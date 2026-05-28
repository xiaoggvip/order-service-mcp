# 第三方模拟 API 接口文档

## 概述

本文档描述了第三方模拟 API 服务提供的接口，用于订单分析、订单创建和支付状态查询功能。

---

## 基础信息

### 服务地址
- **Node.js 版本**: `http://localhost:8080`
- **PHP 版本**: 根据 Web 服务器配置（如 `http://localhost/mock/thirdPartyApi.php`）

### 认证方式

API 支持 Bearer Token 认证，可通过配置启用/禁用：

| 参数 | 值 | 说明 |
|------|-----|------|
| `AUTH_ENABLED` | `true` / `false` | 是否启用认证 |
| `VALID_API_KEY` | `your-secret-api-key` | 有效 API Key |

**认证请求头**:
```
Authorization: Bearer your-secret-api-key
```

### 响应格式

所有接口返回 JSON 格式数据：

```json
{
    "success": true/false,
    "message": "操作结果描述",
    "errors": [],
    "warnings": [],
    ...
}
```

---

## 接口列表

### 1. 订单分析接口

**接口地址**: `POST /api/analyze`

**功能描述**: 分析订单内容，验证数据合法性，计算订单金额

#### 请求参数

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `content` | string | 是 | 订单内容文本 |
| `special_requests` | string | 否 | 特殊要求 |
| `list` | array | 是 | 商品列表 |
| `list[].id` | string | 是 | 商品 ID |
| `list[].name` | string | 是 | 商品名称 |
| `list[].quantity` | int | 否 | 数量，默认 1 |
| `list[].price` | float | 否 | 单价（当商品不在菜单中时使用） |

#### 请求示例

```json
{
    "content": "顾客点了一份杂粮煎饼加鸡蛋",
    "special_requests": "不要辣",
    "list": [
        { "id": "1", "name": "杂粮煎饼", "quantity": 1 },
        { "id": "5", "name": "鸡蛋", "quantity": 1 }
    ]
}
```

#### 响应示例

```json
{
    "success": true,
    "message": "数据验证通过",
    "errors": [],
    "warnings": [],
    "content": "顾客点了一份杂粮煎饼加鸡蛋",
    "special_requests": "不要辣",
    "list": [...],
    "items": [
        { "id": "1", "name": "杂粮煎饼", "quantity": 1, "price": 6, "amount": 6 },
        { "id": "5", "name": "鸡蛋", "quantity": 1, "price": 1, "amount": 1 }
    ],
    "total_amount": 7,
    "customer_info": { "name": "顾客", "phone": "13800138000" }
}
```

#### 响应字段说明

| 字段 | 类型 | 说明 |
|------|------|------|
| `success` | bool | 是否成功 |
| `message` | string | 操作结果描述 |
| `errors` | array | 错误信息列表 |
| `warnings` | array | 警告信息列表 |
| `content` | string | 原始订单内容 |
| `special_requests` | string | 特殊要求 |
| `list` | array | 原始商品列表 |
| `items` | array | 处理后的商品列表（含金额计算） |
| `total_amount` | float | 订单总金额 |
| `customer_info` | object | 顾客信息 |

---

### 2. 创建订单接口

**接口地址**: `POST /api/order`

**功能描述**: 创建新订单，生成订单号和二维码

#### 请求参数

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `list` / `items` | array | 是 | 商品列表（支持两种字段名） |
| `list[].id` | string | 是 | 商品 ID |
| `list[].name` | string | 是 | 商品名称 |
| `list[].quantity` | int | 否 | 数量，默认 1 |
| `list[].price` | float | 否 | 单价 |
| `special_requests` / `specialRequests` | string | 否 | 特殊要求（支持两种字段名） |
| `total_amount` / `totalAmount` | float | 否 | 订单总金额 |
| `customer_info` / `customerInfo` | object | 否 | 顾客信息 |

#### 请求示例

```json
{
    "list": [
        { "id": "1", "name": "杂粮煎饼", "quantity": 1 },
        { "id": "7", "name": "豆浆", "quantity": 1 }
    ],
    "special_requests": "打包",
    "customer_info": { "name": "张三", "phone": "13812345678" }
}
```

#### 响应示例

```json
{
    "success": true,
    "message": "订单创建成功",
    "warnings": [],
    "order_id": "ORD20240115103045ABCD",
    "order_no": "DD20240115123456",
    "qr_code": "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=ORD20240115103045ABCD",
    "total_amount": 7.5,
    "status": "pending",
    "create_time": "2024-01-15 10:30:45",
    "items": [...],
    "special_requests": "打包",
    "customer_info": { "name": "张三", "phone": "13812345678" }
}
```

#### 响应字段说明

