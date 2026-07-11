<?php
/**
 * by: danran
 * wechat: yyy5858588
 * date: 2024-09-07
 */
// RDAP 引导文件来源 https://data.iana.org/rdap/dns.json
// 无 RDAP 服务的后缀回退到 https://lookup.icann.org/zh/lookup
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

define('RDAP_BOOTSTRAP_URL', 'https://data.iana.org/rdap/dns.json');
define('RDAP_CACHE_FILE', __DIR__ . '/rdap-cache.json');
define('RDAP_CACHE_TTL', 86400); // 24 小时
define('ICANN_LOOKUP', 'https://lookup.icann.org/zh/lookup');
define('ARIN_RDAP', 'https://rdap.arin.net/registry');
define('USER_AGENT', 'php-whois/2.0 (RDAP client)');

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

// 主查询函数
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

    // 域名查询：查找 TLD 对应的 RDAP 服务器
    $map = getRdapBootstrap();
    if ($map === null) {
        http_response_code(503);
        return ['domain' => $domain, 'error' => 'RDAP bootstrap unavailable'];
    }

    $rdapUrl = findRdapServer($domain, $map);
    if ($rdapUrl === null) {
        // 无 RDAP 服务，回退到 ICANN 查询页面
        return [
            'domain' => $domain,
            'error' => 'No RDAP server for this TLD',
            'fallback' => ICANN_LOOKUP . '?domain=' . urlencode($domain)
        ];
    }

    return queryDomainRdap($domain, $rdapUrl);
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

// 查询域名 RDAP
function queryDomainRdap($domain, $baseUrl) {
    $url = $baseUrl . '/domain/' . $domain;
    $resp = httpGetJson($url);
    // 查询失败时提供 ICANN 查询页作为降级出口，保证用户始终能继续查询
    $fallback = ICANN_LOOKUP . '?domain=' . urlencode($domain);
    if ($resp === null) {
        // 网络/SSL/超时等失败：返回 fallback 让用户可跳转 ICANN 查询
        return ['domain' => $domain, 'error' => 'RDAP query failed', 'query_url' => $url, 'fallback' => $fallback];
    }
    if ($resp['code'] === 404) {
        // 404 表示域名未注册，属于正常业务结果，不提供 fallback
        return ['domain' => $domain, 'error' => 'Domain not found'];
    }
    if ($resp['code'] !== 200) {
        // RDAP 服务器异常（如 500/501/502）：提供 fallback
        return ['domain' => $domain, 'error' => 'RDAP server returned HTTP ' . $resp['code'], 'fallback' => $fallback];
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
