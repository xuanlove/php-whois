<?php
// 统一入口：?domain=xxx 返回查询 JSON（含 ?api=domain 的 API 模式），否则输出 HTML 页面
define('INDEX_PHP', true);
require __DIR__ . '/whois.php';
if (isset($_GET['domain']) && $_GET['domain'] !== '') {
    handleWhoisRequest();
    exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1e3a5f">
    <title data-i18n="title">WHOIS 查询</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://lf9-cdn-tos.bytecdntp.com/cdn/expire-1-M/dom-to-image/2.6.0/dom-to-image.min.js"></script>
    <script>
        // ===== 轻量内嵌 i18n（兼容 i18next.t 接口，零外部依赖） =====
        window.i18next = (function () {
            let _lang = 'zh-CN';
            let _resources = {};
            return {
                init: function (options, callback) {
                    _lang = options.lng || 'zh-CN';
                    _resources = options.resources || {};
                    if (callback) callback(null, this);
                },
                changeLanguage: function (lang, callback) {
                    _lang = lang;
                    if (callback) callback(null, this);
                },
                t: function (key, params) {
                    params = params || {};
                    const dict = (_resources[_lang] && _resources[_lang].translation) || {};
                    let str = dict[key];
                    if (str === undefined) {
                        str = params.defaultValue !== undefined ? params.defaultValue : key;
                    }
                    // {{var}} 插值
                    str = str.replace(/\{\{(\w+)\}\}/g, function (_, name) {
                        return params[name] !== undefined ? params[name] : '';
                    });
                    return str;
                }
            };
        })();
    </script>
    <style>
        /* ===== 精简工具类（替代 Tailwind CDN，零外部依赖） ===== */
        .flex { display: flex; }
        .inline-flex { display: inline-flex; }
        .flex-col { flex-direction: column; }
        .flex-1 { flex: 1 1 0%; }
        .flex-shrink-0 { flex-shrink: 0; }
        .flex-grow { flex-grow: 1; }
        .items-center { align-items: center; }
        .justify-center { justify-content: center; }
        .justify-between { justify-content: space-between; }
        .justify-end { justify-content: flex-end; }
        .hidden { display: none; }
        .block { display: block; }
        .relative { position: relative; }
        .absolute { position: absolute; }
        .fixed { position: fixed; }
        .cursor-pointer { cursor: pointer; }
        .overflow-hidden { overflow: hidden; }
        .overflow-y-auto { overflow-y: auto; }
        .overflow-x-auto { overflow-x: auto; }
        .mx-auto { margin-left: auto; margin-right: auto; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .font-bold { font-weight: 700; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .tracking-tight { letter-spacing: -0.025em; }
        .whitespace-nowrap { white-space: nowrap; }
        .opacity-25 { opacity: 0.25; }
        .opacity-75 { opacity: 0.75; }

        .gap-1\.5 { gap: 0.375rem; }
        .gap-2 { gap: 0.5rem; }
        .gap-2\.5 { gap: 0.625rem; }
        .gap-3 { gap: 0.75rem; }
        .gap-4 { gap: 1rem; }
        .p-1\.5 { padding: 0.375rem; }
        .p-2 { padding: 0.5rem; }
        .p-2\.5 { padding: 0.625rem; }
        .p-4 { padding: 1rem; }
        .p-5 { padding: 1.25rem; }
        .p-6 { padding: 1.5rem; }
        .p-10 { padding: 2.5rem; }
        .px-3 { padding-left: 0.75rem; padding-right: 0.75rem; }
        .px-4 { padding-left: 1rem; padding-right: 1rem; }
        .px-5 { padding-left: 1.25rem; padding-right: 1.25rem; }
        .px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
        .px-7 { padding-left: 1.75rem; padding-right: 1.75rem; }
        .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
        .py-3 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
        .py-3\.5 { padding-top: 0.875rem; padding-bottom: 0.875rem; }
        .py-8 { padding-top: 2rem; padding-bottom: 2rem; }
        .pb-3 { padding-bottom: 0.75rem; }
        .pb-4 { padding-bottom: 1rem; }
        .pb-6 { padding-bottom: 1.5rem; }
        .pl-12 { padding-left: 3rem; }
        .pr-1 { padding-right: 0.25rem; }
        .pr-11 { padding-right: 2.75rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mb-5 { margin-bottom: 1.25rem; }
        .ml-2 { margin-left: 0.5rem; }
        .mt-4 { margin-top: 1rem; }
        .mt-2 { margin-top: 0.5rem; }

        .w-full { width: 100%; }
        .w-1\.5 { width: 0.375rem; }
        .w-3\.5 { width: 0.875rem; }
        .w-4 { width: 1rem; }
        .w-5 { width: 1.25rem; }
        .w-6 { width: 1.5rem; }
        .w-8 { width: 2rem; }
        .w-10 { width: 2.5rem; }
        .w-16 { width: 4rem; }
        .h-full { height: 100%; }
        .h-3\.5 { height: 0.875rem; }
        .h-4 { height: 1rem; }
        .h-5 { height: 1.25rem; }
        .h-6 { height: 1.5rem; }
        .h-8 { height: 2rem; }
        .h-10 { height: 2.5rem; }
        .h-16 { height: 4rem; }

        .rounded-xl { border-radius: 0.75rem; }
        .rounded-2xl { border-radius: 1rem; }
        .rounded-3xl { border-radius: 1.5rem; }
        .rounded-full { border-radius: 9999px; }

        .text-sm { font-size: 0.875rem; line-height: 1.25rem; }
        .text-base { font-size: 1rem; line-height: 1.5rem; }
        .text-lg { font-size: 1.125rem; line-height: 1.75rem; }
        .text-xl { font-size: 1.25rem; line-height: 1.75rem; }
        .text-white { color: #fff; }

        .max-w-6xl { max-width: 72rem; }
        .left-4 { left: 1rem; }
        .right-3 { right: 0.75rem; }
        .top-1\/2 { top: 50%; }
        .-translate-y-1\/2 { transform: translateY(-50%); }

        .space-y-1 > * + * { margin-top: 0.25rem; }
        .space-y-2 > * + * { margin-top: 0.5rem; }

        /* 响应式 sm: 前缀（>=640px） */
        @media (min-width: 640px) {
            .sm\:flex { display: flex; }
            .sm\:hidden { display: none; }
            .sm\:p-6 { padding: 1.5rem; }
            .sm\:p-10 { padding: 2.5rem; }
            .sm\:px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
            .sm\:px-7 { padding-left: 1.75rem; padding-right: 1.75rem; }
            .sm\:gap-2 { gap: 0.5rem; }
            .sm\:text-lg { font-size: 1.125rem; line-height: 1.75rem; }
            .sm\:text-xl { font-size: 1.25rem; line-height: 1.75rem; }
            .sm\:mb-6 { margin-bottom: 1.5rem; }
        }

        /* ===== 主题变量（商业蓝灰色调） ===== */
        :root {
            --bg-base: #1e3a5f;
            --bg-accent-1: #2c5282;
            --bg-accent-2: #1a365d;
            --bg-accent-3: #2a4365;
            --glass-bg: rgba(255, 255, 255, 0.6);
            --glass-bg-strong: rgba(255, 255, 255, 0.78);
            --glass-border: rgba(255, 255, 255, 0.7);
            --glass-shadow: 0 8px 32px rgba(15, 23, 42, 0.18);
            --glass-blur: 24px;
            --text-primary: #0f172a;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --accent-light: rgba(37, 99, 235, 0.12);
            --accent-gradient: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            --input-bg: rgba(255, 255, 255, 0.65);
            --input-border: rgba(255, 255, 255, 0.75);
            --input-focus-shadow: 0 0 0 4px rgba(37, 99, 235, 0.18);
            --inner-glass-bg: rgba(255, 255, 255, 0.42);
            --inner-glass-border: rgba(255, 255, 255, 0.55);
            --tag-bg: rgba(37, 99, 235, 0.1);
            --tag-text: #1d4ed8;
            --divider: rgba(255, 255, 255, 0.5);
            --scrollbar-thumb: rgba(37, 99, 235, 0.3);
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --header-bg: rgba(255, 255, 255, 0.65);
        }

        html.dark {
            --bg-base: #0b1220;
            --bg-accent-1: #0f172a;
            --bg-accent-2: #111827;
            --bg-accent-3: #1e293b;
            --glass-bg: rgba(15, 23, 42, 0.6);
            --glass-bg-strong: rgba(15, 23, 42, 0.78);
            --glass-border: rgba(148, 163, 184, 0.22);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.45);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #64748b;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --accent-light: rgba(59, 130, 246, 0.15);
            --accent-gradient: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            --input-bg: rgba(15, 23, 42, 0.55);
            --input-border: rgba(148, 163, 184, 0.28);
            --input-focus-shadow: 0 0 0 4px rgba(59, 130, 246, 0.22);
            --inner-glass-bg: rgba(30, 41, 59, 0.42);
            --inner-glass-border: rgba(148, 163, 184, 0.2);
            --tag-bg: rgba(59, 130, 246, 0.15);
            --tag-text: #93c5fd;
            --divider: rgba(148, 163, 184, 0.2);
            --scrollbar-thumb: rgba(59, 130, 246, 0.4);
            --header-bg: rgba(15, 23, 42, 0.65);
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html,
        body {
            height: 100%;
            margin: 0;
            overflow: hidden;
        }

        body {
            font-family: "Space Grotesk", "Noto Sans SC", -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
            color: var(--text-primary);
            background: var(--bg-base);
            transition: color 0.3s ease;
            position: relative;
            min-height: 100vh;
        }

        /* ===== 动态渐变背景 ===== */
        .bg-canvas {
            position: fixed;
            inset: 0;
            z-index: -2;
            background: linear-gradient(-45deg, var(--bg-base), var(--bg-accent-1), var(--bg-accent-2), var(--bg-accent-3));
            background-size: 400% 400%;
            animation: gradientFlow 18s ease infinite;
        }

        @keyframes gradientFlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* 漂浮光斑 */
        .bg-blobs {
            position: fixed;
            inset: 0;
            z-index: -1;
            overflow: hidden;
            pointer-events: none;
        }

        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.5;
            mix-blend-mode: screen;
        }

        html.dark .blob {
            mix-blend-mode: lighten;
            opacity: 0.35;
        }

        .blob-1 {
            width: 480px; height: 480px;
            background: #2563eb;
            top: -120px; left: -120px;
            animation: floatBlob1 22s ease-in-out infinite;
        }

        .blob-2 {
            width: 420px; height: 420px;
            background: #1e40af;
            bottom: -100px; right: -80px;
            animation: floatBlob2 26s ease-in-out infinite;
        }

        .blob-3 {
            width: 360px; height: 360px;
            background: #3b82f6;
            top: 40%; right: 20%;
            animation: floatBlob3 20s ease-in-out infinite;
        }

        @keyframes floatBlob1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(80px, 120px) scale(1.15); }
        }

        @keyframes floatBlob2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-100px, -80px) scale(0.9); }
        }

        @keyframes floatBlob3 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-60px, 100px) scale(1.2); }
        }

        /* ===== 毛玻璃卡片（核心） ===== */
        .glass {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--glass-blur)) saturate(180%);
            -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(180%);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        html.dark .glass {
            box-shadow: var(--glass-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .glass-strong {
            background: var(--glass-bg-strong);
            backdrop-filter: blur(var(--glass-blur)) saturate(200%);
            -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(200%);
            border: 1px solid var(--glass-border);
        }

        .glass-inner {
            background: var(--inner-glass-bg);
            backdrop-filter: blur(12px) saturate(150%);
            -webkit-backdrop-filter: blur(12px) saturate(150%);
            border: 1px solid var(--inner-glass-border);
        }

        /* ===== 现代输入框（毛玻璃） ===== */
        .glass-input {
            background: var(--input-bg);
            backdrop-filter: blur(12px) saturate(150%);
            -webkit-backdrop-filter: blur(12px) saturate(150%);
            border: 1.5px solid var(--input-border);
            color: var(--text-primary);
            transition: all 0.25s ease;
        }

        .glass-input::placeholder {
            color: var(--text-muted);
        }

        .glass-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: var(--input-focus-shadow);
            background: rgba(255, 255, 255, 0.8);
        }

        html.dark .glass-input:focus {
            background: rgba(15, 23, 42, 0.7);
        }

        /* ===== 渐变按钮 ===== */
        .btn-gradient {
            background: var(--accent-gradient);
            color: #fff;
            border: none;
            cursor: pointer;
            transition: transform 0.18s, box-shadow 0.25s, opacity 0.2s;
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
            position: relative;
            overflow: hidden;
        }

        .btn-gradient::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.25), transparent);
            opacity: 0;
            transition: opacity 0.25s;
        }

        .btn-gradient:hover:not(:disabled)::before {
            opacity: 1;
        }

        .btn-gradient:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(37, 99, 235, 0.45);
        }

        .btn-gradient:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-gradient:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        /* ===== 图标按钮 ===== */
        .icon-btn {
            color: var(--text-secondary);
            background: transparent;
            border: none;
            cursor: pointer;
            border-radius: 12px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .icon-btn:hover {
            color: var(--accent);
            background: var(--accent-light);
        }

        /* ===== 来源徽章 + 服务器地址行 ===== */
        .source-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            line-height: 1.4;
            white-space: nowrap;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            border: 1px solid transparent;
        }

        .source-rdap {
            background: rgba(37, 99, 235, 0.14);
            color: var(--accent);
            border-color: rgba(37, 99, 235, 0.28);
        }

        html.dark .source-rdap {
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
            border-color: rgba(59, 130, 246, 0.4);
        }

        .source-whois {
            background: rgba(100, 116, 139, 0.16);
            color: var(--text-muted);
            border-color: rgba(100, 116, 139, 0.3);
        }

        html.dark .source-whois {
            background: rgba(148, 163, 184, 0.18);
            color: #cbd5e1;
            border-color: rgba(148, 163, 184, 0.35);
        }

        .source-info-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 14px;
            padding: 8px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            color: var(--text-muted);
            background: var(--accent-light);
            border: 1px solid var(--divider);
        }

        .source-info-row svg {
            flex-shrink: 0;
            color: var(--accent);
        }

        .source-server {
            font-family: "Space Grotesk", monospace;
            font-weight: 600;
            color: var(--text-secondary);
            word-break: break-all;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ===== 字段行 ===== */
        .field-row {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 12px 16px;
            border-radius: 14px;
            transition: background 0.2s;
        }

        .field-row:hover {
            background: var(--accent-light);
        }

        @media (min-width: 640px) {
            .field-row {
                flex-direction: row;
                align-items: center;
                gap: 16px;
            }
        }

        .field-label {
            display: inline-flex;
            align-items: center;
            font-size: 11px;
            font-weight: 600;
            color: var(--tag-text);
            background: var(--tag-bg);
            padding: 5px 12px;
            border-radius: 999px;
            white-space: nowrap;
            flex-shrink: 0;
            align-self: flex-start;
            letter-spacing: 0.3px;
        }

        @media (min-width: 640px) {
            .field-label {
                min-width: 110px;
                justify-content: center;
                align-self: center;
            }
        }

        .field-value {
            color: var(--text-primary);
            word-break: break-all;
            font-size: 14px;
            line-height: 1.65;
            flex: 1;
            font-weight: 500;
        }

        /* ===== 时间徽章 ===== */
        .time-badge {
            display: inline-flex;
            align-items: center;
            font-size: 11px;
            font-weight: 600;
            color: #fff;
            padding: 3px 10px;
            border-radius: 999px;
            margin-left: 8px;
            vertical-align: middle;
        }

        .time-badge.green { background: var(--success); }
        .time-badge.blue { background: var(--accent); }

        /* ===== 历史项 ===== */
        .history-item {
            background: var(--inner-glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--inner-glass-border);
            padding: 12px 14px;
            border-radius: 14px;
            transition: all 0.18s ease;
        }

        .history-item:hover {
            transform: translateX(3px);
            border-color: var(--accent);
            background: var(--accent-light);
        }

        /* ===== 滚动条 ===== */
        .scroll-area {
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb) transparent;
        }

        .scroll-area::-webkit-scrollbar { width: 6px; height: 6px; }
        .scroll-area::-webkit-scrollbar-track { background: transparent; }
        .scroll-area::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 10px;
        }
        .scroll-area::-webkit-scrollbar-thumb:hover { background: var(--accent); }

        /* ===== API 使用说明 ===== */
        .api-docs .api-code {
            display: block;
            font-family: "Space Grotesk", "SF Mono", Menlo, Consolas, monospace;
            font-size: 0.8rem;
            padding: 0.6rem 0.75rem;
            background: var(--tag-bg);
            color: var(--tag-text);
            border-radius: 0.5rem;
            word-break: break-all;
            border: 1px solid var(--inner-glass-border);
        }

        .api-docs .api-param-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }

        .api-docs .api-param-list li {
            font-size: 0.8rem;
            color: var(--text-secondary);
            padding-left: 0.875rem;
            position: relative;
            line-height: 1.5;
        }

        .api-docs .api-param-list li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.55rem;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--accent);
            opacity: 0.7;
        }

        .api-docs .api-param-list code {
            font-family: "Space Grotesk", "SF Mono", Menlo, Consolas, monospace;
            font-size: 0.78rem;
            padding: 0.1rem 0.4rem;
            background: var(--tag-bg);
            color: var(--tag-text);
            border-radius: 0.3rem;
            margin-right: 0.25rem;
        }

        /* ===== 动画 ===== */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .loading-spinner { animation: spin 0.8s linear infinite; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }

        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }

        /* ===== Toast ===== */
        .toast {
            position: fixed;
            bottom: 28px;
            left: 50%;
            transform: translateX(-50%) translateY(30px);
            background: var(--glass-bg-strong);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
            padding: 14px 26px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 500;
            opacity: 0;
            transition: opacity 0.3s, transform 0.3s;
            z-index: 100;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            pointer-events: none;
            white-space: nowrap;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        /* ===== 移动端历史抽屉 ===== */
        .history-drawer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            max-height: 75vh;
            background: var(--glass-bg-strong);
            backdrop-filter: blur(var(--glass-blur)) saturate(180%);
            -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(180%);
            border-top: 1px solid var(--glass-border);
            border-radius: 28px 28px 0 0;
            transform: translateY(100%);
            transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1);
            z-index: 50;
            box-shadow: 0 -12px 40px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
        }

        .history-drawer.open {
            transform: translateY(0);
        }

        .drawer-handle {
            width: 44px;
            height: 5px;
            background: var(--text-muted);
            border-radius: 3px;
            margin: 12px auto 8px;
            opacity: 0.4;
            flex-shrink: 0;
        }

        /* ===== 链接按钮 ===== */
        .link-btn {
            color: var(--accent);
            font-size: 13px;
            font-weight: 600;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .link-btn:hover { color: var(--accent-hover); }

        /* ===== JSON 代码块 ===== */
        .json-block {
            background: var(--inner-glass-bg);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--inner-glass-border);
            border-radius: 14px;
            padding: 16px;
            font-family: "SF Mono", "Fira Code", "Consolas", monospace;
            font-size: 12px;
            color: var(--text-secondary);
            white-space: pre-wrap;
            word-break: break-all;
            line-height: 1.7;
            max-height: 400px;
            overflow-y: auto;
        }

        /* ===== 布局 ===== */
        .app-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .main-content {
            flex: 1;
            overflow: hidden;
            padding: 12px;
            display: flex;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        /* 浮动按钮常驻，结果区底部留白避免被遮挡 */
        .result-container {
            padding-bottom: 80px;
        }

        .query-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border-radius: 24px;
        }

        @media (max-width: 639px) {
            .query-panel {
                border-radius: 20px;
            }
        }

        /* 圆形浮动按钮（历史记录，所有设备） */
        .fab-history {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--accent-gradient);
            color: #fff;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.4);
            z-index: 40;
            transition: transform 0.2s;
        }

        .fab-history:active {
            transform: scale(0.92);
        }

        /* 遮罩层 */
        .drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            z-index: 45;
        }

        .drawer-overlay.show {
            opacity: 1;
            pointer-events: auto;
        }
    </style>
    <!-- by: danran  -->
    <!-- wechat: yyy5858588  -->
    <!-- date: 2024-09-07  -->
