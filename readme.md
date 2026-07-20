# WHOIS / RDAP / IP 归属地 查询工具

一个轻量级的 PHP 网络信息查询工具，支持 **域名 / IPv4 / IPv6 / MAC 地址** 四类查询，并为 IP 查询自动附加**归属地信息**。域名与 IP 采用 **RDAP 优先 → WHOIS 回退 → ICANN 降级** 的三级查询策略，服务器地址统一从 IANA 根数据库动态发现；IP 归属地采用**双数据源容错**（自建服务优先，ip-api.com 回退）；MAC 地址基于本地 IEEE OUI 数据库查询厂商信息。前端为玻璃态（Glassmorphism）设计，零第三方 JS 依赖，支持 PWA 离线使用，并提供返回原始数据的 API 接口与独立的 IP 归属地 API。

## 在线演示

[https://tool.xuanlove.host/phpwhois/](https://tool.xuanlove.host/phpwhois/)

## 功能特性

### 查询能力
- **自动识别查询类型**：根据输入格式自动判定为域名、IPv4、IPv6 或 MAC 地址，无需手动切换
- **域名查询**：RDAP（HTTPS + JSON）优先 → WHOIS（TCP 43）回退 → ICANN 查询页降级
- **IP 查询**：IPv4 / IPv6 均走 ARIN RDAP，自动重定向到对应 RIR（APNIC/RIPE/LACNIC/AFRINIC）
- **IP 归属地查询**：IPv4 / IPv6 查询时自动附加归属地信息（国家/洲/省/市/邮编/经纬度/时区/ISP/组织/AS）
  - **双数据源容错**：优先调用自建服务 `https://tool.xuanlove.host/ip/`，失败回退到 `ip-api.com`
  - **动态来源显示**：归属地卡片显示真实数据源（非固定值），与实际调用的接口一致
  - 按 IP 缓存 7 天，避免重复请求
- **MAC 地址查询**：基于本地 IEEE OUI 数据库（53000+ 条记录），查询厂商名称、地址、注册类型、OUI 前缀
  - 支持 `aa:bb:cc:dd:ee:ff`、`aa-bb-cc-dd-ee-ff`、`aabb.ccdd.eeff`、`aabbccddeeff` 等多种格式
  - 严格正则匹配，避免 `abcdef123456.io` 等域名被误识别为 MAC
- **统一服务器发现**：从 [IANA 根数据库](https://www.iana.org/domains/root/db) 动态抓取每个 TLD 的 RDAP 与 WHOIS 服务器，本地缓存 7 天
- **多级后缀优先匹配**：`com.cn` 优先于 `cn`，RDAP 与 WHOIS 独立查找各自最长匹配
- **WHOIS 二次查询**：注册局响应中含「Registrar WHOIS Server」时自动追加注册商 WHOIS 查询，获取更详细的注册人信息
- **IDN 域名支持**：Punycode 转换，支持中文等国际化域名

### 信息来源显示
- 查询结果卡片右上角显示来源徽章，颜色区分四类数据源：
  - **RDAP**（蓝色）—— 域名/IP 的 RDAP 协议查询
  - **WHOIS**（灰色）—— 域名 WHOIS 协议（TCP 43）查询
  - **MAC**（绿色）—— 本地 IEEE OUI 数据库查询
  - **GEO**（紫色）—— IP 归属地查询（动态显示真实数据源）
- 服务器地址行显示具体查询的服务器域名/URL，鼠标悬停可查看完整地址
- 多语言标签自动切换（如「RDAP 服务器」/「RDAP Server」）

### 前端体验
- **玻璃态 UI**：`backdrop-filter` 模糊 + 饱和度增强，多层玻璃卡片
- **主题系统**：CSS 变量驱动的亮色/暗色模式，商业蓝灰色调，自动跟随系统或手动切换
- **多语言**：简体中文、繁体中文、英语、俄语、西班牙语（内嵌字典，零 CDN 依赖）
- **查询历史**：本地存储最近 50 条，右下角浮动按钮 + 底部抽屉
- **响应式设计**：桌面与移动端自适应布局
- **PWA 支持**：可安装到桌面，Service Worker 提供离线缓存
- **截图导出**：一键将查询结果导出为图片（dom-to-image）
- **查询后自动滚动**：查询完成后平滑滚动到「信息概览」卡片位置
- **底部页脚**：显示软件信息归属与 GitHub 仓库地址
- **XSS 防护**：所有外部数据 HTML 转义后渲染

### API 接口
- **统一入口**：与网页共用 `index.php`，通过 `?api=<查询目标>` 触发 API 模式
- **纯原始数据**：零包装，直接返回上游 RDAP JSON / WHOIS 原始文本 / MAC CSV 原始行
- **语义化状态码**：200 成功、404 未注册/未找到、429 限流、502 查询失败
- **IP 限流**：同一 IP 60 秒内最多 30 次查询，超限返回 HTTP 429（含 `Retry-After` 头）
- **AJAX 守卫**：`?domain=` 仅响应带 `X-Requested-With` 头的前端 AJAX 请求，直接浏览器访问返回 HTML 页面
- **独立 IP 归属地 API**：`/ip/` 路径提供独立的 IP 归属地查询服务（详见下文）

## 技术栈

- **前端**：原生 HTML + CSS（CSS 变量主题 + 内嵌工具类）+ 原生 JavaScript（fetch + AbortController）
  - 零第三方 JS 依赖（i18next / jQuery / clipboard.js / Tailwind CSS 均已内嵌或移除）
- **后端**：PHP（cURL + socket），建议 PHP 7.4+
- **数据源**：
  - IANA 根数据库（`https://www.iana.org/domains/root/db/<tld>`）—— 域名/IP 服务器发现
  - [IEEE OUI 数据库](https://github.com/WH-2099/macdb)（`mac.csv`）—— MAC 地址厂商查询
  - `https://tool.xuanlove.host/ip/` —— IP 归属地查询主源（自建，基于 ip-api.com）
  - `ip-api.com` 免费版 —— IP 归属地查询回退

## 文件结构

```
.
├── index.php           # 统一入口（前端页面 + API 路由，HTML/CSS/JS 全部内嵌）
├── whois.php           # 后端查询库（被 index.php / ip.php include，也可独立访问）
├── ip.php              # IP 归属地查询 API 独立入口（/ip/ 路径）
├── mac.csv             # MAC 地址厂商数据库（IEEE OUI 分配表，53000+ 条）
├── manifest.json       # PWA 配置
├── service-worker.js   # PWA 离线缓存策略
├── locales/            # 旧版语言文件（已被内嵌字典取代，保留兼容）
└── whois.json          # 旧版 TLD-WHOIS 映射表（后端已改用 IANA 动态发现，保留兼容）
```

## 查询流程

### 域名 / IP 查询

```
用户输入
   │
   ▼
自动识别类型（MAC / IP / 域名）
   │
   ├─ MAC 地址 → 查本地 IEEE OUI 数据库 → 返回厂商信息
   │
   ├─ IP 地址（IPv4/IPv6）
   │     │
   │     ▼
   │   ARIN RDAP（自动重定向到对应 RIR）
   │     │
   │     └─ 成功 → 附加 IP 归属地（双数据源）→ 返回结构化数据
   │
   └─ 域名
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

### IP 归属地查询（双数据源）

```
查询 IP（IPv4/IPv6）
       │
       ▼
  检查本地缓存（ip-geo-cache.json，TTL 7 天）
       │
       ├─ 命中缓存 → 直接返回（含 source 字段标识原始数据源）
       │
       └─ 未命中
           │
           ▼
     ① 优先调用自建服务 https://tool.xuanlove.host/ip/?ip=<ip>
           │
           ├─ 成功 → 规范化字段（province→region、latitude→lat、asn 数字→"ASxxxx"）
           │         返回 source = "tool.xuanlove.host/ip/"
           │
           └─ 失败 → ② 回退到 ip-api.com
                      │
                      ├─ 成功 → 返回 source = "ip-api.com"
                      └─ 失败 → 返回 null（不影响 RDAP 主流程）
```

### MAC 地址查询

```
用户输入 MAC 地址（任意分隔格式）
       │
       ▼
  严格正则校验（4 种标准格式）+ 规范化为 12 位十六进制
       │
       ▼
  取前 6 位 OUI 前缀
       │
       ▼
  查本地 mac.csv 索引（带文件缓存，首次构建后持久化）
       │
       ├─ 命中 → 返回厂商名称 / 地址 / 注册类型（MA-L/MA-M/MA-S/IAB/CID）
       └─ 未命中 → 返回 MAC Not Found
```

## API 接口

### 主查询 API（`?api=`）

```
GET https://tool.xuanlove.host/phpwhois/?api=<查询目标>
```

| 参数 | 必填 | 说明 |
| --- | --- | --- |
| `api` | 是 | 要查询的域名（如 `example.com`）、IP（如 `8.8.8.8`）或 MAC 地址（如 `00:00:00:00:00:00`），同时启用 API 模式 |

**响应格式**：API 只返回原始数据，零包装：

| 场景 | HTTP 状态码 | Content-Type | 响应体 |
| --- | --- | --- | --- |
| 域名 RDAP 命中 | 200 | `application/rdap+json` | 原始 RDAP JSON |
| 域名 WHOIS 命中 | 200 | `text/plain` | 原始 WHOIS 文本 |
| MAC 命中 | 200 | `text/plain` | CSV 原始行（`registry,prefix,org_name,address`） |
| 域名未注册 | 404 | `text/plain` | `Domain Not Registered` |
| MAC 未找到 | 404 | `text/plain` | `MAC Not Found` |
| 查询失败 | 502 | `text/plain` | 错误信息 |
| 超出限流 | 429 | `text/plain` | `Rate limit exceeded`（含 `Retry-After` 头） |

**示例**：

```bash
# 查询已注册域名（RDAP 命中）
curl "https://tool.xuanlove.host/phpwhois/?api=example.com"

# 查询未注册域名
curl -i "https://tool.xuanlove.host/phpwhois/?api=this-domain-not-registered-xyz.com"
# HTTP/1.1 404 Not Found
# Content-Type: text/plain; charset=utf-8
# Domain Not Registered

# 查询 MAC 地址厂商
curl "https://tool.xuanlove.host/phpwhois/?api=00:00:00:00:00:00"
# MA-L,000000,XEROX CORPORATION,M/S 105-50C WEBSTER NY US 14580

# 查询 IPv6 地址
curl "https://tool.xuanlove.host/phpwhois/?api=2001:4860:4860::8888"
```

**限流规则**：同一 IP 60 秒内最多查询 30 次，超出返回 HTTP 429（含 `Retry-After` 头，纯文本响应）。

> **注**：API 模式返回纯原始数据，不包含归属地信息。归属地仅通过 Web 界面或独立的 `/ip/` API 提供。

### IP 归属地查询 API（`/ip/`）

独立的 IP 归属地查询接口，CORS 全开（`Access-Control-Allow-Origin: *`），支持任意客户端调用。

**请求方式**：

```
GET  https://tool.xuanlove.host/ip/                  # 查询调用者公网 IP
GET  https://tool.xuanlove.host/ip/?ip=8.8.8.8       # 查询指定 IP
POST https://tool.xuanlove.host/ip/                  # JSON body 查询指定 IP
```

**调用示例**：

```bash
# 1. 查询本机公网 IP 归属地
curl https://tool.xuanlove.host/ip/
wget -qO- https://tool.xuanlove.host/ip/

# 2. 查询指定 IP（GET 参数）
curl "https://tool.xuanlove.host/ip/?ip=8.8.8.8"

# 3. 查询指定 IP（POST JSON body）
curl -X POST https://tool.xuanlove.host/ip/ \
     -H "Content-Type: application/json" \
     -d '{"ip": "54.255.104.99"}'
```

**成功响应**（HTTP 200）：

```json
{
    "status": "success",
    "query": "8.8.8.8",
    "geolocation": {
        "source": "ip-api.com",
        "country": "美国",
        "country_code": "US",
        "region": "VA",
        "region_name": "弗吉尼亚州",
        "city": "Ashburn",
        "zip": "20149",
        "lat": 39.03,
        "lon": -77.5,
        "timezone": "America/New_York",
        "isp": "Google LLC",
        "org": "Google Public DNS",
        "as": "AS15169 Google LLC",
        "asname": "GOOGLE",
        "continent": "北美洲"
    }
}
```

> `source` 字段标识真实数据源（`tool.xuanlove.host/ip/` 或 `ip-api.com`），与归属地卡片显示一致。

**失败响应**：

| 场景 | HTTP 状态码 | 响应体 |
| --- | --- | --- |
| 无效 IP | 400 | `{"status":"error","message":"Invalid IP address","query":"..."}` |
| 查询失败 | 502 | `{"status":"error","message":"Geolocation query failed","query":"..."}` |

**与主查询的关系**：
- 主查询入口 `?domain=<ip>` 在 Web 界面查询 IPv4/IPv6 时会**自动附加** `geolocation` 字段
- 双数据源策略：优先调用 `https://tool.xuanlove.host/ip/`，失败回退到 `ip-api.com`
- `ip.php` 作为 `/ip/` 路径入口被主源调用，内部直接调用 ip-api.com，**避免递归**

## 安装

1. 克隆仓库（含 `mac.csv` 数据库）：
   ```
   git clone https://github.com/xuanlove/php-whois.git
   ```

2. 将文件放置在 Web 服务器目录中。

3. 确保 Web 服务器支持 PHP（建议 PHP 7.4+，需启用 cURL 与 intl 扩展）。

4. 访问 `index.php` 即可使用。

> **网络要求**：服务器需能访问 `iana.org`（用于动态发现服务器）与各 RDAP/WHOIS 服务器。首次查询某 TLD 时会抓取 IANA 详情页并缓存到 `servers-cache.json`，后续 7 天内直接读缓存。
>
> **MAC 数据库**：`mac.csv` 随仓库分发，首次 MAC 查询时会构建索引并缓存到 `mac-index.cache`，后续查询直接读缓存（若 `mac.csv` 更新则自动重建索引）。
>
> **IP 归属地缓存**：归属地查询结果按 IP 缓存到 `ip-geo-cache.json`，TTL 7 天。

## 使用方法

### 网页查询

1. 在输入框输入查询目标：
   - 域名（如 `example.com`）
   - IPv4 地址（如 `8.8.8.8`）
   - IPv6 地址（如 `2001:4860:4860::8888`）
   - MAC 地址（如 `00:00:00:00:00:00`，支持多种分隔格式）
2. 点击「查询」按钮或按回车键，系统自动识别类型并查询。
3. 查看概览卡中的结构化字段：
   - 域名：注册商、注册/过期/更新日期、状态、DNS、DNSSEC 等
   - IP：网络名称、国家、CIDR、IP 范围、联系人等 + **归属地卡片**（国家/洲/省/市/经纬度/ISP/AS）
   - MAC：厂商名称、地址、注册类型、OUI 前缀
4. 卡片右上角的徽章显示信息来源（RDAP / WHOIS / MAC / GEO），下方显示数据源地址。
5. 点击「原始信息」查看完整 RDAP JSON / WHOIS 原始文本 / MAC CSV 原始行。
6. 点击右下角浮动按钮打开历史抽屉，点击记录可快速重查。
7. 顶栏切换语言（5 种）与主题（亮/暗），偏好自动记忆。
8. 点击截图按钮可将当前查询结果导出为图片。

### API 调用

- 主查询：见 [主查询 API](#主查询-apiapi) 章节，通过 `?api=<查询目标>` 获取原始数据
- IP 归属地：见 [IP 归属地查询 API](#ip-归属地查询-apiip) 章节，通过 `/ip/` 路径调用

## 伪静态规则（Nginx）

```
# IP 归属地查询 API（/ip/ → ip.php）
location = /ip/ {
    try_files $uri /ip.php;
}
location = /ip {
    try_files $uri /ip.php;
}

# 主应用（WHOIS/RDAP/MAC 查询 + 前端页面）
location / {
    try_files $uri $uri/ /index.php;
}
```

## 贡献

欢迎贡献！请随时提交 pull requests 或创建 issues 来改进这个项目。

## 许可证

本项目采用 MIT 许可证。详情见 [LICENSE](LICENSE) 文件。
