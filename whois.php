<?php
/**
 * by: danran
 * wechat: yyy5858588
 * date: 2024-09-07
 */
// RDAP 引导文件来源 https://data.iana.org/rdap/dns.json
// 查询优先级：RDAP → WHOIS（TCP 43，通过 IANA 根数据库发现服务器）→ ICANN 查询页降级
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

define('RDAP_BOOTSTRAP_URL', 'https://data.iana.org/rdap/dns.json');
define('RDAP_CACHE_FILE', __DIR__ . '/rdap-cache.json');
define('RDAP_CACHE_TTL', 86400); // 24 小时
define('ICANN_LOOKUP', 'https://lookup.icann.org/zh/lookup');
define('ARIN_RDAP', 'https://rdap.arin.net/registry');
define('USER_AGENT', 'php-whois/3.0 (RDAP+WHOIS client)');

// IANA 根数据库：每个 TLD 详情页含 WHOIS Server 字段
// 格式：https://www.iana.org/domains/root/db/<tld>
define('IANA_ROOT_DB_URL', 'https://www.iana.org/domains/root/db/');
define('WHOIS_CACHE_FILE', __DIR__ . '/whois-cache.json');
define('WHOIS_CACHE_TTL', 604800); // 7 天（WHOIS 服务器变更极少）
define('WHOIS_PORT', 43);
define('WHOIS_TIMEOUT', 12);

// 已知 ccTLD RDAP 服务器补充列表（IANA 引导文件未收录但实际提供 RDAP 服务）
// 优先于 IANA 引导文件使用
// 注意：.tw 已被 IANA 引导文件正确收录（ccrdap.twnic.tw/tw），此处不再覆盖。
// 仅补充 IANA 引导文件未收录但实际提供 RDAP 服务的 ccTLD。
$EXTRA_RDAP_SERVERS = [
    'cn'       => 'https://rdap.cnnic.cn',
    'com.cn'   => 'https://rdap.cnnic.cn',
    'net.cn'   => 'https://rdap.cnnic.cn',
    'org.cn'   => 'https://rdap.cnnic.cn',
    'gov.cn'   => 'https://rdap.cnnic.cn',
    'ac.cn'    => 'https://rdap.cnnic.cn',
    'bj.cn'    => 'https://rdap.cnnic.cn',
    'sh.cn'    => 'https://rdap.cnnic.cn',
    'gd.cn'    => 'https://rdap.cnnic.cn',
    'zj.cn'    => 'https://rdap.cnnic.cn',
    'jp'       => 'https://rdap.jprs.jp',
    'kr'       => 'https://rdap.kisa.or.kr',
    'ru'       => 'https://rdap.tcinet.ru',
];

// 入口
if (!isset($_GET['domain']) || $_GET['domain'] === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing domain parameter']);
    exit;
}

echo json_encode(getRdapRecord($_GET['domain']), JSON_UNESCAPED_UNICODE);

// 主查询函数：RDAP 优先 → WHOIS 回退 → ICANN 降级
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

    // ① RDAP 优先：查找 TLD 对应的 RDAP 服务器
    $map = getRdapBootstrap();
    $rdapUrl = ($map !== null) ? findRdapServer($domain, $map) : null;

    if ($rdapUrl !== null) {
        $resp = queryDomainRdap($domain, $rdapUrl);
        // RDAP 成功返回数据
        if (isset($resp['rdap'])) return $resp;
        // 404 = 域名未注册，属正常业务结果，不再回退
        if (isset($resp['error']) && $resp['error'] === 'Domain not found') return $resp;
        // 其它失败（网络/SSL/服务器异常）→ 继续走 WHOIS 回退
    }

    // ② WHOIS 回退：通过 IANA 根数据库发现 WHOIS 服务器，TCP 43 查询
    $tld = extractTld($domain);
    $whoisServer = null;
    if ($tld !== '') {
        $whoisServer = discoverWhoisServer($tld);
        if ($whoisServer !== null) {
            $whoisText = queryWhois($domain, $whoisServer);
            if ($whoisText !== null) {
                return [
                    'domain' => $domain,
                    'whois' => $whoisText,
                    'whois_server' => $whoisServer,
                    'source' => 'whois'
                ];
            }
        }
    }

    // ③ 最终降级：ICANN 查询页（根据已尝试的查询给出准确错误信息）
    if ($whoisServer !== null) {
        $error = 'WHOIS query failed';
    } elseif ($rdapUrl !== null) {
        $error = 'RDAP query failed';
    } else {
        $error = 'No RDAP/WHOIS service available';
    }
    return [
        'domain' => $domain,
        'error' => $error,
        'fallback' => $fallback
    ];
}

