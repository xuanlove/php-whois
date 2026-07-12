<?php
/**
 * by: danran
 * wechat: yyy5858588
 * date: 2024-09-07
 */
// 数据源：IANA 根数据库 https://www.iana.org/domains/root/db/<tld>
// 每个 TLD 详情页同时含 RDAP Server 和 WHOIS Server 字段
// 查询优先级：RDAP 优先 → WHOIS 回退（TCP 43）→ ICANN 查询页降级
error_reporting(0);

define('ICANN_LOOKUP', 'https://lookup.icann.org/zh/lookup');
define('ARIN_RDAP', 'https://rdap.arin.net/registry');
define('USER_AGENT', 'php-whois/3.0 (RDAP+WHOIS client)');

// IANA 根数据库：每个 TLD 详情页含 RDAP Server 和 WHOIS Server 字段
define('IANA_ROOT_DB_URL', 'https://www.iana.org/domains/root/db/');
// 统一服务器缓存：{ tld: { rdap: url|null, whois: server|null } }
define('SERVERS_CACHE_FILE', __DIR__ . '/servers-cache.json');
define('SERVERS_CACHE_TTL', 604800); // 7 天（服务器变更极少）
define('WHOIS_PORT', 43);
define('WHOIS_TIMEOUT', 12);

// API 限流：60 秒内同 IP 最多 30 次查询
define('API_RATE_LIMIT_FILE', __DIR__ . '/api-rate-limit.json');
define('API_RATE_LIMIT_WINDOW', 60);
define('API_RATE_LIMIT_MAX', 30);

// RDAP 服务器补充列表（IANA 根数据库未列出 RDAP Server，但实际提供 RDAP 服务的 ccTLD）
// 注意：cn/jp/kr/ru 的 RDAP 服务器经实测均不可用（连接失败），这些 ccTLD 实际未部署 RDAP，
// 已全部移除，改为直接走 WHOIS 查询。若将来某 ccTLD 部署了 RDAP，可在此补充。
$EXTRA_RDAP_SERVERS = [];

