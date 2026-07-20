<?php
/**
 * 独立 IP 归属地查询 API
 *
 * 数据源：ip-api.com 免费版（中文语言，IPv4/IPv6 均支持）
 *
 * 调用方式（tool.xuanlove.host/ip/ 为优先入口）：
 *   1. GET  /ip/                 → 查询调用者公网 IP 的归属地
 *      curl https://tool.xuanlove.host/ip/
 *      wget -qO- https://tool.xuanlove.host/ip/
 *
 *   2. GET  /ip/?ip=8.8.8.8       → 查询指定 IP
 *      curl "https://tool.xuanlove.host/ip/?ip=8.8.8.8"
 *
 *   3. POST /ip/  JSON body       → 查询指定 IP
 *      curl -X POST https://tool.xuanlove.host/ip/ \
 *           -H "Content-Type: application/json" \
 *           -d '{"ip": "54.255.104.99"}'
 *
 * 响应（统一 JSON）：
 *   成功 → {"status":"success","query":"8.8.8.8","geolocation":{...}}
 *   失败 → {"status":"error","message":"...","query":"..."}（HTTP 4xx/5xx）
 */

error_reporting(0);

// 复用 whois.php 中的归属地查询与客户端 IP 解析能力
define('INDEX_PHP', true);
require __DIR__ . '/whois.php';

// 统一 JSON 响应头 + CORS（允许任意客户端调用）
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 预检请求直接返回 204
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * 输出 JSON 响应并退出
 */
function ipApiRespond($status, $payload, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['status' => $status], $payload), JSON_UNESCAPED_UNICODE);
    exit;
}

// === 解析目标 IP ===
$ip = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // POST JSON body：{"ip":"8.8.8.8"}
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (is_array($body) && isset($body['ip'])) {
        $ip = trim($body['ip']);
    } elseif (isset($_POST['ip'])) {
        // 兼容表单 POST
        $ip = trim($_POST['ip']);
    }
} else {
    // GET ?ip=8.8.8.8（可选）
    if (isset($_GET['ip']) && $_GET['ip'] !== '') {
        $ip = trim($_GET['ip']);
    }
}

// 无 IP 参数 → 查询调用者公网 IP
if ($ip === '') {
    $ip = getClientIp();
}

// === 校验 IP 格式（IPv4/IPv6）===
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    ipApiRespond('error', [
        'message' => 'Invalid IP address',
        'query' => $ip,
    ], 400);
}

// === 查询归属地 ===
// 注意：ip.php 作为 /ip/ 入口被 queryIpGeolocation() 的主源调用，
// 因此这里必须直接调用 fetchGeoFromFallback()（ip-api.com），
// 禁止调用 queryIpGeolocation()，否则会形成递归死循环。
$geo = fetchGeoFromFallback($ip);
if ($geo === null) {
    ipApiRespond('error', [
        'message' => 'Geolocation query failed',
        'query' => $ip,
    ], 502);
}

// === 成功响应 ===
ipApiRespond('success', [
    'query' => $ip,
    'geolocation' => $geo,
], 200);
