<?php

define('VALID_API_KEY', 'your-secret-api-key');
define('AUTH_ENABLED', true);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (AUTH_ENABLED) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (empty($authHeader)) {
        sendErrorResponse(401, '未授权访问', 'Authorization header is required');
    }
    
    $parts = explode(' ', $authHeader);
    if (count($parts) !== 2 || strtolower($parts[0]) !== 'bearer') {
        sendErrorResponse(401, '未授权访问', 'Invalid Authorization header format');
    }
    
    $apiKey = trim($parts[1]);
    if ($apiKey !== VALID_API_KEY) {
        sendErrorResponse(401, '未授权访问', 'Invalid API Key ' . $apiKey . '<>' . VALID_API_KEY);
    }
}

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

$routes = [
    ['POST', '/api/analyze', 'handleAnalyze'],
    ['POST', '/api/order', 'handleOrder'],
    ['GET', '/api/pay/status', 'handlePayStatus'],
];

$matched = false;
foreach ($routes as $route) {
    list($method, $path, $handler) = $route;
    if ($requestMethod === $method && (strpos($requestUri, $path) !== false)) {
        $matched = true;
        call_user_func($handler);
        break;
    }
}

if (!$matched) {
    sendErrorResponse(404, 'Not Found', 'API endpoint not found');
}

