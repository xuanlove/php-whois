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
    $parts = explode('.', $domain);
    if (count($parts) < 2) return null;

    // 从最长后缀逐级退化匹配
    for ($i = 1; $i < count($parts); $i++) {
        $candidate = implode('.', array_slice($parts, $i));
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
