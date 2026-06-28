<?php
/**
 * by: danran
 * wechat: yyy5858588
 * date: 2024-09-07
 */
// 数据参考根区数据库 https://www.iana.org/domains/root/db

// 按环境区分错误报告：生产环境关闭显示，开发环境可开启
if (getenv('APP_ENV') === 'dev') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// 简单的基于 APCu 的请求频率限制（每 IP 每分钟 30 次）
function rateLimit() {
    if (!function_exists('apcu_enabled') || !apcu_enabled()) {
        return true;
    }
    $key = 'whois_rl_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $limit = 30;
    $window = 60;
    $count = apcu_inc($key, 1, $success, $window);
    if ($count === false) {
        apcu_store($key, 1, $window);
        $count = 1;
    }
    return $count <= $limit;
}

// 读取 whois.json（APCu 缓存反序列化结果，避免每次请求解析 50KB JSON）
function loadWhoisServers() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        $found = false;
        $cached = apcu_fetch('whois_servers', $found);
        if ($found && is_array($cached)) {
            return $cached;
        }
    }
    $json = @file_get_contents(__DIR__ . '/whois.json');
    $cached = $json ? (json_decode($json, true) ?: []) : [];
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        apcu_store('whois_servers', $cached, 3600);
    }
    return $cached;
}

// WHOIS 查询结果缓存（TTL 1 小时），减少对上游 WHOIS 服务器的重复请求
function getCachedResult($domain) {
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        $found = false;
        $data = apcu_fetch('whois_res_' . $domain, $found);
        if ($found) {
            return $data;
        }
    }
    return null;
}

function setCachedResult($domain, $data) {
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        apcu_store('whois_res_' . $domain, $data, 3600);
    }
}

// 辅助函数：根据域名获取 WHOIS 服务器地址
function getWhoisServer($domain, $whoisServers) {
    // 优先判断 IP 地址（IPv4 含 4 段点号，会被误当作域名后缀匹配）
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

// 执行一次 WHOIS 查询（带 10 秒连接/读取超时）
function queryWhois($server, $query) {
    $conn = @fsockopen($server, 43, $errno, $errstr, 10);
    if (!$conn) {
        return null;
    }
    stream_set_timeout($conn, 10);
    fwrite($conn, $query . "\r\n");
    $result = '';
    while (!feof($conn)) {
        $chunk = fgets($conn, 128);
        if ($chunk === false) {
            break;
        }
        $result .= $chunk;
        $info = stream_get_meta_data($conn);
        if ($info['timed_out']) {
            fclose($conn);
            return null;
        }
    }
    fclose($conn);
    return str_replace("\r", "", $result);
}

// 从 WHOIS 返回中解析 Registrar WHOIS Server（用于二次查询跟随重定向）
function extractRegistrarWhoisServer($raw) {
    if (preg_match('/Registrar WHOIS Server:\s*(\S+)/i', $raw, $m)) {
        return trim($m[1]);
    }
    return '';
}

// 获取 WHOIS 记录（含重定向跟随 + 缓存）
function getWhoisRecord($domain, $whoisServers) {
    // 缓存命中
    $cached = getCachedResult($domain);
    if ($cached !== null) {
        return $cached;
    }

    $whoisServer = getWhoisServer($domain, $whoisServers);
    if (!$whoisServer) {
        return errorResponse($domain, 'Unknown TLD', 400);
    }

    $raw = queryWhois($whoisServer, $domain);
    if ($raw === null) {
        return errorResponse($domain, "Failed to connect to WHOIS server $whoisServer", 500);
    }

    // 跟随注册商 WHOIS 服务器重定向（仅一次，避免循环）
    $registrarServer = extractRegistrarWhoisServer($raw);
    $followedRaw = null;
    if ($registrarServer && $registrarServer !== $whoisServer) {
        $followedRaw = queryWhois($registrarServer, $domain);
    }

    $finalRaw = $followedRaw ?: $raw;

    $response = [
        'domain' => $domain,
        'whois' => $finalRaw,
        'whoisServer' => $whoisServer,
        'followed' => $followedRaw ? true : false,
    ];
    setCachedResult($domain, $response);
    return $response;
}

function errorResponse($domain, $message, $statusCode) {
    return [
        'domain' => $domain,
        'error' => $message,
        '_status' => $statusCode,
    ];
}

// 主入口
if (isset($_GET['domain'])) {
    if (!rateLimit()) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Too Many Requests']);
        exit;
    }

    $input = trim($_GET['domain']);
    // IDN 域名转换为 Punycode（如 中文.com -> xn--fiq228c.com）
    if (function_exists('idn_to_ascii')) {
        $converted = @idn_to_ascii($input, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
        if ($converted !== false) {
            $input = $converted;
        }
    }

    $whoisServers = loadWhoisServers();
    $result = getWhoisRecord($input, $whoisServers);
    $statusCode = isset($result['_status']) ? $result['_status'] : 200;
    unset($result['_status']);
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result);
}