function sendErrorResponse($statusCode, $message, $error = '') {
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error' => $error
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function parseRequestBody() {
    $body = file_get_contents('php://input');
    if (empty($body)) {
        return [];
    }
    
    try {
        return json_decode($body, true);
    } catch (Exception $e) {
        try {
            $bodyGbk = iconv('GBK', 'UTF-8', $body);
            return json_decode($bodyGbk, true);
        } catch (Exception $e2) {
            error_log('解析请求体失败: ' . $e2->getMessage());
            return [];
        }
    }
}

function getMenu() {
    return [
        '杂粮煎饼' => ['id' => '1', 'price' => 6],
        '小米煎饼' => ['id' => '2', 'price' => 6],
        '紫米煎饼' => ['id' => '3', 'price' => 6],
        '玉米煎饼' => ['id' => '4', 'price' => 6],
        '鸡蛋' => ['id' => '5', 'price' => 1],
        '香肠' => ['id' => '6', 'price' => 1],
        '豆浆' => ['id' => '7', 'price' => 1.5]
    ];
}

function findMenuItem($menu, $itemId, $itemName) {
    foreach ($menu as $name => $menuData) {
        if ($menuData['id'] === (string)$itemId || $name === $itemName) {
            return $menuData;
        }
    }
    return null;
}

function getItemMenuKey($menu, $menuItem) {
    foreach ($menu as $name => $data) {
        if ($data['id'] === $menuItem['id']) {
            return $name;
        }
    }
    return null;
}

function handleAnalyze() {
    $menu = getMenu();
    $data = parseRequestBody();
    
    $content = isset($data['content']) ? $data['content'] : '';
    $special_requests = isset($data['special_requests']) ? $data['special_requests'] : '';
    $list = isset($data['list']) ? $data['list'] : [];
    
    error_log('[Mock API] 收到订单分析请求: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
    
    $errors = [];
    $warnings = [];
    
    if (empty($content)) {
        $errors[] = 'content 字段不能为空';
    }
    
    if (!is_array($list)) {
        $errors[] = 'list 必须是数组';
    } else {
        foreach ($list as $index => $item) {
            $itemId = isset($item['id']) ? (string)$item['id'] : '';
            $itemName = isset($item['name']) ? $item['name'] : '';
            
            if (empty($itemId)) {
                $errors[] = "list[{$index}].id 不能为空";
            }
            if (empty($itemName)) {
                $errors[] = "list[{$index}].name 不能为空";
            }
            
            $menuItem = findMenuItem($menu, $itemId, $itemName);
            
            if ($menuItem) {
                if ((string)$menuItem['id'] !== $itemId) {
                    $warnings[] = "list[{$index}].id 与菜单不匹配，建议使用 {$menuItem['id']}";
                }
                $menuKey = getItemMenuKey($menu, $menuItem);
                if ($menuKey !== null && $menuKey !== $itemName) {
                    $warnings[] = "list[{$index}].name 与菜单不匹配，建议使用 {$menuKey}";
                }
            } else {
                $warnings[] = "list[{$index}].name \"{$itemName}\" 不在菜单中";
            }
        }
    }
    
    $totalAmount = 0;
    $items = [];
    
    foreach ($list as $item) {
        $itemId = isset($item['id']) ? (string)$item['id'] : '';
        $itemName = isset($item['name']) ? $item['name'] : '';
        $menuItem = findMenuItem($menu, $itemId, $itemName);
        
        $price = $menuItem ? $menuItem['price'] : (isset($item['price']) ? floatval($item['price']) : 0);
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $amount = $price * $quantity;
        $totalAmount += $amount;
        
        $items[] = [
            'id' => $itemId ?: ($menuItem ? $menuItem['id'] : ''),
            'name' => $itemName,
            'quantity' => $quantity,
            'price' => $price,
            'amount' => $amount
        ];
    }
    
    $response = [
        'success' => empty($errors),
        'message' => empty($errors) ? '数据验证通过' : '数据验证失败',
        'errors' => $errors,
        'warnings' => $warnings,
        'content' => $content,
        'special_requests' => $special_requests,
        'list' => $list,
        'items' => $items,
        'total_amount' => $totalAmount,
        'customer_info' => ['name' => '顾客', 'phone' => '13800138000']
    ];
    
    error_log('[Mock API] 订单分析结果: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

function handleOrder() {
    $menu = getMenu();
    $data = parseRequestBody();
    
    $list = isset($data['list']) ? $data['list'] : (isset($data['items']) ? $data['items'] : []);
    $special_requests = isset($data['special_requests']) ? $data['special_requests'] : (isset($data['specialRequests']) ? $data['specialRequests'] : '');
    $total_amount = isset($data['total_amount']) ? floatval($data['total_amount']) : (isset($data['totalAmount']) ? floatval($data['totalAmount']) : 0);
    
    error_log('[Mock API] 收到创建订单请求: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
    
    $errors = [];
    $warnings = [];
    
    if (!is_array($list) || empty($list)) {
        $errors[] = 'list 不能为空';
    } else {
        foreach ($list as $index => $item) {
            $itemId = isset($item['id']) ? (string)$item['id'] : '';
            $itemName = isset($item['name']) ? $item['name'] : '';
            
            if (empty($itemId)) {
                $errors[] = "list[{$index}].id 不能为空";
            }
            if (empty($itemName)) {
                $errors[] = "list[{$index}].name 不能为空";
            }
        }
    }
    
    if (!empty($errors)) {
        $response = [
            'success' => false,
            'message' => '数据验证失败',
            'errors' => $errors
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $calculatedAmount = 0;
    $items = [];
    
    foreach ($list as $item) {
        $itemId = isset($item['id']) ? (string)$item['id'] : '';
        $itemName = isset($item['name']) ? $item['name'] : '';
        $menuItem = findMenuItem($menu, $itemId, $itemName);
        
        $price = $menuItem ? $menuItem['price'] : (isset($item['price']) ? floatval($item['price']) : 0);
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $amount = $price * $quantity;
        $calculatedAmount += $amount;
        
        $items[] = [
            'id' => $itemId ?: ($menuItem ? $menuItem['id'] : ''),
            'name' => $itemName,
            'quantity' => $quantity,
            'price' => $price,
            'amount' => $amount
        ];
    }
    
    if ($total_amount > 0 && abs($total_amount - $calculatedAmount) > 0.01) {
        $warnings[] = "传入的 total_amount ({$total_amount}) 与计算金额 ({$calculatedAmount}) 不一致";
    }
    
    $orderId = 'ORD' . time() . strtoupper(substr(uniqid('', true), -4));
    $orderNo = 'DD' . date('Ymd') . rand(100000, 999999);
    
    $response = [
        'success' => true,
        'message' => '订单创建成功',
        'warnings' => $warnings,
        'order_id' => $orderId,
        'order_no' => $orderNo,
        'qr_code' => 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($orderId),
        'total_amount' => $calculatedAmount,
        'status' => 'pending',
        'create_time' => date('Y-m-d H:i:s'),
        'items' => $items,
        'special_requests' => $special_requests,
        'customer_info' => isset($data['customer_info']) ? $data['customer_info'] : (isset($data['customerInfo']) ? $data['customerInfo'] : ['name' => '顾客', 'phone' => '13800138000'])
    ];
    
    error_log('[Mock API] 创建订单结果: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

function handlePayStatus() {
    $path = $_SERVER['REQUEST_URI'];
    preg_match('/\/api\/pay\/status\/([^\/]+)/', $path, $matches);
    if(!empty($matches[1])){
        $orderId = $matches[1];
    }else if(!empty($_GET['order_id'])){
        $orderId = $_GET['order_id'];
    }else{
        $orderId = '';
    }
    $mockStatus = ['pending', 'paid', 'paid', 'pending'];
    $randomStatus = $mockStatus[array_rand($mockStatus)];
    
    $response = [
        'order_id' => $orderId,
        'status' => $randomStatus,
        'pay_time' => $randomStatus === 'paid' ? date('Y-m-d H:i:s') : null,
        'amount' => 7.5
    ];
    
    error_log('[Mock API] 查询支付状态: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

?>