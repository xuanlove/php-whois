# WHOIS / RDAP 查询工具

一个轻量级的 PHP 域名与 IP 信息查询工具，采用 **RDAP 优先 → WHOIS 回退 → ICANN 降级** 的三级查询策略，服务器地址统一从 IANA 根数据库动态发现。前端为玻璃态（Glassmorphism）设计，零第三方 JS 依赖，支持 PWA 离线使用。

## 在线演示

[https://whois.rw2.cc/](https://whois.rw2.cc/)

## 功能特性

### 查询能力
- **三级查询优先级**：RDAP（HTTPS + JSON）优先 → WHOIS（TCP 43）回退 → ICANN 查询页降级
- **统一服务器发现**：从 [IANA 根数据库](https://www.iana.org/domains/root/db) 动态抓取每个 TLD 的 RDAP 与 WHOIS 服务器，本地缓存 7 天
- **多级后缀优先匹配**：`com.cn` 优先于 `cn`，RDAP 与 WHOIS 独立查找各自最长匹配
- **WHOIS 二次查询**：注册局响应中含「Registrar WHOIS Server」时自动追加注册商 WHOIS 查询，获取更详细的注册人信息
- **IP 地址查询**：走 ARIN RDAP，自动重定向到对应 RIR
- **IDN 域名支持**：Punycode 转换，支持中文等国际化域名
- **ccTLD RDAP 补充**：IANA 未列出但实际提供 RDAP 的 `.cn` / `.jp` / `.kr` / `.ru` 等通过补充列表优先走 RDAP

### 信息来源显示
- 查询结果卡片右上角显示来源徽章：**RDAP**（蓝色）或 **WHOIS**（灰色）
- 服务器地址行显示具体查询的服务器域名/URL，鼠标悬停可查看完整地址
- 多语言标签自动切换（如「RDAP 服务器」/「RDAP Server」）

### 前端体验
- **玻璃态 UI**：`backdrop-filter` 模糊 + 饱和度增强，多层玻璃卡片
- **主题系统**：CSS 变量驱动的亮色/暗色模式，商业蓝灰色调，自动跟随系统或手动切换
- **多语言**：简体中文、繁体中文、英语、俄语、西班牙语（内嵌字典，零 CDN 依赖）
- **查询历史**：本地存储最近 50 条，桌面侧边栏 + 移动端底部抽屉
- **响应式设计**：桌面与移动端自适应布局
- **PWA 支持**：可安装到桌面，Service Worker 提供离线缓存
- **截图导出**：一键将查询结果导出为图片
- **XSS 防护**：所有外部数据 HTML 转义后渲染

## 技术栈

- **前端**：原生 HTML + CSS（CSS 变量主题 + 内嵌工具类）+ 原生 JavaScript（fetch + AbortController）
  - 零第三方 JS 依赖（i18next / jQuery / clipboard.js / Tailwind CSS 均已内嵌或移除）
- **后端**：PHP（cURL + socket），建议 PHP 7.4+
- **数据源**：IANA 根数据库（`https://www.iana.org/domains/root/db/<tld>`）

## 文件结构

```
.
├── index.html          # 前端（HTML + CSS + JS 全部内嵌）
├── whois.php           # 后端查询接口
├── manifest.json       # PWA 配置
├── service-worker.js   # PWA 离线缓存策略
├── locales/            # 旧版语言文件（已被内嵌字典取代，保留兼容）
└── whois.json          # 旧版 TLD-WHOIS 映射表（后端已改用 IANA 动态发现，保留兼容）
```

## 查询流程

```
用户输入域名/IP
       │
       ▼
  规范化域名（剥离协议、Punycode 转换）
       │
       ▼
  从 IANA 根数据库查找 RDAP + WHOIS 服务器（多级后缀匹配）
       │
       ├─① RDAP 优先查询（HTTPS + JSON）
       │     ├─ 成功 → 返回结构化数据 + RDAP 服务器地址
       │     ├─ 404（域名未注册）→ 返回未注册结果（不回退）
       │     └─ 其它失败 → 继续 WHOIS 回退
       │
       ├─② WHOIS 回退查询（TCP 43）
       │     ├─ 成功 → 返回原始文本 + WHOIS 服务器地址
       │     │       （含注册局→注册商二次查询）
       │     └─ 失败 → 继续 ICANN 降级
       │
       └─③ ICANN 查询页降级
             └─ 返回 lookup.icann.org 链接
```

## 安装

1. 克隆仓库：
   ```
   git clone https://github.com/xuanlove/php-whois.git
   ```

2. 将文件放置在 Web 服务器目录中。

3. 确保 Web 服务器支持 PHP（建议 PHP 7.4+，需启用 cURL 与 intl 扩展）。

4. 访问 `index.html` 即可使用。

> 服务器需能访问 `iana.org`（用于动态发现服务器）与各 RDAP/WHOIS 服务器。首次查询某 TLD 时会抓取 IANA 详情页并缓存到 `servers-cache.json`，后续 7 天内直接读缓存。

## 使用方法

1. 在输入框输入域名（如 `example.com`）或 IP 地址（如 `8.8.8.8`）。
2. 点击「查询」按钮或按回车键。
3. 查看概览卡中的结构化字段（域名、注册商、注册/过期/更新日期、状态、DNS、DNSSEC 等）。
4. 卡片右上角的徽章显示信息来源（RDAP 或 WHOIS），下方显示服务器地址。
5. 点击「原始信息」查看完整 RDAP JSON 或 WHOIS 原始文本。
6. 点击侧边栏（移动端为底部抽屉）中的历史记录可快速重查。
7. 顶栏切换语言（5 种）与主题（亮/暗），偏好自动记忆。
8. 点击截图按钮可将当前查询结果导出为图片。

## 伪静态规则（Nginx）

```
location / {
    try_files $uri $uri/ /index.html;
}
```

## 贡献

欢迎贡献！请随时提交 pull requests 或创建 issues 来改进这个项目。

## 许可证

本项目采用 MIT 许可证。详情见 [LICENSE](LICENSE) 文件。

## 联系方式

如有任何问题或建议，请联系：

- 微信 / WeChat: yyy5858588
- 邮箱 / Email: tsymq@live.com