// 请求处理：被 index.php 统一入口或 whois.php 直接访问时调用
// 入口逻辑：?api=domain → API JSON（限流）；?domain=xxx → Web 查询 JSON；无 domain → 400
function handleWhoisRequest() {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_GET['domain']) || $_GET['domain'] === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Missing domain parameter']);
        exit;
    }

    // API 模式：?api=domain —— 仅返回原始数据（RDAP JSON 或 WHOIS 文本），未注册直接返回错误
    // 查询逻辑与 Web 模式一致，区别仅在于响应精简（剥离 fallback/query_url 等内部字段）
    if (isset($_GET['api']) && $_GET['api'] !== '') {
        // 限流检查
        if (!checkApiRateLimit(getClientIp())) {
            http_response_code(429);
            header('Retry-After: ' . API_RATE_LIMIT_WINDOW);
            echo json_encode(['error' => 'Rate limit exceeded', 'limit' => API_RATE_LIMIT_MAX, 'window' => API_RATE_LIMIT_WINDOW]);
            exit;
        }
        echo json_encode(getApiRecord($_GET['domain']), JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(getRdapRecord($_GET['domain']), JSON_UNESCAPED_UNICODE);
}

// 直接访问 whois.php 时自动处理请求；被 index.php include 时不自动执行（由 index.php 调用）
if (!defined('INDEX_PHP')) {
    handleWhoisRequest();
}

// 主查询函数：RDAP 优先 → WHOIS 回退 → ICANN 降级
// 服务器信息统一从 IANA 根数据库获取（同时含 RDAP 和 WHOIS）

// 构建 WHOIS 失败的错误信息（区分无法到达 / 不可用 / 超时等）
// whoisError 取值见 whoisSocketQuery 注释
function buildWhoisErrorDetail($whoisError) {
    switch ($whoisError) {
        case 'unreachable':  return 'WHOIS server unreachable (connection timeout)';
        case 'unavailable':  return 'WHOIS server unavailable (connection refused)';
        case 'dns_failed':   return 'WHOIS server DNS resolution failed';
        case 'read_timeout': return 'WHOIS server read timeout (no response)';
        case 'empty':        return 'WHOIS server returned empty response';
        default:             return 'WHOIS query failed';
    }
}

// 构建最终降级错误信息（综合 RDAP 和 WHOIS 尝试结果）
function buildErrorMessage($rdapUrl, $whoisServer, $whoisError) {
    if ($rdapUrl !== null && $whoisServer !== null) {
        return 'RDAP query failed; ' . buildWhoisErrorDetail($whoisError);
    }
    if ($whoisServer !== null) {
        return buildWhoisErrorDetail($whoisError);
    }
    if ($rdapUrl !== null) {
        return 'RDAP query failed';
    }
    return 'No RDAP/WHOIS service available';
}

function getRdapRecord($input) {
    $domain = normalizeDomain($input);
    if ($domain === '') {
        http_response_code(400);
        return ['domain' => $input, 'error' => 'Invalid domain or IP'];
    }

    // IP 地址查询：走 ARIN RDAP（会自动重定向到对应 RIR）
    if (filter_var($domain, FILTER_VALIDATE_IP)) {
        return queryIpRdap($domain);
    }

    $fallback = ICANN_LOOKUP . '?domain=' . urlencode($domain);

    // 从 IANA 根数据库获取该域名的 RDAP + WHOIS 服务器（多级后缀优先匹配）
    $servers = findServersForDomain($domain);
    $rdapUrl = $servers['rdap'];
    $whoisServer = $servers['whois'];

    // ① RDAP 优先
    if ($rdapUrl !== null) {
        $resp = queryDomainRdap($domain, $rdapUrl);
        if (isset($resp['rdap'])) {
            return array_merge($resp, ['rdap_server' => $rdapUrl, 'source' => 'rdap']);
        }
        // 404 = 域名未注册，属正常业务结果，不再回退
        if (isset($resp['error']) && $resp['error'] === 'Domain Not Registered') return $resp;
        // 其它失败（网络/SSL/服务器异常）→ 继续走 WHOIS 回退
    }

    // ② WHOIS 回退（TCP 43）
    $whoisError = null;
    if ($whoisServer !== null) {
        $whoisResult = queryWhois($domain, $whoisServer);
        if ($whoisResult['data'] !== null) {
            return [
                'domain' => $domain,
                'whois' => $whoisResult['data'],
                'whois_server' => $whoisServer,
                'source' => 'whois'
            ];
        }
        $whoisError = $whoisResult['error'];
    }

    // ③ 最终降级：ICANN 查询页（根据已尝试的查询给出准确错误信息）
    $error = buildErrorMessage($rdapUrl, $whoisServer, $whoisError);
    return [
        'domain' => $domain,
        'error' => $error,
        'fallback' => $fallback
    ];
}

// API 响应构建：仅返回原始数据
//   RDAP 命中 → { domain, source: 'rdap', data: <原始 RDAP JSON> }
//   WHOIS 命中 → { domain, source: 'whois', data: <原始 WHOIS 文本> }
//   未注册 / 失败 → { domain, error: '...' }
function getApiRecord($input) {
    $record = getRdapRecord($input);
    $api = ['domain' => isset($record['domain']) ? $record['domain'] : $input];
    if (isset($record['rdap'])) {
        $api['source'] = 'rdap';
        $api['data'] = $record['rdap'];
    } elseif (isset($record['whois'])) {
        $api['source'] = 'whois';
        $api['data'] = $record['whois'];
    } elseif (isset($record['error'])) {
        $api['error'] = $record['error'];
    }
    return $api;
}

// 获取客户端真实 IP（兼容反向代理 X-Forwarded-For）
function getClientIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) {
        return $_SERVER['HTTP_X_REAL_IP'];
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

// API 限流：60 秒滑动窗口内同 IP 最多 30 次
// 返回 true=允许，false=超限
function checkApiRateLimit($ip) {
    $now = time();
    $data = [];
    if (is_file(API_RATE_LIMIT_FILE)) {
        $cached = json_decode(file_get_contents(API_RATE_LIMIT_FILE), true);
        if (is_array($cached)) $data = $cached;
    }

    $record = isset($data[$ip]) ? $data[$ip] : ['count' => 0, 'window_start' => $now];
    // 窗口已过期 → 重置
    if ($now - $record['window_start'] >= API_RATE_LIMIT_WINDOW) {
        $record = ['count' => 0, 'window_start' => $now];
    }
    $record['count']++;
    $data[$ip] = $record;

    // 顺带清理过期窗口，避免文件无限增长
    foreach ($data as $key => $val) {
        if ($now - $val['window_start'] >= API_RATE_LIMIT_WINDOW * 24) unset($data[$key]);
    }

    @file_put_contents(API_RATE_LIMIT_FILE, json_encode($data), LOCK_EX);
    return $record['count'] <= API_RATE_LIMIT_MAX;
}

// 为域名查找 RDAP + WHOIS 服务器（多级后缀优先匹配）
// RDAP 和 WHOIS 独立查找各自的最长匹配：
//   - RDAP：先查补充列表，再查 IANA 根数据库，取最长后缀命中
//   - WHOIS：查 IANA 根数据库，取最长后缀命中（IANA 通常仅一级 TLD 有 WHOIS，
//     如 com.cn 会退化到 cn 的 whois.cnnic.cn）
// 返回 ['rdap' => url|null, 'whois' => server|null]
function findServersForDomain($domain) {
    $parts = explode('.', $domain);
    if (count($parts) < 2) return ['rdap' => null, 'whois' => null];

    $rdap = null;
    $whois = null;
    // 从最长后缀逐级退化匹配（com.cn 优先于 cn）
    for ($i = 1; $i < count($parts); $i++) {
        $candidate = implode('.', array_slice($parts, $i));

        // RDAP：先查补充列表（IANA 未收录但实际提供的 RDAP）
        if ($rdap === null && isset($GLOBALS['EXTRA_RDAP_SERVERS'][$candidate])) {
            $rdap = $GLOBALS['EXTRA_RDAP_SERVERS'][$candidate];
        }

        // 查 IANA 根数据库统一缓存（含 rdap + whois）
        $ianaServers = discoverServers($candidate);
        if ($rdap === null && isset($ianaServers['rdap'])) {
            $rdap = $ianaServers['rdap'];
        }
        if ($whois === null && isset($ianaServers['whois'])) {
            $whois = $ianaServers['whois'];
        }

        // 两者都已命中则无需继续退化
        if ($rdap !== null && $whois !== null) break;
    }
    return ['rdap' => $rdap, 'whois' => $whois];
}

// 通过 IANA 根数据库发现 TLD 的 RDAP + WHOIS 服务器
// 数据源：https://www.iana.org/domains/root/db/<tld>
// 返回 ['rdap' => url|null, 'whois' => server|null]
function discoverServers($tld) {
    $tld = strtolower($tld);
    if ($tld === '') return ['rdap' => null, 'whois' => null];

    $cache = getServersCache();
    // 缓存命中（带 TTL，通过文件修改时间判断）
    if (is_file(SERVERS_CACHE_FILE) && (time() - filemtime(SERVERS_CACHE_FILE)) < SERVERS_CACHE_TTL) {
        if (isset($cache[$tld])) {
            return $cache[$tld];
        }
    }

    // 抓取 IANA 详情页
    $url = IANA_ROOT_DB_URL . rawurlencode($tld);
    $html = httpGet($url);
    if ($html === null) {
        // 抓取失败：尝试用过期缓存兜底
        if (isset($cache[$tld])) return $cache[$tld];
        return ['rdap' => null, 'whois' => null];
    }

    // strip HTML 标签后正则提取（兼容各种标签格式）
    $text = strip_tags($html);
    $rdap = null;
    $whois = null;
    if (preg_match('/RDAP\s*Server\s*:?\s*(https?:\/\/[^\s<]+)/i', $text, $m)) {
        $rdap = rtrim(trim($m[1]), '/');
    }
    if (preg_match('/WHOIS\s*Server\s*:?\s*([a-z0-9.\-]+\.[a-z]{2,})/i', $text, $m2)) {
        $whois = strtolower(trim($m2[1]));
    }

    // 写入统一缓存（含空结果，避免重复抓取无服务的 TLD）
    $cache[$tld] = ['rdap' => $rdap, 'whois' => $whois];
    saveServersCache($cache);

    return $cache[$tld];
}

// 读取服务器缓存
function getServersCache() {
    if (!is_file(SERVERS_CACHE_FILE)) return [];
    $cached = json_decode(file_get_contents(SERVERS_CACHE_FILE), true);
    return is_array($cached) ? $cached : [];
}

// 写入服务器缓存
function saveServersCache($cache) {
    @file_put_contents(SERVERS_CACHE_FILE, json_encode($cache, JSON_UNESCAPED_SLASHES));
}

// 规范化域名
function normalizeDomain($domain) {
    $domain = trim($domain);
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = preg_replace('#^www\.#i', '', $domain);
    // IPv6 地址含冒号，需在剥离 URL 路径前先检测，否则会被截断
    if (filter_var($domain, FILTER_VALIDATE_IP)) return $domain;
    $domain = preg_replace('#[/:].*$#', '', $domain);
    if ($domain === '') return '';
    if (filter_var($domain, FILTER_VALIDATE_IP)) return $domain;
    $converted = @idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII);
    return strtolower($converted !== false ? $converted : $domain);
}