// 提取一级 TLD（IANA 根数据库按一级 TLD 索引）
function extractTld($domain) {
    $parts = explode('.', $domain);
    if (count($parts) < 2) return '';
    return strtolower(end($parts));
}

// 规范化域名
function normalizeDomain($domain) {
    $domain = trim($domain);
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = preg_replace('#^www\.#i', '', $domain);
    $domain = preg_replace('#[/:].*$#', '', $domain);
    if ($domain === '') return '';
    if (filter_var($domain, FILTER_VALIDATE_IP)) return $domain;
    $converted = @idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII);
    return strtolower($converted !== false ? $converted : $domain);
}

// 获取 RDAP 引导文件（带本地缓存）
function getRdapBootstrap() {
    // 检查缓存是否有效
    if (is_file(RDAP_CACHE_FILE) && (time() - filemtime(RDAP_CACHE_FILE)) < RDAP_CACHE_TTL) {
        $cached = json_decode(file_get_contents(RDAP_CACHE_FILE), true);
        if (is_array($cached)) return $cached;
    }

    // 从 IANA 获取最新引导文件
    $raw = httpGet(RDAP_BOOTSTRAP_URL);
    if ($raw === null) {
        // 获取失败，尝试用过期缓存兜底
        if (is_file(RDAP_CACHE_FILE)) {
            $cached = json_decode(file_get_contents(RDAP_CACHE_FILE), true);
            if (is_array($cached)) return $cached;
        }
        return null;
    }

    $bootstrap = json_decode($raw, true);
    if (!$bootstrap || !isset($bootstrap['services'])) return null;

    // 转换为 TLD => RDAP base URL 映射
    $map = [];
    foreach ($bootstrap['services'] as $service) {
        if (count($service) < 2) continue;
        $tlds = $service[0];
        $urls = $service[1];
        // 优先取 https URL
        $url = '';
        foreach ($urls as $u) {
            if (strpos($u, 'https://') === 0) { $url = $u; break; }
        }
        if (!$url && !empty($urls)) $url = $urls[0];
        if (!$url) continue;
        foreach ($tlds as $tld) {
            $map[strtolower($tld)] = rtrim($url, '/');
        }
    }

    @file_put_contents(RDAP_CACHE_FILE, json_encode($map, JSON_UNESCAPED_SLASHES));
    return $map;
}

// 根据 TLD 查找 RDAP 服务器（多级后缀优先匹配）
function findRdapServer($domain, $map) {
    global $EXTRA_RDAP_SERVERS;
    $parts = explode('.', $domain);
    if (count($parts) < 2) return null;

    // 从最长后缀逐级退化匹配
    for ($i = 1; $i < count($parts); $i++) {
        $candidate = implode('.', array_slice($parts, $i));
        // 优先查补充列表（已知 ccTLD）
        if (isset($EXTRA_RDAP_SERVERS[$candidate])) {
            return $EXTRA_RDAP_SERVERS[$candidate];
        }
        // 再查 IANA 引导文件
        if (isset($map[$candidate])) {
            return $map[$candidate];
        }
    }
    return null;
}

