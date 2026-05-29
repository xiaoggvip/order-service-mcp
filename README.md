# MCP服务 - 智慧点单系统

## 项目概述

基于 **fastmcp** 框架实现的点单服务，支持 AI 订单分析、订单创建和支付状态查询。该服务完全符合 MCP (Model Context Protocol) 协议规范，可与任何支持 MCP 的平台无缝对接。

## 快速开始

### 1. 安装依赖

```bash
cd d:\www\hc\ai\mcp
npm install
```

### 2. 启动服务

**启动模拟第三方 API 服务**（用于开发测试）

```bash
npm run mock-server
```

模拟服务将运行在 `http://localhost:8080`

**启动 MCP 服务**

```bash
npm start
```

## 支持的平台

### FastGPT 配置

**配置步骤：**

1. 登录 FastGPT 管理后台
2. 进入「工作流」→「创建工具」
3. 选择「MCP工具」类型
4. 填写配置信息：

| 配置项 | 值 |
|--------|-----|
| 名称 | 点餐助手 |
| 图标 | 选择合适的图标 |
| 鉴权类型 | 无 |
| MCP地址 | `http://localhost:3000` |

5. 点击「解析」按钮，系统会自动识别工具
6. 确认工具列表后点击「创建」

### AingDesk 配置

**配置步骤：**

1. 打开 AingDesk 应用
2. 点击右上角设置图标 → 选择「MCP服务器」
3. 点击「添加服务器」按钮
4. 填写配置信息：

| 配置项 | 值 |
|--------|-----|
| 名称 | 点单助手 |
| 描述 | 支持AI订单分析、订单创建和支付状态查询 |
| 类型 | `Stdio` |
| 程序类型 | `自定义` |
| 命令 | `node` |
| 参数 | `d:\www\hc\ai\mcp\server.js` |
| CWD | `d:\www\hc\ai\mcp` |

5. 点击「添加」按钮

### mcpServers 配置

**Stdio 模式配置：**

```json
{
  "mcpServers": {
    "order-service-mcp": {
      "command": "node",
      "args": ["server.js"]
    }
  }
}
```

## 支持的工具

### 1. analyze_order（订单分析）

验证订单数据格式，返回验证结果和计算金额

**输入参数：**
```json
{
    "content": "我想要1份宫保鸡丁，一份鱼香肉丝，3份米饭，加辣",
    "special_requests": "加辣",
    "list": [
        {"id": "1", "name": "宫保鸡丁", "quantity": 1},
        {"id": "2", "name": "鱼香肉丝", "quantity": 1},
        {"id": "3", "name": "米饭", "quantity": 3}
    ]
}
```

**输出示例：**
```json
{
    "success": true,
    "message": "数据验证通过",
    "errors": [],
    "warnings": [],
    "content": "我想要1份宫保鸡丁...",
    "special_requests": "加辣",
    "list": [...],
    "items": [...],
    "total_amount": 57
}
```

### 2. create_order（创建订单）

创建订单，返回订单信息和支付二维码

**输入参数：**
```json
{
    "list": [
        {"id": "1", "name": "宫保鸡丁", "quantity": 1},
        {"id": "2", "name": "鱼香肉丝", "quantity": 1}
    ],
    "special_requests": "加辣",
    "total_amount": 54,
    "customer_info": {"name": "张三", "phone": "13800138000"}
}
```

**输出示例：**
```json
{
    "success": true,
    "message": "订单创建成功",
    "order_id": "ORD20260522100000ABCD",
    "order_no": "DD20260522123456",
    "qr_code": "https://api.qrserver.com/...",
    "total_amount": 54,
    "status": "pending"
}
```

### 3. check_payment_status（查询支付状态）

查询订单支付状态

**输入参数：**
```json
{
    "orderId": "ORD20260522100000ABCD"
}
```

**输出示例：**
```json
{
    "order_id": "ORD20260522100000ABCD",
    "status": "paid",
    "pay_time": "2026-05-22 10:30:00",
    "amount": 54
}
```

## 配置文件

### .env 环境变量

```env
THIRD_PARTY_API_HOST=http://localhost:8080
```

### 配置说明

| 配置项 | 说明 | 默认值 |
|--------|------|--------|
| THIRD_PARTY_API_HOST | 第三方 API 地址 | http://localhost:8080 |

## 项目结构

```
mcp/
├── server.js              # MCP服务器入口（fastmcp实现）
├── package.json           # 依赖配置
├── .env                   # 环境变量
└── mock/
    ├── thirdPartyServer.js # Node.js 模拟第三方API服务
    └── thirdPartyApi.php   # PHP 模拟第三方API服务
```

## 技术栈

- **框架**: fastmcp
- **验证**: Zod 3
- **HTTP**: axios

## 代码示例

**server.js** - 核心服务器代码：

```javascript
import { FastMCP } from "fastmcp";
import { z } from "zod";
import axios from "axios";

const server = new FastMCP({
  name: "order-service",
  description: "支持AI订单分析、订单创建和支付状态查询的MCP服务",
  version: "1.0.0",
});

server.addTool({
  name: "analyze_order",
  description: "验证订单数据格式，返回验证结果和计算金额",
  parameters: z.object({
    content: z.string().describe("用户输入的订单内容"),
    special_requests: z.string().optional().describe("特殊要求"),
    list: z.array(z.object({
      id: z.string().describe("商品ID"),
      name: z.string().describe("商品名称"),
      quantity: z.number().describe("数量")
    })).describe("商品列表")
  }),
  execute: async (args) => {
    const result = await axios.post(`${process.env.THIRD_PARTY_API_HOST}/api/analyze`, args);
    return JSON.stringify(result.data, null, 2);
  },
});

server.start({
  transportType: "stdio",
});
```

## 参考文档

- [fastmcp 文档](https://github.com/fastmcp/fastmcp)
- [MCP 协议规范](https://github.com/modelcontextprotocol/spec)
- [FastGPT MCP 文档](https://doc.fastgpt.in/docs/tool/custom/)
- [AingDesk MCP 文档](https://www.aingdesk.com/docs/guide/mcp.html)