// 查询域名 RDAP（失败时返回 error，由主流程统一决定是否回退 WHOIS）
function queryDomainRdap($domain, $baseUrl) {
    $url = $baseUrl . '/domain/' . $domain;
    $resp = httpGetJson($url);
    if ($resp === null) {
        return ['domain' => $domain, 'error' => 'RDAP query failed', 'query_url' => $url];
    }
    if ($resp['code'] === 404) {
        return ['domain' => $domain, 'error' => 'Domain Not Registered'];
    }
    if ($resp['code'] !== 200) {
        return ['domain' => $domain, 'error' => 'RDAP server returned HTTP ' . $resp['code']];
    }
    return ['domain' => $domain, 'rdap' => $resp['data']];
}

// 查询 IP RDAP（ARIN 作为入口，自动重定向到对应 RIR）
function queryIpRdap($ip) {
    $url = ARIN_RDAP . '/ip/' . $ip;
    $resp = httpGetJson($url);
    if ($resp === null) {
        return ['domain' => $ip, 'error' => 'RDAP query failed', 'fallback' => ICANN_LOOKUP . '?domain=' . urlencode($ip)];
    }
    if ($resp['code'] === 404) {
        return ['domain' => $ip, 'error' => 'IP not found'];
    }
    if ($resp['code'] !== 200) {
        return ['domain' => $ip, 'error' => 'RDAP server returned HTTP ' . $resp['code']];
    }
    return ['domain' => $ip, 'rdap' => $resp['data'], 'rdap_server' => ARIN_RDAP, 'source' => 'rdap'];
}