// 查询域名 RDAP（失败时返回 error，由主流程统一决定是否回退 WHOIS）
function queryDomainRdap($domain, $baseUrl) {
    $url = $baseUrl . '/domain/' . $domain;
    $resp = httpGetJson($url);
    if ($resp === null) {
        return ['domain' => $domain, 'error' => 'RDAP query failed', 'query_url' => $url];
    }
    if ($resp['code'] === 404) {
        return ['domain' => $domain, 'error' => 'Domain not found'];
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
    return ['domain' => $ip, 'rdap' => $resp['data']];
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

// 读取 WHOIS 服务器缓存（TLD => server，空字符串表示无 WHOIS 服务）
function getWhoisCache() {
    if (!is_file(WHOIS_CACHE_FILE)) return [];
    $cached = json_decode(file_get_contents(WHOIS_CACHE_FILE), true);
    return is_array($cached) ? $cached : [];
}

// 写入 WHOIS 服务器缓存
function saveWhoisCache($cache) {
    @file_put_contents(WHOIS_CACHE_FILE, json_encode($cache, JSON_UNESCAPED_SLASHES));
}

// 通过 IANA 根数据库发现 TLD 的 WHOIS 服务器
// 数据源：https://www.iana.org/domains/root/db/<tld>（页面含 "WHOIS Server: xxx"）
function discoverWhoisServer($tld) {
    $tld = strtolower($tld);
    if ($tld === '') return null;

    // 1. 检查缓存（带 TTL，通过文件修改时间判断）
    $cache = getWhoisCache();
    if (is_file(WHOIS_CACHE_FILE) && (time() - filemtime(WHOIS_CACHE_FILE)) < WHOIS_CACHE_TTL) {
        if (isset($cache[$tld])) {
            return $cache[$tld] !== '' ? $cache[$tld] : null;
        }
    }

    // 2. 抓取 IANA 详情页
    $url = IANA_ROOT_DB_URL . rawurlencode($tld);
    $html = httpGet($url);
    if ($html === null) {
        // 抓取失败：尝试用过期缓存兜底
        if (isset($cache[$tld]) && $cache[$tld] !== '') return $cache[$tld];
        return null;
    }

    // 3. 解析 WHOIS Server 字段（strip HTML 标签后正则匹配，兼容各种标签格式）
    $text = strip_tags($html);
    $server = null;
    if (preg_match('/WHOIS\s*Server\s*:?\s*([a-z0-9.\-]+\.[a-z]{2,})/i', $text, $m)) {
        $server = strtolower(trim($m[1]));
    }

    // 4. 写入缓存（含空结果，避免重复抓取无 WHOIS 的 TLD）
    $cache[$tld] = $server !== null ? $server : '';
    saveWhoisCache($cache);

    return $server;
}

// WHOIS 查询（TCP 43 端口）
// 支持注册局→注册商二次查询（如 .com 的 whois.verisign-grs.com 会指向注册商 WHOIS）
function queryWhois($domain, $server) {
    $raw = whoisSocketQuery($server, $domain);
    if ($raw === null) return null;

    // 二次查询：注册局响应中常含 "Registrar WHOIS Server:" 指向注册商
    // 注册商 WHOIS 含更详细信息（注册人、联系方式等）
    if (preg_match('/Registrar\s*WHOIS\s*Server\s*:?\s*([a-z0-9.\-]+\.[a-z]{2,})/i', $raw, $m)) {
        $registrarServer = strtolower(trim($m[1]));
        // 避免循环：仅当注册商服务器与注册局不同时二次查询
        if ($registrarServer !== $server) {
            $registrarRaw = whoisSocketQuery($registrarServer, $domain);
            if ($registrarRaw !== null) {
                return $raw . "\n\n===== 注册商 WHOIS (" . $registrarServer . ") =====\n" . $registrarRaw;
            }
        }
    }
    return $raw;
}

// 底层 WHOIS socket 查询（TCP 43）
function whoisSocketQuery($server, $query) {
    $fp = @fsockopen($server, WHOIS_PORT, $errno, $errstr, WHOIS_TIMEOUT);
    if (!$fp) return null;
    stream_set_timeout($fp, WHOIS_TIMEOUT);
    // 某些服务器要求以 \r\n 结尾
    fputs($fp, $query . "\r\n");
    $resp = '';
    while (!feof($fp)) {
        $chunk = fread($fp, 8192);
        if ($chunk === false || $chunk === '') break;
        $resp .= $chunk;
        $info = stream_get_meta_data($fp);
        if (!empty($info['timed_out'])) break;
    }
    fclose($fp);
    if ($resp === '') return null;
    // 转换为 UTF-8（WHOIS 响应可能是各种编码）
    $converted = @mb_convert_encoding($resp, 'UTF-8', 'UTF-8, ISO-8859-1, GBK, BIG5, EUC-JP');
    return $converted !== '' ? $converted : $resp;
}
