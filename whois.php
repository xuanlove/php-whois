<?php
/**
 * by: danran
 * wechat: yyy5858588
 * date: 2024-09-07
 */
// 数据参考根区数据库 https://www.iana.org/domains/root/db
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$whoisServers = json_decode(file_get_contents(__DIR__ . '/whois.json'), true);

// 获取 WHOIS 记录
function getWhoisRecord($domain) {
    global $whoisServers;

    $domain = normalizeDomain($domain);
    if ($domain === '') {
        return jsonError($domain, 'Invalid domain or IP', 400);
    }

    $whoisServer = getWhoisServer($domain, $whoisServers);
    if (!$whoisServer) {
        return jsonError($domain, 'Unknown TLD', 400);
    }

    $result = queryWhois($whoisServer, $domain);
    if ($result === false) {
        return jsonError($domain, "Failed to connect to WHOIS server $whoisServer", 500);
    }

    // 跟随 WHOIS 重定向：解析 "Registrar WHOIS Server"，最多再查一次
    $referral = parseReferral($result);
    if ($referral && $referral !== $whoisServer) {
        $sub = queryWhois($referral, $domain);
        if ($sub !== false) {
            $result .= "\n--- Registrar WHOIS ($referral) ---\n" . $sub;
        }
    }

    return ['domain' => $domain, 'whois' => $result];
}

// 规范化域名：去协议/端口/路径/空格，IDN 转 Punycode
function normalizeDomain($domain) {
    $domain = trim($domain);
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = preg_replace('#^www\.#i', '', $domain);
    $domain = preg_replace('#[/:].*$#', '', $domain);
    $domain = idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII);
    return strtolower($domain);
}

// 实际发起 WHOIS 查询，带超时
function queryWhois($server, $query) {
    $conn = @fsockopen($server, 43, $errno, $errstr, 10);
    if (!$conn) return false;
    stream_set_timeout($conn, 10);
    fwrite($conn, $query . "\r\n");
    $result = '';
    while (!feof($conn)) {
        $chunk = fgets($conn, 128);
        if ($chunk === false) break;
        $result .= $chunk;
        if (connection_aborted()) break;
    }
    $info = stream_get_meta_data($conn);
    fclose($conn);
    if (!empty($info['timed_out'])) return false;
    return str_replace("\r", '', $result);
}

// 解析 Registrar WHOIS Server 字段
function parseReferral($raw) {
    if (preg_match('/^\s*(Registrar\s+)?WHOIS\s+Server\s*:\s*(\S+)/im', $raw, $m)) {
        $host = strtolower(trim($m[2]));
        if (filter_var($host, FILTER_VALIDATE_DOMAIN)) return $host;
    }
    return '';
}

// 根据域名获取 WHOIS 服务器地址
function getWhoisServer($domain, $whoisServers) {
    // 优先判断 IP 地址
    if (filter_var($domain, FILTER_VALIDATE_IP)) {
        return 'whois.apnic.net';
    }

    $parts = explode('.', $domain);
    if (count($parts) < 2) {
        return '';
    }

    // 优先匹配多级后缀（如 com.cn），再退化为最后一段
    for ($i = 1; $i < count($parts); $i++) {
        $candidate = implode('.', array_slice($parts, $i));
        if (isset($whoisServers[$candidate]) && $whoisServers[$candidate]) {
            return $whoisServers[$candidate];
        }
    }

    // 兜底：未知后缀走 IANA
    return 'whois.iana.org';
}

function jsonError($domain, $message, $code) {
    http_response_code($code);
    return ['domain' => $domain, 'error' => $message];
}

// 入口：http://localhost/whois.php?domain=rw2.cc
if (isset($_GET['domain']) && $_GET['domain'] !== '') {
    echo json_encode(getWhoisRecord($_GET['domain']), JSON_UNESCAPED_UNICODE);
}