// HTTP GET（返回原始字符串）
function httpGet($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => USER_AGENT,
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) return null;
    return $raw;
}

// HTTP GET JSON（返回 ['code'=>int, 'data'=>array] 或 null）
function httpGetJson($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_HTTPHEADER => ['Accept: application/rdap+json'],
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    return ['code' => $code, 'data' => $data];
}

// ===== WHOIS 服务器发现 + TCP 43 查询 =====

// WHOIS 查询（TCP 43 端口）
// 支持注册局→注册商二次查询（如 .com 的 whois.verisign-grs.com 会指向注册商 WHOIS）
// 返回 ['data' => string|null, 'error' => string|null]
function queryWhois($domain, $server) {
    $result = whoisSocketQuery($server, $domain);
    if ($result['data'] === null) {
        return ['data' => null, 'error' => $result['error']];
    }
    $raw = $result['data'];

    // 二次查询：注册局响应中常含 "Registrar WHOIS Server:" 指向注册商
    // 注册商 WHOIS 含更详细信息（注册人、联系方式等）
    if (preg_match('/Registrar\s*WHOIS\s*Server\s*:?\s*([a-z0-9.\-]+\.[a-z]{2,})/i', $raw, $m)) {
        $registrarServer = strtolower(trim($m[1]));
        // 避免循环：仅当注册商服务器与注册局不同时二次查询
        if ($registrarServer !== $server) {
            $regResult = whoisSocketQuery($registrarServer, $domain);
            if ($regResult['data'] !== null) {
                $raw .= "\n\n===== 注册商 WHOIS (" . $registrarServer . ") =====\n" . $regResult['data'];
            }
            // 注册商查询失败不影响注册局结果，仅忽略附加信息
        }
    }
    return ['data' => $raw, 'error' => null];
}

// 底层 WHOIS socket 查询（TCP 43）
// 返回 ['data' => string|null, 'error' => string|null]
//   error 取值：
//     'unreachable'  连接超时/无法到达（网络不通、防火墙拦截）
//     'unavailable'  连接被拒绝（服务器未监听 43 端口）
//     'dns_failed'   域名解析失败（WHOIS 服务器域名无效）
//     'empty'        连接成功但响应为空（服务器无数据）
//     'read_timeout' 读取超时（连接成功但无响应）
function whoisSocketQuery($server, $query) {
    $fp = @fsockopen($server, WHOIS_PORT, $errno, $errstr, WHOIS_TIMEOUT);
    if (!$fp) {
        // 区分错误类型
        if ($errno === 110 || $errno === 10060 || strpos($errstr, 'timed out') !== false) {
            return ['data' => null, 'error' => 'unreachable'];
        }
        if ($errno === 111 || $errno === 10061 || strpos($errstr, 'refused') !== false) {
            return ['data' => null, 'error' => 'unavailable'];
        }
        if ($errno === 0 && (strpos($errstr, 'getaddrinfo') !== false || strpos($errstr, 'php_network_getaddresses') !== false)) {
            return ['data' => null, 'error' => 'dns_failed'];
        }
        return ['data' => null, 'error' => 'unreachable'];
    }
    stream_set_timeout($fp, WHOIS_TIMEOUT);
    // 某些服务器要求以 \r\n 结尾
    fputs($fp, $query . "\r\n");
    $resp = '';
    $timedOut = false;
    while (!feof($fp)) {
        $chunk = fread($fp, 8192);
        if ($chunk === false || $chunk === '') break;
        $resp .= $chunk;
        $info = stream_get_meta_data($fp);
        if (!empty($info['timed_out'])) { $timedOut = true; break; }
    }
    fclose($fp);
    if ($resp === '') {
        return ['data' => null, 'error' => $timedOut ? 'read_timeout' : 'empty'];
    }
    // 转换为 UTF-8（WHOIS 响应可能是各种编码）
    $converted = @mb_convert_encoding($resp, 'UTF-8', 'UTF-8, ISO-8859-1, GBK, BIG5, EUC-JP');
    return ['data' => $converted !== '' ? $converted : $resp, 'error' => null];
}
