# WHOIS 查询工具 / WHOIS Lookup Tool

## 简介 / Introduction

一个轻量级的 PHP WHOIS 查询工具，支持域名与 IP 地址的 WHOIS 信息查询。前端采用现代化简约设计，原生实现零第三方 JS 依赖，后端支持 WHOIS 重定向跟随、IDN 域名、查询缓存与请求限流。

A lightweight PHP WHOIS lookup tool supporting WHOIS queries for both domain names and IP addresses. The frontend features a modern minimalist design with zero third-party JS dependencies, while the backend supports WHOIS referral following, IDN domains, query caching, and rate limiting.

## 在线演示 / Online Demo

[https://whois.rw2.cc/](https://whois.rw2.cc/)

## 功能 / Features

- WHOIS 信息查询（域名 & IP）/ WHOIS lookup for domains and IP addresses
- 查询结果概览卡 + 可折叠原始 WHOIS / Overview card with collapsible raw WHOIS
- 注册时长与到期剩余时间标签 / Registration period and expiry countdown badges
- 查询历史记录（本地存储，最近 50 条）/ Search history (local storage, last 50 entries)
- 多语言支持（简中、繁中、英语、俄语、西班牙语）/ Multi-language (zh-CN, zh-TW, en, ru, es)
- 响应式设计（桌面侧边栏 + 移动端底部抽屉）/ Responsive (desktop sidebar + mobile bottom drawer)
- 后端 WHOIS 重定向跟随 / Backend WHOIS referral following
- IDN 域名 Punycode 转换 / IDN domain Punycode conversion
- APCu 查询结果缓存与请求限流 / APCu result caching and rate limiting
- WHOIS 数据 HTML 转义防注入 / HTML-escaped WHOIS data to prevent injection

## whois.json

TLD 与对应 WHOIS 服务器的映射表（JSON 对象）：
- **键**：顶级域名，包含 gTLD、ccTLD 及 IDN TLD
- **值**：对应的 WHOIS 服务器地址，`null` 表示无指定服务器

后端按多级后缀优先匹配（如 `com.cn`），未知后缀回退到 `whois.iana.org`。

A mapping of TLDs to their WHOIS servers (JSON object):
- **Keys**: TLDs including gTLDs, ccTLDs, and IDN TLDs
- **Values**: corresponding WHOIS server addresses, `null` if unavailable

The backend matches multi-level suffixes first (e.g. `com.cn`), falling back to `whois.iana.org` for unknown TLDs.

## 技术栈 / Tech Stack

- 前端 / Frontend: HTML, CSS（CSS 变量主题）, 原生 JavaScript（无第三方 JS 依赖）/ Vanilla JavaScript, no third-party JS dependencies
- 后端 / Backend: PHP（支持 APCu 可选优化）/ PHP (APCu optional)

## 安装 / Installation

1. 克隆仓库 / Clone the repository:
   ```
   git clone https://github.com/xuanlove/php-whois.git
   ```

2. 将文件放置在 Web 服务器目录中 / Place the files in your web server directory.

3. 确保 Web 服务器支持 PHP（建议 PHP 7.4+）/ Ensure your web server supports PHP (7.4+ recommended).

4. 访问 `index.html` 即可使用 / Access `index.html` to use the tool.

> 可选 / Optional：安装 APCu 扩展以启用查询缓存与请求限流；未安装时功能正常降级。
> Install APCu extension to enable caching and rate limiting; degrades gracefully if absent.

## 使用方法 / Usage

1. 在输入框输入域名或 IP 地址 / Enter a domain name or IP address.
2. 点击「查询」按钮或按回车键 / Click "Search" or press Enter.
3. 查看概览卡中的结构化字段（域名、注册商、日期、状态、DNS 等）/ View structured fields in the overview card.
4. 点击「展开原文」查看完整 WHOIS 原始信息 / Click "Expand" to view raw WHOIS.
5. 点击侧边栏（移动端为底部抽屉）中的历史记录可快速重查 / Click a history entry to re-query instantly.
6. 顶栏下拉切换语言，自动记忆偏好 / Switch language via the top bar dropdown, preference is remembered.

## 伪静态规则 / Pseudo-static Rules

```
location / {
    try_files $uri $uri/ /index.html;
}
```

## 贡献 / Contributing

欢迎贡献！请随时提交 pull requests 或创建 issues 来改进这个项目。

Contributions are welcome! Feel free to submit pull requests or create issues to improve this project.

## 许可证 / License

本项目采用 MIT 许可证。详情请见 [LICENSE](LICENSE) 文件。

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## 联系方式 / Contact

如有任何问题或建议，请联系：
For any questions or suggestions, please contact:

- 微信 / WeChat: yyy5858588
- 邮箱 / Email: tsymq@live.com