</head>

<body>
    <!-- 动态渐变背景 -->
    <div class="bg-canvas"></div>
    <div class="bg-blobs">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
    </div>

    <div class="app-container">
        <!-- 顶部导航栏 -->
        <header class="glass" style="border-radius: 0; border-left: none; border-right: none; border-top: none;">
            <div class="px-4 sm:px-6 py-3 flex items-center justify-between max-w-6xl mx-auto w-full">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl flex items-center justify-center" style="background: var(--accent-gradient); box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                    </div>
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight" style="color: var(--text-primary);" data-i18n="title">WHOIS 查询</h1>
                </div>
                <div class="flex items-center gap-1.5 sm:gap-2">
                    <button type="button" id="themeToggle" class="icon-btn p-2.5" title="切换主题">
                        <svg id="themeIconSun" class="w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <svg id="themeIconMoon" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    </button>
                    <select id="languageSelect" class="glass-input text-sm rounded-xl py-2 px-3 cursor-pointer font-medium">
                        <option value="zh-CN">简体</option>
                        <option value="zh-TW">繁體</option>
                        <option value="en">EN</option>
                        <option value="ru">RU</option>
                        <option value="es">ES</option>
                    </select>
                </div>
            </div>
        </header>

        <!-- 主内容区 -->
        <div class="main-content">
            <!-- 查询面板 -->
            <div class="glass query-panel">
                <div class="p-4 sm:p-6 flex flex-col h-full overflow-hidden">
                    <!-- 搜索表单 -->
                    <form id="whois-form" class="mb-4 flex-shrink-0">
                        <div class="flex gap-2.5">
                            <div class="flex-1 relative">
                                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5" style="color: var(--text-muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input type="text" id="domain" name="domain" required placeholder="请输入IP或者域名"
                                    data-i18n="[placeholder]inputPlaceholder"
                                    class="glass-input w-full pl-12 pr-11 py-3.5 rounded-2xl text-sm font-medium"
                                    autocomplete="off" autocapitalize="off" spellcheck="false">
                                <button type="button" id="clear-input"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 icon-btn p-1.5 hidden">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <button type="submit" id="submit-btn"
                                class="btn-gradient flex items-center justify-center px-5 sm:px-7 rounded-2xl text-sm font-semibold whitespace-nowrap">
                                <span data-i18n="search">查询</span>
                                <svg class="loading-spinner ml-2 h-4 w-4 hidden" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        </div>
                    </form>

                    <!-- 结果区 + API 使用说明（共用滚动区，API 说明始终可见） -->
                    <div id="resultDiv" class="flex-1 overflow-hidden">
                        <div class="h-full overflow-y-auto scroll-area result-container pr-1">
                            <div id="importantInfo"></div>
                            <div id="originalInfo" class="mt-4"></div>
                            <!-- API 使用说明 -->
                            <section class="api-docs mt-4">
                                <div class="glass-inner rounded-2xl p-4 sm:p-5">
                                    <h3 class="text-base font-bold flex items-center gap-2 mb-3" style="color: var(--text-primary);">
                                        <svg class="w-4 h-4" style="color: var(--accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                        </svg>
                                        <span data-i18n="apiDocsTitle">API 使用说明</span>
                                    </h3>
                                    <p class="text-sm mb-3" style="color: var(--text-secondary);" data-i18n="apiDocsDesc">通过 API 获取原始 WHOIS/RDAP 数据，查询逻辑与网页一致，仅返回原始数据。</p>
                                    <div class="api-docs-block mb-3">
                                        <div class="text-xs font-semibold mb-1" style="color: var(--text-muted);" data-i18n="apiEndpoint">接口地址</div>
                                        <code class="api-code" id="apiEndpointUrl">?api=domain&amp;domain=example.com</code>
                                    </div>
                                    <div class="api-docs-block mb-3">
                                        <div class="text-xs font-semibold mb-1" style="color: var(--text-muted);" data-i18n="apiParams">请求参数</div>
                                        <ul class="api-param-list">
                                            <li><code>domain</code> <span data-i18n="apiParamDomain">必填，要查询的域名或 IP</span></li>
                                            <li><code>api</code> <span data-i18n="apiParamApi">必填，固定值 domain，启用 API 模式</span></li>
                                        </ul>
                                    </div>
                                    <div class="api-docs-block mb-3">
                                        <div class="text-xs font-semibold mb-1" style="color: var(--text-muted);" data-i18n="apiResponse">响应格式</div>
                                        <ul class="api-param-list">
                                            <li><span data-i18n="apiRespRdap">RDAP 命中：HTTP 200，Content-Type: application/rdap+json，直接返回原始 RDAP JSON</span></li>
                                            <li><span data-i18n="apiRespWhois">WHOIS 命中：HTTP 200，Content-Type: text/plain，直接返回原始 WHOIS 文本</span></li>
                                            <li><span data-i18n="apiRespUnregistered">未注册：HTTP 404，Content-Type: text/plain，返回 Domain Not Registered</span></li>
                                            <li><span data-i18n="apiRespFailed">查询失败：HTTP 502，Content-Type: text/plain，返回错误信息</span></li>
                                        </ul>
                                    </div>
                                    <div class="api-docs-block">
                                        <div class="text-xs font-semibold mb-1" style="color: var(--text-muted);" data-i18n="apiRateLimit">限流规则</div>
                                        <p class="text-sm" style="color: var(--text-secondary);" data-i18n="apiRateLimitDesc">同一 IP 60 秒内最多查询 30 次，超限返回 HTTP 429（含 Retry-After 头，纯文本响应）。</p>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 历史记录侧栏已移除，所有设备统一使用右下角浮动按钮 + 底部抽屉 -->
        </div>
    </div>

    <!-- 历史记录浮动按钮（所有设备） -->
    <button type="button" id="fabHistory" class="fab-history" title="历史记录">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    </button>

    <!-- 历史抽屉遮罩 -->
    <div id="drawerOverlay" class="drawer-overlay"></div>

    <!-- 历史记录抽屉（所有设备） -->
    <div id="historyDrawer" class="history-drawer">
        <div class="drawer-handle"></div>
        <div class="flex items-center justify-between px-5 pb-3 flex-shrink-0">
            <h2 class="text-base font-bold flex items-center gap-2" style="color: var(--text-primary);">
                <svg class="w-4 h-4" style="color: var(--accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span data-i18n="recentSearches">最近查询</span>
            </h2>
            <button type="button" id="closeDrawerBtn" class="icon-btn p-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto scroll-area px-4 pb-6" style="max-height: 55vh;">
            <ul id="history-list" class="space-y-2"></ul>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        // ===== 原生 JS 工具函数 =====
        const $ = (sel) => document.querySelector(sel);
        const $$ = (sel) => document.querySelectorAll(sel);

        document.addEventListener('DOMContentLoaded', function () {
            const HISTORY_KEY = 'whoisHistory';
            const THEME_KEY = 'whoisTheme';
            const MAX_HISTORY = 500;

            const form = $('#whois-form');
            const domainInput = $('#domain');
            const submitBtn = $('#submit-btn');
            const resultDiv = $('#resultDiv');
            const importantInfo = $('#importantInfo');
            const originalInfo = $('#originalInfo');
            const historyList = $('#history-list');
            const clearInputBtn = $('#clear-input');
            const historyDrawer = $('#historyDrawer');
            const drawerOverlay = $('#drawerOverlay');

            function init() {
                bindEvents();
                initTheme();
                initClipboard();
                checkUrlForDomain();
                updateSearchHistory();
                toggleClearButton();
                initI18n();
                updateApiEndpointUrl();
            }

            // 动态生成 API 接口地址：基于当前页面 URL + ?api=domain&domain=example.com
            function updateApiEndpointUrl() {
                var el = $('#apiEndpointUrl');
                if (!el) return;
                var base = window.location.origin + window.location.pathname;
                el.textContent = base + '?api=domain&domain=' + i18next.t('apiDomainExample');
            }

            function bindEvents() {
                form.addEventListener('submit', handleFormSubmit);
                document.addEventListener('click', function (e) {
                    if (e.target.closest('.search-again')) handleSearchAgain(e);
                    if (e.target.closest('#saveScreenshot')) handleSaveScreenshot();
                });
                domainInput.addEventListener('input', toggleClearButton);
                clearInputBtn.addEventListener('click', clearInput);
                document.addEventListener('keydown', handleGlobalKeydown);
                $('#languageSelect').addEventListener('change', handleLanguageChange);
                $('#themeToggle').addEventListener('click', toggleTheme);
                $('#fabHistory').addEventListener('click', openDrawer);
                $('#closeDrawerBtn').addEventListener('click', closeDrawer);
                drawerOverlay.addEventListener('click', closeDrawer);
            }

            function openDrawer() {
                historyDrawer.classList.add('open');
                drawerOverlay.classList.add('show');
            }

            function closeDrawer() {
                historyDrawer.classList.remove('open');
                drawerOverlay.classList.remove('show');
            }

            // ===== 暗黑模式 =====
            function initTheme() {
                const saved = localStorage.getItem(THEME_KEY);
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = saved ? saved === 'dark' : prefersDark;
                applyTheme(isDark);
            }

            function applyTheme(isDark) {
                const html = document.documentElement;
                const sunIcon = $('#themeIconSun');
                const moonIcon = $('#themeIconMoon');
                if (isDark) {
                    html.classList.add('dark');
                    sunIcon.classList.remove('hidden');
                    moonIcon.classList.add('hidden');
                    document.querySelector('meta[name="theme-color"]').setAttribute('content', '#0b1220');
                } else {
                    html.classList.remove('dark');
                    sunIcon.classList.add('hidden');
                    moonIcon.classList.remove('hidden');
                    document.querySelector('meta[name="theme-color"]').setAttribute('content', '#1e3a5f');
                }
            }

            function toggleTheme() {
                const isDark = !document.documentElement.classList.contains('dark');
                applyTheme(isDark);
                localStorage.setItem(THEME_KEY, isDark ? 'dark' : 'light');
            }

            function handleFormSubmit(e) {
                e.preventDefault();
                const domain = domainInput.value.trim();
                if (!domain) return;
                performWhoisLookup(domain);
            }

            function handleSearchAgain(e) {
                const btn = e.target.closest('.search-again');
                const domain = btn.dataset.domain;
                performWhoisLookup(domain);
                closeDrawer();
            }

            function performWhoisLookup(domain) {
                updateButtonState(true);
                updateUrl(domain);
                domainInput.value = domain;
                toggleClearButton();

                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 15000);

                fetch('?domain=' + encodeURIComponent(domain), {
                    signal: controller.signal,
                    headers: { 'Accept': 'application/json' }
                })
                    .then(res => res.json())
                    .then(response => handleWhoisResponse(response))
                    .catch(err => {
                        const msg = err.name === 'AbortError' ? 'timeout' : err.message;
                        showError(i18next.t('requestFailed', { status: escapeHtml(msg) }));
                    })
                    .finally(() => {
                        clearTimeout(timeoutId);
                        updateButtonState(false);
                        updateContent();
                    });
            }

            function handleWhoisResponse(response) {
                if (response.rdap) {
                    // 检测是否为 IP 查询（RDAP 响应含 startAddress 或 objectClassName 为 ip network）
                    const isIpQuery = response.rdap.objectClassName === 'ip network' ||
                        !!response.rdap.startAddress || !!response.rdap.cidr0_cidrs;
                    if (isIpQuery) {
                        const { importantInfoHtml, originalInfoHtml } = formatIpInfo(response.rdap, {
                            server: response.rdap_server || '',
                            source: response.source || 'rdap',
                            ip: response.domain || ''
                        });
                        importantInfo.innerHTML = importantInfoHtml;
                        originalInfo.innerHTML = originalInfoHtml;
                        resultDiv.classList.remove('hidden');
                        resultDiv.classList.add('fade-in');
                        setTimeout(() => resultDiv.classList.remove('fade-in'), 400);
                        updateSearchHistory(domainInput.value.trim());
                        updateContent();
                        return;
                    }
                    const { importantInfoHtml, originalInfoHtml } = formatRdapInfo(response.rdap, {
                        server: response.rdap_server || '',
                        source: response.source || 'rdap'
                    });
                    importantInfo.innerHTML = importantInfoHtml;
                    originalInfo.innerHTML = originalInfoHtml;
                    resultDiv.classList.remove('hidden');
                    resultDiv.classList.add('fade-in');
                    setTimeout(() => resultDiv.classList.remove('fade-in'), 400);
                    updateSearchHistory(domainInput.value.trim());
                    updateContent();
                } else if (response.whois) {
                    // RDAP 无服务/失败，回退到 WHOIS 协议查询成功
                    const { importantInfoHtml, originalInfoHtml } = formatWhoisInfo(response.whois, {
                        server: response.whois_server || '',
                        source: 'whois'
                    });
                    importantInfo.innerHTML = importantInfoHtml;
                    originalInfo.innerHTML = originalInfoHtml;
                    resultDiv.classList.remove('hidden');
                    resultDiv.classList.add('fade-in');
                    setTimeout(() => resultDiv.classList.remove('fade-in'), 400);
                    updateSearchHistory(domainInput.value.trim());
                    updateContent();
                } else if (response.fallback) {
                    // RDAP 与 WHOIS 均失败，显示错误详情 + ICANN 查询链接
                    const fallbackUrl = escapeHtml(response.fallback);
                    const errorDetail = response.error ? translateError(response.error) : '';
                    importantInfo.innerHTML = `
                        <div class="glass-inner rounded-3xl p-6 sm:p-10 mb-4 text-center fade-in">
                            <div class="w-16 h-16 mx-auto mb-5 rounded-full flex items-center justify-center" style="background: rgba(245, 158, 11, 0.15);">
                                <svg class="h-8 w-8" style="color: var(--warning);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <p class="font-semibold mb-2 text-base" style="color: var(--text-primary);" data-i18n="noRdapService">该后缀暂无 RDAP 查询服务</p>
                            ${errorDetail ? `<p class="text-sm mb-5" style="color: var(--text-muted);">${errorDetail}</p>` : '<div class="mb-5"></div>'}
                            <a href="${fallbackUrl}" target="_blank" rel="noopener noreferrer" class="btn-gradient inline-flex items-center px-6 py-3 rounded-2xl text-sm font-semibold">
                                <span data-i18n="icannLookup">前往 ICANN 查询</span>
                                <svg class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </a>
                        </div>
                    `;
                    originalInfo.innerHTML = '';
                    resultDiv.classList.remove('hidden');
                    resultDiv.classList.add('fade-in');
                    setTimeout(() => resultDiv.classList.remove('fade-in'), 400);
                    updateSearchHistory(domainInput.value.trim());
                    updateContent();
                } else if (response.error) {
                    showError(i18next.t('error', { message: translateError(response.error) }));
                }
            }

            function getLabel(key) {
                return i18next.t(key, { defaultValue: key });
            }

            // 将后端英文错误信息翻译为本地化文案
            // 识别 WHOIS 服务器错误类型：unreachable / unavailable / dns / read_timeout / empty
            function translateError(errorMsg) {
                if (!errorMsg) return '';
                const e = String(errorMsg);
                // 组合错误优先匹配（RDAP+WHOIS 均失败）
                if (e.indexOf('RDAP query failed') !== -1 && e.indexOf('WHOIS') !== -1) {
                    // 提取 WHOIS 具体错误并拼接
                    const whoisPart = e.split(';')[1] || '';
                    const t = whoisPart.indexOf('unreachable') !== -1 ? i18next.t('whoisUnreachable')
                        : whoisPart.indexOf('unavailable') !== -1 ? i18next.t('whoisUnavailable')
                        : whoisPart.indexOf('DNS resolution') !== -1 ? i18next.t('whoisDnsFailed')
                        : whoisPart.indexOf('read timeout') !== -1 ? i18next.t('whoisReadTimeout')
                        : whoisPart.indexOf('empty response') !== -1 ? i18next.t('whoisEmpty')
                        : '';
                    return i18next.t('rdapWhoisBothFailed') + (t ? '（' + t + '）' : '');
                }
                if (e.indexOf('RDAP query failed') !== -1) return i18next.t('rdapFailed');
                if (e.indexOf('No RDAP/WHOIS service') !== -1) return i18next.t('noService');
                // 单独 WHOIS 错误
                if (e.indexOf('unreachable') !== -1) return i18next.t('whoisUnreachable');
                if (e.indexOf('unavailable') !== -1) return i18next.t('whoisUnavailable');
                if (e.indexOf('DNS resolution') !== -1) return i18next.t('whoisDnsFailed');
                if (e.indexOf('read timeout') !== -1) return i18next.t('whoisReadTimeout');
                if (e.indexOf('empty response') !== -1) return i18next.t('whoisEmpty');
                return escapeHtml(e);
            }

            // HTML 转义，防止 XSS
            function escapeHtml(text) {
                if (text == null) return '';
                return String(text).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            // 从 vcardArray 提取联系人信息（RFC 7483 jCard 格式）
            function extractVcard(vcardArray) {
                const result = {};
                if (!Array.isArray(vcardArray) || vcardArray.length < 2) return result;
                const entries = vcardArray[1];
                if (!Array.isArray(entries)) return result;
                entries.forEach(function (entry) {
                    if (!Array.isArray(entry) || entry.length < 4) return;
                    const key = entry[0];
                    const value = entry[3];
                    if (key === 'fn') result.fn = value;
                    else if (key === 'email') result.email = value;
                    else if (key === 'org') result.org = value;
                    else if (key === 'tel') {
                        const type = entry[1] && entry[1].type ? entry[1].type : 'voice';
                        result.tel = result.tel || value;
                        result['tel_' + type] = value;
                    } else if (key === 'adr') {
                        // adr 的 label 在 entry[1].label 中
                        if (entry[1] && entry[1].label) result.address = entry[1].label;
                    }
                });
                return result;
            }

            // 格式化 RDAP 日期为本地时区显示
            function formatDate(dateString) {
                if (!dateString) return '';
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return escapeHtml(dateString);
                return escapeHtml(date.toLocaleString());
            }

            // 从 WHOIS 原始文本提取结构化字段
            // 兼容多种 WHOIS 服务器格式：
            //   - Verisign/CNNIC/PIR 标准 "Key: Value" 格式
            //   - .ru TCI 小写键 "domain:" "nserver:" "created:" "paid-till:" "state:"
            //   - .jp JPRS 方括号 "[Domain Name]" "[登録年月日]" 格式
            //   - .kr KISA "Key :" 冒号前空格 + 韩文/英文双语（优先英文段）
            //   - .tw TWNIC "Record expires on" 缩进多行格式
            function parseWhoisText(text) {
                if (!text || typeof text !== 'string') return { fields: {}, rawDates: {} };

                // .kr 含韩文+英文双段，优先英文段（# ENGLISH 之后）
                const englishSplit = text.split(/#\s*ENGLISH/i);
                if (englishSplit.length > 1) text = englishSplit[englishSplit.length - 1];

                const result = {};
                const rawDates = {};
                const multiValues = { status: [], nameServers: [] };
                const lines = text.split(/\r?\n/);
                let inNsSection = false; // .tw/.kr 多行 NS 区段

                // 规范化日期为可被 new Date() 解析的格式
                function normalizeDate(val) {
                    if (!val) return '';
                    let s = val.trim();
                    s = s.replace(/\s*\(.*?\)\s*/g, ' ').trim(); // 去时区标注 (JST)(UTC+8)
                    s = s.replace(/(\d{4})\/(\d{1,2})\/(\d{1,2})/, '$1-$2-$3'); // .jp 2001/02/02
                    s = s.replace(/(\d{4})\.\s*(\d{1,2})\.\s*(\d{1,2})\./, '$1-$2-$3'); // .kr 2007. 08. 21.
                    s = s.replace(/(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})/, '$1T$2'); // .cn 补 T
                    return s;
                }

                function setField(field, val) {
                    if (field === 'creationDate' || field === 'expiryDate' || field === 'updatedDate') {
                        if (!rawDates[field]) {
                            const norm = normalizeDate(val);
                            rawDates[field] = norm;
                            result[field] = norm;
                        }
                    } else if (!result[field]) {
                        result[field] = val;
                    }
                }
                function addStatus(val) {
                    // .ru state 含逗号分隔多状态；去 URL 部分
                    val.split(/[,，]/).forEach(function (s) {
                        s = s.trim().split(/\s+/)[0];
                        if (s && multiValues.status.indexOf(s) === -1) multiValues.status.push(s);
                    });
                }
                function addNs(val) {
                    val = val.trim().replace(/\.$/, ''); // .ru NS 末尾带点
                    if (val && multiValues.nameServers.indexOf(val) === -1) multiValues.nameServers.push(val);
                }

                // 统一键名映射（兼容多语言键名）
                function parseKey(rawKey, val) {
                    const key = rawKey.toLowerCase().trim().replace(/\s+/g, ' ');
                    const v = val.trim();

                    if (key === 'domain name' || key === 'domain' || key === 'query') {
                        setField('domainName', v);
                    } else if (key === 'registry domain id' || key === 'domain id' || key === 'roid') {
                        setField('registryDomainId', v);
                    } else if (key === 'registrar' || key === 'sponsoring registrar' || key === 'registration service provider' || key === 'authorized agency' || key === '등록대행자') {
                        setField('registrar', v);
                    } else if (key === 'registrar iana id' || key === 'iana id') {
                        setField('ianaId', v);
                    } else if (key === 'creation date' || key === 'created' || key === 'created date' || key === 'registration time' || key === 'registered on' || key === 'registered date' || key === '등록일' || key === 'record created on' || key === '登録年月日') {
                        setField('creationDate', v);
                    } else if (key === 'registry expiry date' || key === 'expiry date' || key === 'expiration date' || key === 'expire' || key === 'expires on' || key === 'paid-till' || key === 'expiration time' || key === '사용 종료일' || key === 'record expires on' || key === '有効期限') {
                        setField('expiryDate', v);
                    } else if (key === 'updated date' || key === 'last updated' || key === 'last modified' || key === 'changed' || key === 'last updated date' || key === '최근 정보 변경일' || key === '最終更新' || key === 'last updated on') {
                        setField('updatedDate', v);
                    } else if (key === 'domain status' || key === 'status' || key === 'state' || key === '상태' || key === '등록정보 보호' || key === 'ロック状態' || key === '状態') {
                        addStatus(v);
                    } else if (key === 'name server' || key === 'nserver' || key === 'name servers' || key === 'host name') {
                        addNs(v);
                    } else if (key === 'dnssec') {
                        if (!result.dnssec) {
                            const lower = v.toLowerCase();
                            const signed = lower === 'signed' || lower === 'yes' || lower.indexOf('1') !== -1 || lower === '서명됨';
                            const k = signed ? 'dnssecSigned' : 'dnssecUnsigned';
                            result.dnssec = '<span data-i18n="' + k + '">' + i18next.t(k, { defaultValue: signed ? '已签名' : '未签名' }) + '</span>';
                        }
                    } else if (key === 'registrant' || key === 'registrant name' || key === '등록인') {
                        setField('registrant', v);
                    } else if (key === 'registrant contact email' || key === 'ac e-mail' || key === '책임자 전자우편') {
                        setField('registrantEmail', v);
                    }
                }

                lines.forEach(function (line) {
                    const trimmed = line.trim();
                    if (!trimmed) return;
                    // 跳过注释行
                    if (/^(%|#|>>>|---|=====|\[ *JPRS)/.test(trimmed)) return;

                    // .jp 方括号格式 [Key] Value 或 [日本語キー] Value
                    let m = trimmed.match(/^\[([^\]]+)\]\s*(.+)$/);
                    if (m) { parseKey(m[1], m[2]); return; }

                    // .tw: "Record expires on DATE" / "Record created on DATE"（优先于标准键值匹配）
                    m = trimmed.match(/^Record\s+(expires|created)\s+on\s+(.+)$/i);
                    if (m) {
                        setField(m[1].toLowerCase() === 'expires' ? 'expiryDate' : 'creationDate', m[2]);
                        return;
                    }

                    // 标准 "Key: Value" 或 "Key : Value"（冒号前可有空格，.kr 格式）
                    m = trimmed.match(/^(.{1,45}?)\s*[:]\s*(.+)$/);
                    if (m) { inNsSection = false; parseKey(m[1], m[2]); return; }

                    // .tw: "Domain servers in listed order:" 进入 NS 区段
                    if (/domain servers/i.test(trimmed)) { inNsSection = true; return; }
                    // .kr: "Primary Name Server" / "Secondary Name Server" 进入 NS 区段
                    if (/^(primary|secondary)\s+name\s+server/i.test(trimmed)) { inNsSection = true; return; }

                    // NS 区段内的裸主机名行（.tw）或 "Host Name : xxx"（.kr）
                    if (inNsSection) {
                        m = trimmed.match(/^Host\s+Name\s*[:]\s*(.+)$/i);
                        if (m) { addNs(m[1]); return; }
                        // .tw 裸主机名（无键，纯域名行）
                        if (/^[a-z0-9.\-]+\.[a-z]{2,}/i.test(trimmed) && trimmed.indexOf(' ') === -1) {
                            addNs(trimmed);
                            return;
                        }
                    }
                });

                // 合并多值字段
                if (multiValues.status.length) {
                    result.status = multiValues.status.map(escapeHtml).join('<br>');
                }
                if (multiValues.nameServers.length) {
                    result.nameServers = multiValues.nameServers.map(escapeHtml).join('<br>');
                }

                // 转义字符串值 + 日期格式化
                ['domainName', 'registryDomainId', 'registrar', 'ianaId', 'registrant', 'registrantEmail'].forEach(function (k) {
                    if (result[k]) result[k] = escapeHtml(result[k]);
                });
                ['creationDate', 'expiryDate', 'updatedDate'].forEach(function (k) {
                    if (result[k]) result[k] = formatDate(result[k]);
                });

                return { fields: result, rawDates: rawDates };
            }

            // 格式化 WHOIS 文本为结构化概览 + 原始信息 HTML
            function formatWhoisInfo(whoisText, sourceInfo) {
                if (!whoisText || typeof whoisText !== 'string') whoisText = '';
                sourceInfo = sourceInfo || {};
                const sourceServer = escapeHtml(sourceInfo.server || '');
                const parsed = parseWhoisText(whoisText);
                const temp = parsed.fields;
                const rawDates = parsed.rawDates;

                // 按固定顺序构建显示字段（与 RDAP 一致）
                const fieldOrder = ['domainName', 'registrar', 'ianaId', 'registryDomainId',
                    'creationDate', 'expiryDate', 'updatedDate', 'status',
                    'nameServers', 'dnssec', 'registrant', 'registrantEmail'];
                const info = {};
                fieldOrder.forEach(function (key) {
                    if (temp[key]) info[key] = temp[key];
                });

                const registrationPeriod = calculatePeriod(rawDates.creationDate, 'registrationPeriod');
                const remainingTime = calculatePeriod(rawDates.expiryDate, 'remainingTime');

                // 生成重要信息 HTML（与 RDAP 分支风格一致）
                let importantInfoHtml = `
                    <div id="importantInfoCard" class="glass-inner rounded-3xl p-4 sm:p-6 mb-4 fade-in">
                        <div class="flex justify-between items-center mb-5">
                            <h3 class="text-base sm:text-lg font-bold flex items-center gap-2.5" style="color: var(--text-primary);">
                                <span class="w-1.5 h-6 rounded-full" style="background: var(--accent-gradient);"></span>
                                <span data-i18n="whoisOverview">WHOIS 信息概览</span>
                            </h3>
                            <div class="flex items-center gap-2.5">
                                <span class="source-badge source-whois" title="${sourceServer}">WHOIS</span>
                                <button id="saveScreenshot" class="icon-btn p-2.5" title="截图">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="source-info-row">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span data-i18n="whoisServerLabel">WHOIS 服务器</span>：
                            <span class="source-server" title="${sourceServer}">${sourceServer}</span>
                        </div>
                        <div class="space-y-1">
                            ${Object.entries(info).map(([key, value]) => `
                                <div class="field-row">
                                    <span class="field-label" data-i18n="${key}">${getLabel(key)}</span>
                                    <span class="field-value">
                                        ${value}
                                        ${key === 'domainName' ? registrationPeriod : ''}
                                        ${key === 'expiryDate' ? remainingTime : ''}
                                    </span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;

                // 生成原始 WHOIS 文本 HTML
                const escapedText = escapeHtml(whoisText);
                let originalInfoHtml = `
                    <div class="glass-inner rounded-3xl p-4 sm:p-6 fade-in">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-base sm:text-lg font-bold flex items-center gap-2.5" style="color: var(--text-primary);">
                                <span class="w-1.5 h-6 rounded-full" style="background: var(--accent-gradient);"></span>
                                <span data-i18n="whoisRawText">WHOIS 原始信息</span>
                            </h3>
                            <button id="copyOriginalInfo" class="icon-btn p-2.5" data-clipboard-target="#originalInfoContent" title="复制">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                        </div>
                        <pre class="json-block scroll-area" id="originalInfoContent">${escapedText}</pre>
                    </div>
                `;

                return { importantInfoHtml, originalInfoHtml };
            }

            // 格式化 IP RDAP 响应为结构化概览 + 原始信息 HTML
            // 支持 IPv4 和 IPv6，字段来自 RIR（APNIC/ARIN/RIPE/LACNIC/AFRINIC）
            function formatIpInfo(rdap, sourceInfo) {
                if (!rdap || typeof rdap !== 'object') rdap = {};
                sourceInfo = sourceInfo || {};
                const sourceServer = escapeHtml(sourceInfo.server || '');
                const queriedIp = escapeHtml(sourceInfo.ip || '');
                const temp = {};
                const rawDates = {};

                // IP 地址
                if (queriedIp) temp.ipAddress = queriedIp;

                // 网络名称
                if (rdap.name) temp.networkName = escapeHtml(rdap.name);

                // 国家/地区
                if (rdap.country) temp.ipCountry = escapeHtml(rdap.country);

                // IP 范围（优先 handle，否则拼接 startAddress - endAddress）
                if (rdap.handle) {
                    temp.ipRange = escapeHtml(rdap.handle);
                } else if (rdap.startAddress && rdap.endAddress) {
                    temp.ipRange = escapeHtml(rdap.startAddress + ' - ' + rdap.endAddress);
                }

                // CIDR（cidr0_cidrs 数组）
                if (Array.isArray(rdap.cidr0_cidrs) && rdap.cidr0_cidrs.length) {
                    const cidrs = rdap.cidr0_cidrs.map(function (c) {
                        if (c.v4prefix) return c.v4prefix + '/' + c.length;
                        if (c.v6prefix) return c.v6prefix + '/' + c.length;
                        return '';
                    }).filter(Boolean);
                    if (cidrs.length) temp.ipCidr = escapeHtml(cidrs.join(', '));
                }

                // IP 版本
                if (rdap.ipVersion) {
                    const ver = rdap.ipVersion === 'v6' ? 'IPv6' : 'IPv4';
                    temp.ipVersion = escapeHtml(ver);
                }

                // 分配类型
                if (rdap.type) temp.ipType = escapeHtml(rdap.type);

                // 事件：注册、更新
                if (Array.isArray(rdap.events)) {
                    rdap.events.forEach(function (ev) {
                        if (!ev.eventDate) return;
                        if (ev.eventAction === 'registration') {
                            rawDates.creationDate = ev.eventDate;
                            temp.creationDate = formatDate(ev.eventDate);
                        } else if (ev.eventAction === 'last changed') {
                            rawDates.updatedDate = ev.eventDate;
                            temp.updatedDate = formatDate(ev.eventDate);
                        }
                    });
                }

                // 状态
                if (Array.isArray(rdap.status) && rdap.status.length) {
                    temp.status = rdap.status.map(escapeHtml).join('<br>');
                }

                // 描述（remarks）
                if (Array.isArray(rdap.remarks) && rdap.remarks.length) {
                    const desc = rdap.remarks.map(function (r) {
                        return Array.isArray(r.description) ? r.description.join('\n') : (r.description || '');
                    }).filter(Boolean).join('\n');
                    if (desc) temp.ipDescription = escapeHtml(desc).replace(/\n/g, '<br>');
                }

                // 联系信息（entities：abuse 优先，其次 technical、administrative）
                if (Array.isArray(rdap.entities) && rdap.entities.length) {
                    const rolePriority = ['abuse', 'technical', 'administrative', 'registrant'];
                    const contacts = {};
                    rdap.entities.forEach(function (entity) {
                        const roles = entity.roles || [];
                        const vcard = extractVcard(entity.vcardArray);
                        for (let i = 0; i < rolePriority.length; i++) {
                            const role = rolePriority[i];
                            if (roles.indexOf(role) !== -1 && !contacts[role]) {
                                contacts[role] = vcard;
                                contacts[role].handle = entity.handle || '';
                                break;
                            }
                        }
                    });

                    // 拼接联系信息（abuse 联系人最重要）
                    const contactParts = [];
                    if (contacts.abuse) {
                        const c = contacts.abuse;
                        const parts = [];
                        if (c.fn) parts.push(c.fn);
                        if (c.email) parts.push(c.email);
                        if (c.tel) parts.push(c.tel);
                        if (parts.length) contactParts.push(i18next.t('abuseContact', { defaultValue: '滥用投诉' }) + ': ' + parts.join(' / '));
                    }
                    if (contacts.technical) {
                        const c = contacts.technical;
                        const parts = [];
                        if (c.fn) parts.push(c.fn);
                        if (c.email) parts.push(c.email);
                        if (c.tel) parts.push(c.tel);
                        if (parts.length) contactParts.push(i18next.t('techContact', { defaultValue: '技术联系' }) + ': ' + parts.join(' / '));
                    }
                    if (contacts.administrative) {
                        const c = contacts.administrative;
                        const parts = [];
                        if (c.fn) parts.push(c.fn);
                        if (c.email) parts.push(c.email);
                        if (parts.length) contactParts.push(i18next.t('adminContact', { defaultValue: '管理联系' }) + ': ' + parts.join(' / '));
                    }
                    if (contactParts.length) {
                        temp.ipContacts = escapeHtml(contactParts.join('\n')).replace(/\n/g, '<br>');
                    }
                }

                // WHOIS 服务器（port43）
                if (rdap.port43) temp.ipWhoisServer = escapeHtml(rdap.port43);

                // 按固定顺序构建显示字段
                const fieldOrder = ['ipAddress', 'networkName', 'ipCountry', 'ipRange', 'ipCidr',
                    'ipVersion', 'ipType', 'creationDate', 'updatedDate', 'status',
                    'ipDescription', 'ipContacts', 'ipWhoisServer'];
                const info = {};
                fieldOrder.forEach(function (key) {
                    if (temp[key]) info[key] = temp[key];
                });

                const registrationPeriod = calculatePeriod(rawDates.creationDate, 'registrationPeriod');

                // 生成重要信息 HTML
                let importantInfoHtml = `
                    <div id="importantInfoCard" class="glass-inner rounded-3xl p-4 sm:p-6 mb-4 fade-in">
                        <div class="flex justify-between items-center mb-5">
                            <h3 class="text-base sm:text-lg font-bold flex items-center gap-2.5" style="color: var(--text-primary);">
                                <span class="w-1.5 h-6 rounded-full" style="background: var(--accent-gradient);"></span>
                                <span data-i18n="ipOverview">IP 信息概览</span>
                            </h3>
                            <div class="flex items-center gap-2.5">
                                <span class="source-badge source-rdap" title="${sourceServer}">RDAP</span>
                                <button id="saveScreenshot" class="icon-btn p-2.5" title="截图">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="source-info-row">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span data-i18n="rdapServerLabel">RDAP 服务器</span>：
                            <span class="source-server" title="${sourceServer}">${sourceServer}</span>
                        </div>
                        <div class="space-y-1">
                            ${Object.entries(info).map(([key, value]) => `
                                <div class="field-row">
                                    <span class="field-label" data-i18n="${key}">${getLabel(key)}</span>
                                    <span class="field-value">
                                        ${value}
                                        ${key === 'ipAddress' ? registrationPeriod : ''}
                                    </span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;

                // 生成原始 RDAP JSON 信息 HTML
                const rawJson = escapeHtml(JSON.stringify(rdap, null, 2));
                let originalInfoHtml = `
                    <div class="glass-inner rounded-3xl p-4 sm:p-6 fade-in">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-base sm:text-lg font-bold flex items-center gap-2.5" style="color: var(--text-primary);">
                                <span class="w-1.5 h-6 rounded-full" style="background: var(--accent-gradient);"></span>
                                <span data-i18n="originalRdap">RDAP 原始信息</span>
                            </h3>
                            <button id="copyOriginalInfo" class="icon-btn p-2.5" data-clipboard-target="#originalInfoContent" title="复制">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                        </div>
                        <div class="json-block scroll-area" id="originalInfoContent">${rawJson}</div>
                    </div>
                `;

                return { importantInfoHtml, originalInfoHtml };
            }

            // 解析 RDAP JSON 响应，生成概览和原始信息 HTML
            function formatRdapInfo(rdap, sourceInfo) {
                // 防止 null/undefined 导致后续属性读取崩溃
                if (!rdap || typeof rdap !== 'object') rdap = {};
                sourceInfo = sourceInfo || {};
                const sourceServer = escapeHtml(sourceInfo.server || '');
                const isRdap = (sourceInfo.source || 'rdap') === 'rdap';
                const temp = {};
                const rawDates = {}; // 保存原始日期用于时间跨度计算

                // 域名（优先显示 Unicode 形式）
                temp.domainName = escapeHtml(rdap.unicodeName || rdap.ldhName || '');

                // 注册局域名 ID
                if (rdap.handle) temp.registryDomainId = escapeHtml(rdap.handle);

                // 事件：注册、过期、更新、RDAP 数据库更新
                if (Array.isArray(rdap.events)) {
                    rdap.events.forEach(function (ev) {
                        if (!ev.eventDate) return;
                        if (ev.eventAction === 'registration') {
                            rawDates.creationDate = ev.eventDate;
                            temp.creationDate = formatDate(ev.eventDate);
                        } else if (ev.eventAction === 'expiration') {
                            rawDates.expiryDate = ev.eventDate;
                            temp.expiryDate = formatDate(ev.eventDate);
                        } else if (ev.eventAction === 'last changed') {
                            rawDates.updatedDate = ev.eventDate;
                            temp.updatedDate = formatDate(ev.eventDate);
                        } else if (ev.eventAction === 'last update of RDAP database') {
                            temp.rdapUpdated = formatDate(ev.eventDate);
                        }
                    });
                }

                // 状态
                if (Array.isArray(rdap.status) && rdap.status.length) {
                    temp.status = rdap.status.map(escapeHtml).join('<br>');
                }

                // 域名服务器（含 IP 地址，若提供）
                if (Array.isArray(rdap.nameservers) && rdap.nameservers.length) {
                    const ns = rdap.nameservers.map(function (n) {
                        let name = n.unicodeName || n.ldhName || '';
                        if (!name) return '';
                        // 附加 IP 地址（部分 RDAP 服务器提供）
                        const ips = [];
                        if (Array.isArray(n.ipAddresses)) {
                            n.ipAddresses.forEach(function (ip) {
                                ips.push(ip);
                            });
                        } else if (n.ipAddresses && typeof n.ipAddresses === 'object') {
                            ['v4', 'v6'].forEach(function (ver) {
                                if (Array.isArray(n.ipAddresses[ver])) {
                                    n.ipAddresses[ver].forEach(function (ip) { ips.push(ip); });
                                }
                            });
                        }
                        return ips.length ? name + ' (' + ips.join(', ') + ')' : name;
                    }).filter(Boolean);
                    if (ns.length) temp.nameServers = ns.map(escapeHtml).join('<br>');
                }

                // DNSSEC（含 DS 记录详情）
                if (rdap.secureDNS) {
                    const signed = rdap.secureDNS.delegationSigned === true;
                    const key = signed ? 'dnssecSigned' : 'dnssecUnsigned';
                    let dnssecHtml = `<span data-i18n="${key}">${i18next.t(key, { defaultValue: signed ? '已签名' : '未签名' })}</span>`;
                    // DS 记录
                    if (signed && Array.isArray(rdap.secureDNS.dsData) && rdap.secureDNS.dsData.length) {
                        const ds = rdap.secureDNS.dsData.map(function (d) {
                            const parts = [];
                            if (d.keyTag) parts.push('keyTag=' + d.keyTag);
                            if (d.algorithm) parts.push('alg=' + d.algorithm);
                            if (d.digestType) parts.push('digestType=' + d.digestType);
                            if (d.digest) parts.push('digest=' + d.digest.substring(0, 16) + '...');
                            return parts.join(' ');
                        });
                        dnssecHtml += '<br><span style="font-size:0.8em;opacity:0.8;">' + ds.map(escapeHtml).join('<br>') + '</span>';
                    }
                    temp.dnssec = dnssecHtml;
                }

                // 备注（remarks）
                if (Array.isArray(rdap.remarks) && rdap.remarks.length) {
                    const remarks = rdap.remarks.map(function (r) {
                        return Array.isArray(r.description) ? r.description.join('\n') : (r.description || '');
                    }).filter(Boolean).join('\n');
                    if (remarks) temp.remarks = escapeHtml(remarks).replace(/\n/g, '<br>');
                }

                // WHOIS 服务器（port43）
                if (rdap.port43) temp.whoisServer = escapeHtml(rdap.port43);

                // 实体：注册商、注册人、技术、管理、滥用（含嵌套实体）
                const contacts = {};
                if (Array.isArray(rdap.entities)) {
                    function processEntity(entity, isNested) {
                        const roles = entity.roles || [];
                        const vcard = extractVcard(entity.vcardArray);
                        roles.forEach(function (role) {
                            if (!contacts[role]) {
                                contacts[role] = {
                                    vcard: vcard,
                                    handle: entity.handle || ''
                                };
                            }
                        });
                        // 递归处理嵌套实体
                        if (Array.isArray(entity.entities)) {
                            entity.entities.forEach(function (sub) {
                                processEntity(sub, true);
                            });
                        }
                    }
                    rdap.entities.forEach(function (entity) { processEntity(entity, false); });
                }

                // 注册商信息
                if (contacts.registrar) {
                    const v = contacts.registrar.vcard;
                    if (v.fn || v.org) temp.registrar = escapeHtml(v.org || v.fn);
                    if (contacts.registrar.handle) temp.ianaId = escapeHtml(contacts.registrar.handle);
                    // 注册商邮箱
                    if (v.email) temp.registrarEmail = escapeHtml(v.email);
                    // 注册商电话
                    if (v.tel) temp.registrarPhone = escapeHtml(v.tel);
                    // 注册商地址
                    if (v.address) temp.registrarAddress = escapeHtml(v.address).replace(/\n/g, '<br>');
                }

                // 注册人信息
                if (contacts.registrant) {
                    const v = contacts.registrant.vcard;
                    if (v.org || v.fn) temp.registrant = escapeHtml(v.org || v.fn);
                    if (v.email) temp.registrantEmail = escapeHtml(v.email);
                    if (v.tel) temp.registrantPhone = escapeHtml(v.tel);
                    if (v.address) temp.registrantAddress = escapeHtml(v.address).replace(/\n/g, '<br>');
                }

                // 管理联系人
                if (contacts.administrative) {
                    const v = contacts.administrative.vcard;
                    const parts = [];
                    if (v.fn || v.org) parts.push(v.fn || v.org);
                    if (v.email) parts.push(v.email);
                    if (v.tel) parts.push(v.tel);
                    if (parts.length) temp.adminContact = escapeHtml(parts.join(' / '));
                }

                // 技术联系人
                if (contacts.technical) {
                    const v = contacts.technical.vcard;
                    const parts = [];
                    if (v.fn || v.org) parts.push(v.fn || v.org);
                    if (v.email) parts.push(v.email);
                    if (v.tel) parts.push(v.tel);
                    if (parts.length) temp.techContact = escapeHtml(parts.join(' / '));
                }

                // 滥用投诉联系人
                if (contacts.abuse) {
                    const v = contacts.abuse.vcard;
                    const parts = [];
                    if (v.fn || v.org) parts.push(v.fn || v.org);
                    if (v.email) parts.push(v.email);
                    if (v.tel) parts.push(v.tel);
                    if (parts.length) temp.abuseContact = escapeHtml(parts.join(' / '));
                }

                // 按固定顺序构建显示字段
                const fieldOrder = ['domainName', 'registrar', 'ianaId', 'registrarEmail', 'registrarPhone', 'registrarAddress',
                    'registryDomainId', 'creationDate', 'expiryDate', 'updatedDate', 'rdapUpdated', 'status',
                    'nameServers', 'dnssec', 'registrant', 'registrantEmail', 'registrantPhone', 'registrantAddress',
                    'adminContact', 'techContact', 'abuseContact', 'remarks', 'whoisServer'];
                const info = {};
                fieldOrder.forEach(function (key) {
                    if (temp[key]) info[key] = temp[key];
                });

                const registrationPeriod = calculatePeriod(rawDates.creationDate, 'registrationPeriod');
                const remainingTime = calculatePeriod(rawDates.expiryDate, 'remainingTime');

                // 生成重要信息 HTML
                let importantInfoHtml = `
                    <div id="importantInfoCard" class="glass-inner rounded-3xl p-4 sm:p-6 mb-4 fade-in">
                        <div class="flex justify-between items-center mb-5">
                            <h3 class="text-base sm:text-lg font-bold flex items-center gap-2.5" style="color: var(--text-primary);">
                                <span class="w-1.5 h-6 rounded-full" style="background: var(--accent-gradient);"></span>
                                <span data-i18n="whoisOverview">WHOIS 信息概览</span>
                            </h3>
                            <div class="flex items-center gap-2.5">
                                <span class="source-badge ${isRdap ? 'source-rdap' : 'source-whois'}" title="${sourceServer}">
                                    ${isRdap ? 'RDAP' : 'WHOIS'}
                                </span>
                                <button id="saveScreenshot" class="icon-btn p-2.5" title="截图">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="source-info-row">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span data-i18n="${isRdap ? 'rdapServerLabel' : 'whoisServerLabel'}">${isRdap ? 'RDAP 服务器' : 'WHOIS 服务器'}</span>：
                            <span class="source-server" title="${sourceServer}">${sourceServer}</span>
                        </div>
                        <div class="space-y-1">
                            ${Object.entries(info).map(([key, value]) => `
                                <div class="field-row">
                                    <span class="field-label" data-i18n="${key}">${getLabel(key)}</span>
                                    <span class="field-value">
                                        ${value}
                                        ${key === 'domainName' ? registrationPeriod : ''}
                                        ${key === 'expiryDate' ? remainingTime : ''}
                                    </span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;

                // 生成原始 RDAP JSON 信息 HTML
                const rawJson = escapeHtml(JSON.stringify(rdap, null, 2));
                let originalInfoHtml = `
                    <div class="glass-inner rounded-3xl p-4 sm:p-6 fade-in">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-base sm:text-lg font-bold flex items-center gap-2.5" style="color: var(--text-primary);">
                                <span class="w-1.5 h-6 rounded-full" style="background: var(--accent-gradient);"></span>
                                <span data-i18n="originalRdap">RDAP 原始信息</span>
                            </h3>
                            <button id="copyOriginalInfo" class="icon-btn p-2.5" data-clipboard-target="#originalInfoContent" title="复制">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                        </div>
                        <div class="json-block scroll-area" id="originalInfoContent">${rawJson}</div>
                    </div>
                `;

                return { importantInfoHtml, originalInfoHtml };
            }

            // 计算时间跨度（注册至今 / 距过期剩余）
            function calculatePeriod(dateString, type) {
                if (!dateString) return '';

                const date = new Date(dateString);
                if (isNaN(date.getTime())) return '';
                const currentDate = new Date();
                const diffTime = Math.abs(type === 'remainingTime' ? date - currentDate : currentDate - date);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                let time, unit;
                if (diffDays >= 365) {
                    time = Math.floor(diffDays / 365);
                    unit = 'years';
                } else if (diffDays >= 30) {
                    time = Math.floor(diffDays / 30);
                    unit = 'months';
                } else {
                    time = diffDays;
                    unit = 'days';
                }

                return `<span class="time-badge ${type === 'remainingTime' ? 'green' : 'blue'}" data-i18n="${type}" data-time="${time}" data-unit="${unit}"></span>`;
            }

            function updateSearchHistory(domain) {
                let history = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
                if (domain && domain.trim()) {
                    history = [domain, ...history.filter(item => item !== domain)].slice(0, MAX_HISTORY);
                    localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
                }
                renderHistory(history);
            }

            function renderHistory(history) {
                const historyHtml = history.length
                    ? history.map(item => `
                        <li class="history-item">
                            <span class="text-sm block mb-2 font-medium" style="color: var(--text-secondary);">${escapeHtml(item)}</span>
                            <button class="search-again link-btn" data-domain="${escapeHtml(item)}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span data-i18n="searchAgain">再次查询</span>
                            </button>
                        </li>
                    `).join('')
                    : `<li class="text-sm text-center py-8" style="color: var(--text-muted);" data-i18n="noHistory">暂无历史查询记录</li>`;
                historyList.innerHTML = historyHtml;
            }

            function updateButtonState(isLoading) {
                const submitText = submitBtn.querySelector('span');
                const spinner = submitBtn.querySelector('svg');
                submitBtn.disabled = isLoading;
                if (isLoading) {
                    submitText.textContent = i18next.t('searching', { defaultValue: '查询中' });
                    spinner.classList.remove('hidden');
                } else {
                    submitText.textContent = i18next.t('search', { defaultValue: '查询' });
                    spinner.classList.add('hidden');
                }
            }

            function updateUrl(domain) {
                if (domain) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('domain', domain);
                    history.pushState(null, '', url.toString());
                }
            }

            function showError(message) {
                importantInfo.innerHTML = '';
                originalInfo.innerHTML = `
                    <div class="glass-inner rounded-3xl p-6 sm:p-10 text-center fade-in">
                        <div class="w-16 h-16 mx-auto mb-5 rounded-full flex items-center justify-center" style="background: rgba(239, 68, 68, 0.15);">
                            <svg class="h-8 w-8" style="color: var(--danger);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">${message}</p>
                    </div>
                `;
                resultDiv.classList.remove('hidden');
            }

            function showToast(message, duration = 3000) {
                const toast = $('#toast');
                toast.textContent = i18next.t(message);
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), duration);
            }

            function initClipboard() {
                // 使用原生 navigator.clipboard API，无需第三方库
                document.addEventListener('click', function (e) {
                    const btn = e.target.closest('#copyOriginalInfo');
                    if (!btn) return;
                    const content = $('#originalInfoContent');
                    if (!content) return;
                    const text = content.textContent;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(
                            () => showToast('copiedToClipboard'),
                            () => fallbackCopy(text)
                        );
                    } else {
                        fallbackCopy(text);
                    }
                });
            }

            // 降级方案：使用临时 textarea + execCommand
            function fallbackCopy(text) {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try {
                    document.execCommand('copy');
                    showToast('copiedToClipboard');
                } catch (err) {
                    showToast('copyFailed');
                }
                document.body.removeChild(ta);
            }

            function handleSaveScreenshot() {
                const element = document.getElementById('importantInfoCard');
                const currentDomain = window.location.hostname;
                const currentQueryDomain = domainInput.value.trim();
                const saveBtn = $('#saveScreenshot');

                // 临时隐藏截图按钮
                saveBtn.style.display = 'none';

                domtoimage.toPng(element, {
                    quality: 1,
                    bgcolor: '#ffffff'
                })
                    .then(function (dataUrl) {
                        const img = new Image();
                        img.onload = function () {
                            const canvas = document.createElement('canvas');
                            canvas.width = img.width;
                            canvas.height = img.height;
                            const ctx = canvas.getContext('2d');

                            ctx.drawImage(img, 0, 0);

                            ctx.font = '12px Arial';
                            ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
                            const watermarkText = '© ' + new Date().getFullYear() + ' ' + currentDomain;
                            const textWidth = ctx.measureText(watermarkText).width;
                            ctx.fillText(watermarkText, canvas.width - textWidth - 10, canvas.height - 10);

                            const link = document.createElement('a');
                            link.download = `whois_${currentQueryDomain}.png`;
                            link.href = canvas.toDataURL('image/png');
                            link.click();

                            saveBtn.style.display = '';
                        };
                        img.src = dataUrl;
                    })
                    .catch(function (error) {
                        console.error('截图失败:', error);
                        showToast('screenshotFailed');
                        saveBtn.style.display = '';
                    });
            }

            function checkUrlForDomain() {
                const params = new URLSearchParams(window.location.search);
                const domain = params.get('domain');
                if (domain) {
                    domainInput.value = domain;
                    performWhoisLookup(domain);
                }
            }

            function toggleClearButton() {
                clearInputBtn.classList.toggle('hidden', !domainInput.value);
            }

            function clearInput() {
                domainInput.value = '';
                domainInput.focus();
                toggleClearButton();
            }

            function handleGlobalKeydown(e) {
                if (e.key === 'Delete') {
                    e.preventDefault();
                    clearInput();
                }
            }

            function initI18n() {
                const userLang = navigator.language || navigator.userLanguage;
                const defaultLang = ['zh-CN', 'zh-TW', 'en', 'ru', 'es'].includes(userLang) ? userLang : 'zh-CN';

                i18next.init({
                    lng: defaultLang,
                    resources: {
                        'zh-CN': {
                            translation: {
                                'title': 'WHOIS 查询',
                                'inputPlaceholder': '请输入IP或者域名',
                                'search': '查询',
                                'searching': '查询中',
                                'recentSearches': '最近查询',
                                'noHistory': '暂无历史查询记录',
                                'apiDocsTitle': 'API 使用说明',
                                'apiDocsDesc': '通过 API 获取原始 WHOIS/RDAP 数据，查询逻辑与网页一致，仅返回原始数据。',
                                'apiEndpoint': '接口地址',
                                'apiDomainExample': 'example.com',
                                'apiParams': '请求参数',
                                'apiParamDomain': '必填，要查询的域名或 IP',
                                'apiParamApi': '必填，固定值 domain，启用 API 模式',
                                'apiResponse': '响应格式',
                                'apiRespRdap': 'RDAP 命中：HTTP 200，Content-Type: application/rdap+json，直接返回原始 RDAP JSON',
                                'apiRespWhois': 'WHOIS 命中：HTTP 200，Content-Type: text/plain，直接返回原始 WHOIS 文本',
                                'apiRespUnregistered': '未注册：HTTP 404，Content-Type: text/plain，返回 Domain Not Registered',
                                'apiRespFailed': '查询失败：HTTP 502，Content-Type: text/plain，返回错误信息',
                                'apiRateLimit': '限流规则',
                                'apiRateLimitDesc': '同一 IP 60 秒内最多查询 30 次，超限返回 HTTP 429（含 Retry-After 头，纯文本响应）。',
                                'searchAgain': '再次查询',
                                'whoisOverview': 'WHOIS 信息概览',
                                'ipOverview': 'IP 信息概览',
                                'ipAddress': 'IP 地址',
                                'networkName': '网络名称',
                                'ipCountry': '国家/地区',
                                'ipRange': 'IP 范围',
                                'ipCidr': 'CIDR',
                                'ipVersion': 'IP 版本',
                                'ipType': '分配类型',
                                'ipDescription': '描述',
                                'ipContacts': '联系信息',
                                'ipWhoisServer': 'WHOIS 服务器',
                                'abuseContact': '滥用投诉',
                                'techContact': '技术联系',
                                'adminContact': '管理联系',
                                'copiedToClipboard': '原始信息已复制到剪贴板',
                                'copyFailed': '复制失败，请手动复制',
                                'screenshotFailed': '截图失败，请稍后重试',
                                'domainName': '域名',
                                'registrar': '注册商',
                                'updatedDate': '更新日',
                                'creationDate': '注册日',
                                'expiryDate': '过期日',
                                'remainingTime': '剩余 {{time}} {{unit}}',
                                'registrationPeriod': '{{time}} {{unit}}',
                                'years': '年',
                                'months': '个月',
                                'days': '天',
                                'status': '状态',
                                'nameServers': '域名服务器',
                                'dnssec': 'DNSSEC',
                                'registrant': '注册人',
                                'registrantEmail': '注册人邮箱',
                                'registrantPhone': '注册人电话',
                                'registrantAddress': '注册人地址',
                                'registrarEmail': '注册商邮箱',
                                'registrarPhone': '注册商电话',
                                'registrarAddress': '注册商地址',
                                'rdapUpdated': 'RDAP 数据更新',
                                'remarks': '备注',
                                'whoisServer': 'WHOIS 服务器',
                                'error': '错误: {{message}}',
                                'requestFailed': '请求失败: {{status}}',
                                'originalRdap': 'RDAP 原始信息',
                                'noRdapService': '查询失败，请通过 ICANN 官方查询',
                                'whoisUnreachable': 'WHOIS 服务器无法到达（连接超时，可能被防火墙拦截）',
                                'whoisUnavailable': 'WHOIS 服务器不可用（连接被拒绝，服务未运行）',
                                'whoisDnsFailed': 'WHOIS 服务器域名解析失败',
                                'whoisReadTimeout': 'WHOIS 服务器读取超时（连接成功但无响应）',
                                'whoisEmpty': 'WHOIS 服务器返回空响应',
                                'rdapWhoisBothFailed': 'RDAP 与 WHOIS 查询均失败',
                                'rdapFailed': 'RDAP 查询失败',
                                'noService': '无可用的 RDAP/WHOIS 查询服务',
                                'icannLookup': '前往 ICANN 查询',
                                'registryDomainId': '注册局域名 ID',
                                'ianaId': 'IANA ID',
                                'dnssecSigned': '已签名',
                                'dnssecUnsigned': '未签名',
                                'whoisSource': 'WHOIS 协议',
                                'whoisServerLabel': 'WHOIS 服务器',
                                'rdapServerLabel': 'RDAP 服务器',
                                'whoisFallbackNote': '该域名后缀暂无 RDAP 服务，已通过 WHOIS 协议获取注册信息',
                                'whoisRawText': 'WHOIS 原始信息'
                            }
                        },
                        'zh-TW': {
                            translation: {
                                'title': 'WHOIS 查詢',
                                'inputPlaceholder': '請輸入IP或者域名',
                                'search': '查詢',
                                'searching': '查詢中',
                                'recentSearches': '最近查詢',
                                'noHistory': '暫無歷史查詢記錄',
                                'apiDocsTitle': 'API 使用說明',
                                'apiDocsDesc': '透過 API 取得原始 WHOIS/RDAP 資料，查詢邏輯與網頁一致，僅返回原始資料。',
                                'apiEndpoint': '介面地址',
                                'apiDomainExample': 'example.com',
                                'apiParams': '請求參數',
                                'apiParamDomain': '必填，要查詢的域名或 IP',
                                'apiParamApi': '必填，固定值 domain，啟用 API 模式',
                                'apiResponse': '回應格式',
                                'apiRespRdap': 'RDAP 命中：HTTP 200，Content-Type: application/rdap+json，直接返回原始 RDAP JSON',
                                'apiRespWhois': 'WHOIS 命中：HTTP 200，Content-Type: text/plain，直接返回原始 WHOIS 文字',
                                'apiRespUnregistered': '未註冊：HTTP 404，Content-Type: text/plain，返回 Domain Not Registered',
                                'apiRespFailed': '查詢失敗：HTTP 502，Content-Type: text/plain，返回錯誤訊息',
                                'apiRateLimit': '限流規則',
                                'apiRateLimitDesc': '同一 IP 60 秒內最多查詢 30 次，超限返回 HTTP 429（含 Retry-After 標頭，純文字回應）。',
                                'searchAgain': '再次查詢',
                                'whoisOverview': 'WHOIS 信息概覽',
                                'ipOverview': 'IP 資訊概覽',
                                'ipAddress': 'IP 位址',
                                'networkName': '網路名稱',
                                'ipCountry': '國家/地區',
                                'ipRange': 'IP 範圍',
                                'ipCidr': 'CIDR',
                                'ipVersion': 'IP 版本',
                                'ipType': '分配類型',
                                'ipDescription': '描述',
                                'ipContacts': '聯絡資訊',
                                'ipWhoisServer': 'WHOIS 伺服器',
                                'abuseContact': '濫用投訴',
                                'techContact': '技術聯絡',
                                'adminContact': '管理聯絡',
                                'copiedToClipboard': '原始信息已複製到剪貼板',
                                'copyFailed': '複製失敗，請手動複製',
                                'screenshotFailed': '截圖失敗，請稍後重試',
                                'domainName': '域名',
                                'registrar': '註冊商',
                                'updatedDate': '更新日',
                                'creationDate': '註冊日',
                                'expiryDate': '過期日',
                                'remainingTime': '剩餘 {{time}} {{unit}}',
                                'registrationPeriod': '{{time}} {{unit}}',
                                'years': '年',
                                'months': '個月',
                                'days': '天',
                                'status': '狀態',
                                'nameServers': '域名伺服器',
                                'dnssec': 'DNSSEC',
                                'registrant': '註冊人',
                                'registrantEmail': '註冊人郵箱',
                                'registrantPhone': '註冊人電話',
                                'registrantAddress': '註冊人地址',
                                'registrarEmail': '註冊商郵箱',
                                'registrarPhone': '註冊商電話',
                                'registrarAddress': '註冊商地址',
                                'rdapUpdated': 'RDAP 資料更新',
                                'remarks': '備註',
                                'whoisServer': 'WHOIS 伺服器',
                                'error': '錯誤: {{message}}',
                                'requestFailed': '請求失敗: {{status}}',
                                'originalRdap': 'RDAP 原始資訊',
                                'noRdapService': '查詢失敗，請通過 ICANN 官方查詢',
                                'whoisUnreachable': 'WHOIS 伺服器無法到達（連線逾時，可能被防火牆攔截）',
                                'whoisUnavailable': 'WHOIS 伺服器不可用（連線被拒絕，服務未運行）',
                                'whoisDnsFailed': 'WHOIS 伺服器網域解析失敗',
                                'whoisReadTimeout': 'WHOIS 伺服器讀取逾時（連線成功但無回應）',
                                'whoisEmpty': 'WHOIS 伺服器傳回空回應',
                                'rdapWhoisBothFailed': 'RDAP 與 WHOIS 查詢均失敗',
                                'rdapFailed': 'RDAP 查詢失敗',
                                'noService': '無可用的 RDAP/WHOIS 查詢服務',
                                'icannLookup': '前往 ICANN 查詢',
                                'registryDomainId': '註冊局域名 ID',
                                'ianaId': 'IANA ID',
                                'dnssecSigned': '已簽署',
                                'dnssecUnsigned': '未簽署',
                                'whoisSource': 'WHOIS 協議',
                                'whoisServerLabel': 'WHOIS 伺服器',
                                'rdapServerLabel': 'RDAP 伺服器',
                                'whoisFallbackNote': '該域名後綴暫無 RDAP 服務，已通過 WHOIS 協議獲取註冊資訊',
                                'whoisRawText': 'WHOIS 原始資訊'
                            }
                        },
                        'en': {
                            translation: {
                                'title': 'WHOIS Lookup',
                                'inputPlaceholder': 'Enter IP or domain name',
                                'search': 'Search',
                                'searching': 'Searching',
                                'recentSearches': 'Recent Searches',
                                'noHistory': 'No search history',
                                'apiDocsTitle': 'API Usage',
                                'apiDocsDesc': 'Retrieve raw WHOIS/RDAP data via API. Query logic matches the web interface; only raw data is returned.',
                                'apiEndpoint': 'Endpoint',
                                'apiDomainExample': 'example.com',
                                'apiParams': 'Parameters',
                                'apiParamDomain': 'Required. Domain or IP to query.',
                                'apiParamApi': 'Required. Fixed value domain, enables API mode.',
                                'apiResponse': 'Response Format',
                                'apiRespRdap': 'RDAP hit: HTTP 200, Content-Type: application/rdap+json, returns raw RDAP JSON directly',
                                'apiRespWhois': 'WHOIS hit: HTTP 200, Content-Type: text/plain, returns raw WHOIS text directly',
                                'apiRespUnregistered': 'Unregistered: HTTP 404, Content-Type: text/plain, returns Domain Not Registered',
                                'apiRespFailed': 'Query failed: HTTP 502, Content-Type: text/plain, returns error message',
                                'apiRateLimit': 'Rate Limit',
                                'apiRateLimitDesc': 'Up to 30 queries per IP within 60 seconds. Exceeding returns HTTP 429 (with Retry-After header, plain-text response).',
                                'searchAgain': 'Search Again',
                                'whoisOverview': 'WHOIS Information Overview',
                                'ipOverview': 'IP Information Overview',
                                'ipAddress': 'IP Address',
                                'networkName': 'Network Name',
                                'ipCountry': 'Country/Region',
                                'ipRange': 'IP Range',
                                'ipCidr': 'CIDR',
                                'ipVersion': 'IP Version',
                                'ipType': 'Allocation Type',
                                'ipDescription': 'Description',
                                'ipContacts': 'Contact Info',
                                'ipWhoisServer': 'WHOIS Server',
                                'abuseContact': 'Abuse Contact',
                                'techContact': 'Technical Contact',
                                'adminContact': 'Admin Contact',
                                'copiedToClipboard': 'Original information copied to clipboard',
                                'copyFailed': 'Copy failed, please copy manually',
                                'screenshotFailed': 'Screenshot failed, please try again later',
                                'domainName': 'Domain Name',
                                'registrar': 'Registrar',
                                'updatedDate': 'Updated Date',
                                'creationDate': 'Creation Date',
                                'expiryDate': 'Expiry Date',
                                'remainingTime': '{{time}} {{unit}} remaining',
                                'registrationPeriod': '{{time}} {{unit}}',
                                'years': 'years',
                                'months': 'months',
                                'days': 'days',
                                'status': 'Status',
                                'nameServers': 'Name Servers',
                                'dnssec': 'DNSSEC',
                                'registrant': 'Registrant',
                                'registrantEmail': 'Registrant Email',
                                'registrantPhone': 'Registrant Phone',
                                'registrantAddress': 'Registrant Address',
                                'registrarEmail': 'Registrar Email',
                                'registrarPhone': 'Registrar Phone',
                                'registrarAddress': 'Registrar Address',
                                'rdapUpdated': 'RDAP Database Updated',
                                'remarks': 'Remarks',
                                'whoisServer': 'WHOIS Server',
                                'error': 'Error: {{message}}',
                                'requestFailed': 'Request failed: {{status}}',
                                'originalRdap': 'Original RDAP Information',
                                'noRdapService': 'Query failed, please use ICANN official lookup',
                                'whoisUnreachable': 'WHOIS server unreachable (connection timeout, possibly blocked by firewall)',
                                'whoisUnavailable': 'WHOIS server unavailable (connection refused, service not running)',
                                'whoisDnsFailed': 'WHOIS server DNS resolution failed',
                                'whoisReadTimeout': 'WHOIS server read timeout (connected but no response)',
                                'whoisEmpty': 'WHOIS server returned empty response',
                                'rdapWhoisBothFailed': 'Both RDAP and WHOIS queries failed',
                                'rdapFailed': 'RDAP query failed',
                                'noService': 'No RDAP/WHOIS service available',
                                'icannLookup': 'Go to ICANN Lookup',
                                'registryDomainId': 'Registry Domain ID',
                                'ianaId': 'IANA ID',
                                'dnssecSigned': 'Signed',
                                'dnssecUnsigned': 'Unsigned',
                                'whoisSource': 'WHOIS Protocol',
                                'whoisServerLabel': 'WHOIS Server',
                                'rdapServerLabel': 'RDAP Server',
                                'whoisFallbackNote': 'No RDAP service for this TLD, retrieved via WHOIS protocol',
                                'whoisRawText': 'WHOIS Raw Information'
                            }
                        },
                        'ru': {
                            translation: {
                                'title': 'WHOIS Поиск',
                                'inputPlaceholder': 'Введите IP или доменное имя',
                                'search': 'Поиск',
                                'searching': 'Поиск...',
                                'recentSearches': 'Недавние запросы',
                                'noHistory': 'Нет истории поиска',
                                'apiDocsTitle': 'Использование API',
                                'apiDocsDesc': 'Получайте исходные данные WHOIS/RDAP через API. Логика запросов как на сайте; возвращаются только исходные данные.',
                                'apiEndpoint': 'Адрес эндпоинта',
                                'apiDomainExample': 'example.com',
                                'apiParams': 'Параметры запроса',
                                'apiParamDomain': 'Обязательно. Домен или IP для запроса.',
                                'apiParamApi': 'Обязательно. Фиксированное значение domain, включает режим API.',
                                'apiResponse': 'Формат ответа',
                                'apiRespRdap': 'RDAP найден: HTTP 200, Content-Type: application/rdap+json, возвращает исходный RDAP JSON напрямую',
                                'apiRespWhois': 'WHOIS найден: HTTP 200, Content-Type: text/plain, возвращает исходный текст WHOIS напрямую',
                                'apiRespUnregistered': 'Не зарегистрирован: HTTP 404, Content-Type: text/plain, возвращает Domain Not Registered',
                                'apiRespFailed': 'Ошибка запроса: HTTP 502, Content-Type: text/plain, возвращает сообщение об ошибке',
                                'apiRateLimit': 'Ограничение частоты',
                                'apiRateLimitDesc': 'До 30 запросов с одного IP за 60 секунд. Превышение возвращает HTTP 429 (с заголовком Retry-After, текстовый ответ).',
                                'searchAgain': 'Искать снова',
                                'whoisOverview': 'Обзор информации WHOIS',
                                'ipOverview': 'Обзор IP-информации',
                                'ipAddress': 'IP-адрес',
                                'networkName': 'Имя сети',
                                'ipCountry': 'Страна/регион',
                                'ipRange': 'Диапазон IP',
                                'ipCidr': 'CIDR',
                                'ipVersion': 'Версия IP',
                                'ipType': 'Тип распределения',
                                'ipDescription': 'Описание',
                                'ipContacts': 'Контактная информация',
                                'ipWhoisServer': 'WHOIS-сервер',
                                'abuseContact': 'Контакт по злоупотреблениям',
                                'techContact': 'Технический контакт',
                                'adminContact': 'Административный контакт',
                                'copiedToClipboard': 'Оригинальная информация скопирована в буфер обмена',
                                'copyFailed': 'Не удалось скопировать, пожалуйста, скопируйте вручную',
                                'screenshotFailed': 'Не удалось сделать скриншот, попробуйте позже',
                                'domainName': 'Доменное имя',
                                'registrar': 'Регистратор',
                                'updatedDate': 'Дата обновления',
                                'creationDate': 'Дата создания',
                                'expiryDate': 'Дата истечения',
                                'remainingTime': 'Осталось {{time}} {{unit}}',
                                'registrationPeriod': '{{time}} {{unit}}',
                                'years': 'лет',
                                'months': 'месяцев',
                                'days': 'дней',
                                'status': 'Статус',
                                'nameServers': 'Имена серверов',
                                'dnssec': 'DNSSEC',
                                'registrant': 'Регистрант',
                                'registrantEmail': 'Email регистранта',
                                'registrantPhone': 'Телефон регистранта',
                                'registrantAddress': 'Адрес регистранта',
                                'registrarEmail': 'Email регистратора',
                                'registrarPhone': 'Телефон регистратора',
                                'registrarAddress': 'Адрес регистратора',
                                'rdapUpdated': 'Обновление БД RDAP',
                                'remarks': 'Примечания',
                                'whoisServer': 'Сервер WHOIS',
                                'error': 'Ошибка: {{message}}',
                                'requestFailed': 'Запрос не выполнен: {{status}}',
                                'originalRdap': 'Оригинальная информация RDAP',
                                'noRdapService': 'Запрос не удался, используйте официальный поиск ICANN',
                                'whoisUnreachable': 'WHOIS-сервер недоступен (тайм-аут соединения, возможно заблокирован брандмауэром)',
                                'whoisUnavailable': 'WHOIS-сервер недоступен (соединение отклонено, служба не запущена)',
                                'whoisDnsFailed': 'Ошибка DNS-разрешения WHOIS-сервера',
                                'whoisReadTimeout': 'Тайм-аут чтения WHOIS-сервера (подключено, но нет ответа)',
                                'whoisEmpty': 'WHOIS-сервер вернул пустой ответ',
                                'rdapWhoisBothFailed': 'Запросы RDAP и WHOIS завершились неудачей',
                                'rdapFailed': 'Запрос RDAP не удался',
                                'noService': 'Служба RDAP/WHOIS недоступна',
                                'icannLookup': 'Перейти к поиску ICANN',
                                'registryDomainId': 'ID домена в реестре',
                                'ianaId': 'IANA ID',
                                'dnssecSigned': 'Подписан',
                                'dnssecUnsigned': 'Не подписан',
                                'whoisSource': 'Протокол WHOIS',
                                'whoisServerLabel': 'Сервер WHOIS',
                                'rdapServerLabel': 'Сервер RDAP',
                                'whoisFallbackNote': 'Для этого TLD нет службы RDAP, данные получены через WHOIS',
                                'whoisRawText': 'Исходная информация WHOIS'
                            }
                        },
                        'es': {
                            translation: {
                                'title': 'Consulta WHOIS',
                                'inputPlaceholder': 'Ingrese IP o nombre de dominio',
                                'search': 'Buscar',
                                'searching': 'Buscando...',
                                'recentSearches': 'Búsquedas recientes',
                                'noHistory': 'No hay historial de búsqueda',
                                'apiDocsTitle': 'Uso de la API',
                                'apiDocsDesc': 'Obtén datos sin procesar de WHOIS/RDAP mediante la API. La lógica de consulta coincide con la web; solo se devuelven datos sin procesar.',
                                'apiEndpoint': 'Endpoint',
                                'apiDomainExample': 'example.com',
                                'apiParams': 'Parámetros',
                                'apiParamDomain': 'Obligatorio. Dominio o IP a consultar.',
                                'apiParamApi': 'Obligatorio. Valor fijo domain, activa el modo API.',
                                'apiResponse': 'Formato de respuesta',
                                'apiRespRdap': 'RDAP encontrado: HTTP 200, Content-Type: application/rdap+json, devuelve el JSON RDAP sin procesar',
                                'apiRespWhois': 'WHOIS encontrado: HTTP 200, Content-Type: text/plain, devuelve el texto WHOIS sin procesar',
                                'apiRespUnregistered': 'No registrado: HTTP 404, Content-Type: text/plain, devuelve Domain Not Registered',
                                'apiRespFailed': 'Consulta fallida: HTTP 502, Content-Type: text/plain, devuelve mensaje de error',
                                'apiRateLimit': 'Límite de frecuencia',
                                'apiRateLimitDesc': 'Hasta 30 consultas por IP en 60 segundos. Superarlo devuelve HTTP 429 (con cabecera Retry-After, respuesta de texto).',
                                'searchAgain': 'Buscar de nuevo',
                                'whoisOverview': 'Resumen de información WHOIS',
                                'ipOverview': 'Resumen de información IP',
                                'ipAddress': 'Dirección IP',
                                'networkName': 'Nombre de red',
                                'ipCountry': 'País/Región',
                                'ipRange': 'Rango IP',
                                'ipCidr': 'CIDR',
                                'ipVersion': 'Versión IP',
                                'ipType': 'Tipo de asignación',
                                'ipDescription': 'Descripción',
                                'ipContacts': 'Información de contacto',
                                'ipWhoisServer': 'Servidor WHOIS',
                                'abuseContact': 'Contacto de abuso',
                                'techContact': 'Contacto técnico',
                                'adminContact': 'Contacto administrativo',
                                'copiedToClipboard': 'Información original copiada al portapapeles',
                                'copyFailed': 'Error al copiar, por favor copie manualmente',
                                'screenshotFailed': 'Error al tomar captura de pantalla, intente nuevamente más tarde',
                                'domainName': 'Nombre de dominio',
                                'registrar': 'Registrador',
                                'updatedDate': 'Fecha de actualización',
                                'creationDate': 'Fecha de creación',
                                'expiryDate': 'Fecha de expiración',
                                'remainingTime': '{{time}} {{unit}} restantes',
                                'registrationPeriod': '{{time}} {{unit}}',
                                'years': 'años',
                                'months': 'meses',
                                'days': 'días',
                                'status': 'Estado',
                                'nameServers': 'Servidores de nombres',
                                'dnssec': 'DNSSEC',
                                'registrant': 'Registrante',
                                'registrantEmail': 'Email del registrante',
                                'registrantPhone': 'Teléfono del registrante',
                                'registrantAddress': 'Dirección del registrante',
                                'registrarEmail': 'Email del registrador',
                                'registrarPhone': 'Teléfono del registrador',
                                'registrarAddress': 'Dirección del registrador',
                                'rdapUpdated': 'Actualización BD RDAP',
                                'remarks': 'Observaciones',
                                'whoisServer': 'Servidor WHOIS',
                                'error': 'Error: {{message}}',
                                'requestFailed': 'Solicitud fallida: {{status}}',
                                'originalRdap': 'Información original de RDAP',
                                'noRdapService': 'Consulta fallida, use la búsqueda oficial de ICANN',
                                'whoisUnreachable': 'Servidor WHOIS inalcanzable (tiempo de espera agotado, posiblemente bloqueado por firewall)',
                                'whoisUnavailable': 'Servidor WHOIS no disponible (conexión rechazada, servicio no en ejecución)',
                                'whoisDnsFailed': 'Error de resolución DNS del servidor WHOIS',
                                'whoisReadTimeout': 'Tiempo de espera de lectura del servidor WHOIS (conectado pero sin respuesta)',
                                'whoisEmpty': 'El servidor WHOIS devolvió una respuesta vacía',
                                'rdapWhoisBothFailed': 'Las consultas RDAP y WHOIS fallaron',
                                'rdapFailed': 'Consulta RDAP fallida',
                                'noService': 'No hay servicio RDAP/WHOIS disponible',
                                'icannLookup': 'Ir a búsqueda ICANN',
                                'registryDomainId': 'ID de dominio del registro',
                                'ianaId': 'IANA ID',
                                'dnssecSigned': 'Firmado',
                                'dnssecUnsigned': 'No firmado',
                                'whoisSource': 'Protocolo WHOIS',
                                'whoisServerLabel': 'Servidor WHOIS',
                                'rdapServerLabel': 'Servidor RDAP',
                                'whoisFallbackNote': 'Sin servicio RDAP para este TLD, obtenido vía WHOIS',
                                'whoisRawText': 'Información sin procesar de WHOIS'
                            }
                        }
                    }
                }, function () {
                    updateContent();
                });
            }

            function updateContent() {
                $$('[data-i18n]').forEach(function (el) {
                    const key = el.getAttribute('data-i18n');
                    if (key === 'remainingTime' || key === 'registrationPeriod') {
                        const time = el.getAttribute('data-time');
                        const unit = el.getAttribute('data-unit');
                        el.innerHTML = i18next.t(key, { time: time, unit: i18next.t(unit) });
                    } else if (key && key.indexOf('[placeholder]') === 0) {
                        const placeholderKey = key.replace('[placeholder]', '');
                        el.setAttribute('placeholder', i18next.t(placeholderKey));
                    } else if (key) {
                        el.textContent = i18next.t(key);
                    }
                });
            }

            function handleLanguageChange() {
                i18next.changeLanguage(this.value, function () {
                    updateContent();
                    updateApiEndpointUrl();
                });
            }

            init();
        });
    </script>
</body>

</html>