| 字段 | 类型 | 说明 |
|------|------|------|
| `success` | bool | 是否成功 |
| `message` | string | 操作结果描述 |
| `warnings` | array | 警告信息列表 |
| `order_id` | string | 订单唯一标识 |
| `order_no` | string | 订单编号 |
| `qr_code` | string | 支付二维码 URL |
| `total_amount` | float | 订单总金额 |
| `status` | string | 订单状态（pending/paid） |
| `create_time` | string | 创建时间 |
| `items` | array | 商品列表 |
| `special_requests` | string | 特殊要求 |
| `customer_info` | object | 顾客信息 |

---

### 3. 查询支付状态接口

**接口地址**: `GET /api/pay/status/{orderId}` 或 `GET /api/pay/status?order_id={orderId}`

**功能描述**: 查询订单支付状态（模拟随机返回支付状态）

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `orderId` | string | 是 | 订单 ID（路径参数或查询参数） |

#### 请求示例

```bash
# 方式一：路径参数
GET /api/pay/status/ORD20240115103045ABCD

# 方式二：查询参数
GET /api/pay/status?order_id=ORD20240115103045ABCD
```

#### 响应示例

```json
{
    "order_id": "ORD20240115103045ABCD",
    "status": "paid",
    "pay_time": "2024-01-15 10:32:15",
    "amount": 7.5
}
```

#### 响应字段说明

| 字段 | 类型 | 说明 |
|------|------|------|
| `order_id` | string | 订单 ID |
| `status` | string | 支付状态（pending/paid） |
| `pay_time` | string/null | 支付时间（未支付时为 null） |
| `amount` | float | 支付金额 |

---

## 菜单商品列表

| ID | 商品名称 | 价格（元） |
|----|----------|------------|
| 1 | 杂粮煎饼 | 6.00 |
| 2 | 小米煎饼 | 6.00 |
| 3 | 紫米煎饼 | 6.00 |
| 4 | 玉米煎饼 | 6.00 |
| 5 | 鸡蛋 | 1.00 |
| 6 | 香肠 | 1.00 |
| 7 | 豆浆 | 1.50 |

---

## 错误码说明

| HTTP 状态码 | 说明 |
|-------------|------|
| 401 | 未授权访问（认证失败或未提供认证信息） |
| 404 | API 端点不存在 |

---

## 使用示例

### cURL 示例

```bash
# 订单分析
curl -X POST http://localhost:8080/api/analyze \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-secret-api-key" \
  -d '{"content":"测试订单","list":[{"id":"1","name":"杂粮煎饼"}]}'

# 创建订单
curl -X POST http://localhost:8080/api/order \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-secret-api-key" \
  -d '{"list":[{"id":"1","name":"杂粮煎饼"},{"id":"7","name":"豆浆"}]}'

# 查询支付状态
curl -X GET "http://localhost:8080/api/pay/status/ORD20240115103045ABCD" \
  -H "Authorization: Bearer your-secret-api-key"
```

### JavaScript 示例

```javascript
const API_BASE = 'http://localhost:8080';
const API_KEY = 'your-secret-api-key';

async function analyzeOrder(data) {
    const response = await fetch(`${API_BASE}/api/analyze`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${API_KEY}`
        },
        body: JSON.stringify(data)
    });
    return response.json();
}

async function createOrder(data) {
    const response = await fetch(`${API_BASE}/api/order`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${API_KEY}`
        },
        body: JSON.stringify(data)
    });
    return response.json();
}

async function getPayStatus(orderId) {
    const response = await fetch(`${API_BASE}/api/pay/status/${orderId}`, {
        headers: { 'Authorization': `Bearer ${API_KEY}` }
    });
    return response.json();
}
```

### PHP 示例

```php
<?php
$apiBase = 'http://localhost/mock/thirdPartyApi.php';
$apiKey = 'your-secret-api-key';

function callApi($endpoint, $method = 'GET', $data = []) {
    global $apiBase, $apiKey;
    
    $url = $apiBase . $endpoint;
    $ch = curl_init($url);
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// 订单分析
$result = callApi('/api/analyze', 'POST', [
    'content' => '测试订单',
    'list' => [['id' => '1', 'name' => '杂粮煎饼']]
]);

// 创建订单
$result = callApi('/api/order', 'POST', [
    'list' => [['id' => '1', 'name' => '杂粮煎饼']]
]);

// 查询支付状态
$result = callApi('/api/pay/status/ORD20240115103045ABCD');
?>
```

---

## 配置说明

### Node.js 版本配置

编辑 `thirdPartyServer.js`：

```javascript
const VALID_API_KEY = 'your-secret-api-key';  // API Key
const AUTH_ENABLED = true;                     // 是否启用认证
const PORT = 8080;                             // 服务端口
```

### PHP 版本配置

编辑 `thirdPartyApi.php`：

```php
define('VALID_API_KEY', 'your-secret-api-key');  // API Key
define('AUTH_ENABLED', true);                     // 是否启用认证
```

---

## 启动方式

### Node.js 版本

```bash
# 安装依赖
npm install express cors

# 启动服务
node thirdPartyServer.js
```

### PHP 版本

将 `thirdPartyApi.php` 放置在 Web 服务器（如 Apache、Nginx）的可访问目录下即可。