# 去水印解析 - 无水印短视频解析工具

> **去水印解析** 是一款面向网页端使用的短视频无水印解析工具。输入分享链接后，可快速获取视频信息和无水印媒体地址。

本项目是在开源项目基础上的二次开发版本，当前产品名称、网页界面和功能实现已按本项目需求重新整理。

## 项目地址

- GitHub：<https://github.com/yuchen0x1/qushuiyin-jiexi>
- Gitee：<https://gitee.com/yuchen0x1/qushuiyin-jiexi>

## 项目来源与二次开发说明

- 原项目：[`5ime/video_spider`](https://github.com/5ime/video_spider)
- 原项目地址：<https://github.com/5ime/video_spider>
- 原项目分支：`main`
- 原项目许可证：`AGPL-3.0`
- 本项目主要改动：重做网页端视觉和交互、补齐平台解析器、增加版本化 API、完善批量解析与历史记录，并补充请求限流、媒体代理和输入校验等安全处理。

本项目保留原项目的版权和许可证要求。进行发布、部署或再次分发时，请同时遵守 [AGPL-3.0](LICENSE) 及原项目的相关声明。

## 支持平台

> ✔️ 完全支持无水印

| 平台 | 状态 | 平台 | 状态 | 平台 | 状态 |
| --- | --- | --- | --- | --- | --- |
| **抖音** | ✔️ | **快手** | ✔️ | **B站** | ✔️ |
| **小红书** | ✔️ | **皮皮虾** | ✔️ | **微视** | ✔️ |
| **最右** | ✔️ | **皮皮搞笑** | ✔️ | **视频号** | ✔️ |
| **西瓜视频** | ✔️ | **微博** | ⭕ | | |

## 环境要求

- PHP 8.1+
- PHP 扩展：`curl`、`json`
- Composer
- Node.js 18+（抖音解析需要，用于生成 a_bogus 签名）

## 快速开始

1. 安装依赖：

   ```bash
   composer install
   ```

2. 启动服务（推荐使用 Composer 脚本，自动带上路由与抖音签名服务）：

   ```bash
   composer serve:full
   ```

   或仅启动网页/API（不含抖音 a_bogus 常驻签名服务，此时抖音解析会按需临时调用 Node）：

   ```bash
   composer serve
   ```

   底层命令等价于：`php -S localhost:8000 -t public public/router.php`

3. 配置环境变量（可选）：

   复制 `.env.example` 为 `.env`，然后按需修改：

   ```env
   APP_NAME=VideoSpider
   APP_DEBUG=false
   APP_CORS_ALLOW_ORIGIN=*

   RATE_LIMIT_ENABLED=true
   RATE_LIMIT_MAX_REQUESTS=60
   RATE_LIMIT_TIME_WINDOW=60

   CURL_CONNECT_TIMEOUT=5
   CURL_TIMEOUT=10
   CURL_MAX_RETRIES=3

   # 抖音解析依赖 Node.js 生成 a_bogus 签名
   DOUYIN_NODE_BIN=node
   # DOUYIN_A_BOGUS_SCRIPT=自定义签名脚本路径

   # 微博解析需要有效 Cookie
   WEIBO_COOKIE=''
   ```

4. 打开 `http://localhost:8000` 使用网页版，或直接调用下方 API。

## 网页版

浏览器直接访问站点根路径即可使用网页版解析工具：

- 粘贴视频分享链接后自动提取其中的 URL
- 解析后展示标题、作者、点赞数、发布时间
- 内置视频播放、下载、复制链接和新窗口打开
- 支持剪贴板粘贴按钮与回车快捷解析
- 支持单个 / 批量解析，批量解析可停止、失败后单条重试
- 最近解析记录保存在浏览器本地，可一键重新解析或清空
- 支持复制无水印链接与标题、平台、链接组合文案

## API 使用

服务提供版本化接口 `/api/v1`（旧版根路径接口保持向后兼容）。交互式文档位于 `public/openapi.yaml`（OpenAPI 3.0）。

| 接口 | 方法 | 说明 |
| --- | --- | --- |
| `/api/v1/health` | GET | 健康检查（始终公开） |
| `/api/v1/platforms` | GET | 支持的平台 key 列表 |
| `/api/v1/parse?url=...` | GET | 解析链接 |
| `/api/v1/parse` | POST | 解析链接（表单或 JSON body：`{"url":"..."}`） |

### 可选鉴权

默认关闭。如需开启，在 `.env` 设置 `API_KEY_ENABLED=true` 并配置 `API_KEY`，之后除 `/api/v1/health` 外，所有接口需携带请求头：

```bash
X-API-Key: <your_api_key>
# 或
Authorization: Bearer <your_api_key>
```

生成密钥建议：`openssl rand -hex 24`

### 请求方式

支持 **GET** 和 **POST**，推荐使用 **POST**。

```bash
curl -X POST "http://localhost:8000" \
     -H "Content-Type: application/x-www-form-urlencoded" \
     -d "url=<video_url>"
```

GET 请求：

```bash
curl -G "http://localhost:8000" \
     --data-urlencode "url=<encoded_video_url>"
```

### 返回字段

| 字段名 | 说明 | 字段名 | 说明 |
| --- | --- | --- | --- |
| **author** | 视频作者 | **avatar** | 作者头像 |
| **like** | 视频点赞量 | **time** | 视频发布时间 |
| **title** | 视频标题 | **cover** | 视频封面 |
| **url** | 无水印视频链接 | **uid** | 作者 ID |
| **music** | 背景音乐信息（部分平台） | **code** | 状态码 |

### 错误码

- `400` - 参数错误（URL 格式无效、不支持的平台）
- `405` - 不支持的请求方法
- `422` - 参数验证失败
- `429` - 请求过于频繁
- `500` - 服务器内部错误

## 速率限制

默认限制为 **60 次请求 / 60 秒**（基于 IP）。可通过 `.env` 中的 `RATE_LIMIT_MAX_REQUESTS`、`RATE_LIMIT_TIME_WINDOW` 调整，或将 `RATE_LIMIT_ENABLED=false` 关闭。

## 常见问题

### 抖音提示“签名生成失败”？

确认服务器已安装 Node.js 18+，且 `node` 命令在 PATH 中可用；如安装路径特殊，可在 `.env` 中配置 `DOUYIN_NODE_BIN`。

### 微博解析失败？

微博必须配置有效的 `WEIBO_COOKIE`，否则会提示“请先设置微博 cookie”。

### 平台接口失效怎么办？

短视频平台的接口会不定期变更。如果解析失败，请先确认链接仍有效；若持续失败，可在 [Issues](https://github.com/yuchen0x1/qushuiyin-jiexi/issues) 反馈。

## 免责声明

本项目仅供个人学习和研究使用。如果涉及侵犯任何个人或团体的权益，请立即联系我们，我们将尽快处理并删除相关数据。



