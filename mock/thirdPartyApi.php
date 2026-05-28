<?php

class ThirdPartyApi
{
    const VALID_API_KEY = 'your-secret-api-key';
    const AUTH_ENABLED = true;

    private $requestUri;
    private $requestMethod;
    private $menu = array(
        '杂粮煎饼' => array('id' => '1', 'price' => 6),
        '小米煎饼' => array('id' => '2', 'price' => 6),
        '紫米煎饼' => array('id' => '3', 'price' => 6),
        '玉米煎饼' => array('id' => '4', 'price' => 6),
        '鸡蛋' => array('id' => '5', 'price' => 1),
        '香肠' => array('id' => '6', 'price' => 1),
        '豆浆' => array('id' => '7', 'price' => 1.5)
    );

    public function __construct()
    {
        $this->requestUri = $_SERVER['REQUEST_URI'];
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->initHeaders();
    }

    private function initHeaders()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($this->requestMethod === 'OPTIONS') {
            exit(0);
        }
    }

    private function authenticate()
    {
        if (!self::AUTH_ENABLED) {
            return true;
        }

        $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        if (empty($authHeader)) {
            $this->sendErrorResponse(401, '未授权访问', 'Authorization header is required');
        }

        $parts = explode(' ', $authHeader);
        if (count($parts) !== 2 || strtolower($parts[0]) !== 'bearer') {
            $this->sendErrorResponse(401, '未授权访问', 'Invalid Authorization header format');
        }

        $apiKey = trim($parts[1]);
        if ($apiKey !== self::VALID_API_KEY) {
            $this->sendErrorResponse(401, '未授权访问', 'Invalid API Key ');
        }

        return true;
    }

    private function sendErrorResponse($statusCode, $message, $error = '')
    {
        http_response_code($statusCode);
        echo json_encode(array(
            'success' => false,
            'message' => $message,
            'error' => $error
        ), JSON_UNESCAPED_UNICODE);
        exit();
    }

    private function parseRequestBody()
    {
        $body = file_get_contents('php://input');

        if (empty($body)) {
            return array();
        }

        $result = json_decode($body, true);
        if ($result !== null) {
            return $result;
        }

        try {
            $bodyGbk = iconv('GBK', 'UTF-8', $body);
            $result = json_decode($bodyGbk, true);
            if ($result !== null) {
                return $result;
            }
        } catch (Exception $e) {
            error_log('解析请求体失败: ' . $e->getMessage());
        }

        return array();
    }

    private function findMenuItem($itemId, $itemName)
    {
        foreach ($this->menu as $name => $menuData) {
            if ($menuData['id'] === (string)$itemId || $name === $itemName) {
                return $menuData;
            }
        }
        return null;
    }

    private function getItemMenuKey($menuItem)
    {
        foreach ($this->menu as $name => $data) {
            if ($data['id'] === $menuItem['id']) {
                return $name;
            }
        }
        return null;
    }

    private function handleAnalyze()
    {
        $data = $this->parseRequestBody();

        $content = isset($data['content']) ? $data['content'] : '';
        $special_requests = isset($data['special_requests']) ? $data['special_requests'] : '';
        $list = isset($data['list']) ? $data['list'] : array();

        error_log('[Mock API] 收到订单分析请求: ' . json_encode($data, JSON_UNESCAPED_UNICODE));

        $errors = array();
        $warnings = array();

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
                    $errors[] = 'list[' . $index . '].id 不能为空';
                }
                if (empty($itemName)) {
                    $errors[] = 'list[' . $index . '].name 不能为空';
                }

                $menuItem = $this->findMenuItem($itemId, $itemName);

                if ($menuItem) {
                    if ((string)$menuItem['id'] !== $itemId) {
                        $warnings[] = 'list[' . $index . '].id 与菜单不匹配，建议使用 ' . $menuItem['id'];
                    }
                    $menuKey = $this->getItemMenuKey($menuItem);
                    if ($menuKey !== null && $menuKey !== $itemName) {
                        $warnings[] = 'list[' . $index . '].name 与菜单不匹配，建议使用 ' . $menuKey;
                    }
                } else {
                    $warnings[] = 'list[' . $index . '].name "' . $itemName . '" 不在菜单中';
                }
            }
        }

        $totalAmount = 0;
        $items = array();

        foreach ($list as $item) {
            $itemId = isset($item['id']) ? (string)$item['id'] : '';
            $itemName = isset($item['name']) ? $item['name'] : '';
            $menuItem = $this->findMenuItem($itemId, $itemName);

            $price = $menuItem ? $menuItem['price'] : (isset($item['price']) ? (float)$item['price'] : 0);
            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
            $amount = $price * $quantity;
            $totalAmount += $amount;

            $items[] = array(
                'id' => $itemId ?: ($menuItem ? $menuItem['id'] : ''),
                'name' => $itemName,
                'quantity' => $quantity,
                'price' => $price,
                'amount' => $amount
            );
        }

        $response = array(
            'success' => empty($errors),
            'message' => empty($errors) ? '数据验证通过' : '数据验证失败',
            'errors' => $errors,
            'warnings' => $warnings,
            'content' => $content,
            'special_requests' => $special_requests,
            'list' => $list,
            'items' => $items,
            'total_amount' => $totalAmount,
            'customer_info' => array('name' => '客服电话', 'phone' => '13800138000')
        );

        error_log('[Mock API] 订单分析结果: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    private function handleOrder()
    {
        $data = $this->parseRequestBody();

        $list = isset($data['list']) ? $data['list'] : (isset($data['items']) ? $data['items'] : array());
        $special_requests = isset($data['special_requests']) ? $data['special_requests'] : (isset($data['specialRequests']) ? $data['specialRequests'] : '');
        $total_amount = isset($data['total_amount']) ? (float)$data['total_amount'] : (isset($data['totalAmount']) ? (float)$data['totalAmount'] : 0);

        error_log('[Mock API] 收到创建订单请求: ' . json_encode($data, JSON_UNESCAPED_UNICODE));

        $errors = array();
        $warnings = array();

        if (!is_array($list) || empty($list)) {
            $errors[] = 'list 不能为空';
        } else {
            foreach ($list as $index => $item) {
                $itemId = isset($item['id']) ? (string)$item['id'] : '';
                $itemName = isset($item['name']) ? $item['name'] : '';

                if (empty($itemId)) {
                    $errors[] = 'list[' . $index . '].id 不能为空';
                }
                if (empty($itemName)) {
                    $errors[] = 'list[' . $index . '].name 不能为空';
                }
            }
        }

        if (!empty($errors)) {
            $response = array(
                'success' => false,
                'message' => '数据验证失败',
                'errors' => $errors
            );
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return;
        }

        $calculatedAmount = 0;
        $items = array();

        foreach ($list as $item) {
            $itemId = isset($item['id']) ? (string)$item['id'] : '';
            $itemName = isset($item['name']) ? $item['name'] : '';
            $menuItem = $this->findMenuItem($itemId, $itemName);

            $price = $menuItem ? $menuItem['price'] : (isset($item['price']) ? (float)$item['price'] : 0);
            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
            $amount = $price * $quantity;
            $calculatedAmount += $amount;

            $items[] = array(
                'id' => $itemId ?: ($menuItem ? $menuItem['id'] : ''),
                'name' => $itemName,
                'quantity' => $quantity,
                'price' => $price,
                'amount' => $amount
            );
        }

        if ($total_amount > 0 && abs($total_amount - $calculatedAmount) > 0.01) {
            $warnings[] = '传入的 total_amount (' . $total_amount . ') 与计算金额 (' . $calculatedAmount . ') 不一致';
        }

        $orderId = 'ORD' . time() . strtoupper(substr(uniqid('', true), -4));
        $orderNo = 'DD' . date('Ymd') . rand(100000, 999999);

        $response = array(
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
            'customer_info' => isset($data['customer_info']) ? $data['customer_info'] : (isset($data['customerInfo']) ? $data['customerInfo'] : array('name' => '顾客', 'phone' => '13800138000'))
        );

        error_log('[Mock API] 创建订单结果: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    private function handlePayStatus()
    {
        $path = $_SERVER['REQUEST_URI'];
        preg_match('/\/api\/pay\/status\/([^\/]+)/', $path, $matches);
        $orderId = isset($matches[1]) ? $matches[1] : (isset($_GET['order_id']) ? $_GET['order_id'] : '');

        $mockStatus = array('pending', 'paid', 'paid', 'pending');
        $randomStatus = $mockStatus[array_rand($mockStatus)];

        $response = array(
            'order_id' => $orderId,
            'status' => $randomStatus,
            'pay_time' => $randomStatus === 'paid' ? date('Y-m-d H:i:s') : null,
            'amount' => 7.5
        );

        error_log('[Mock API] 查询支付状态: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    public function run()
    {
        if (!$this->authenticate()) {
            return;
        }

        $routes = array(
            array('POST', '/api/analyze', 'handleAnalyze'),
            array('POST', '/api/order', 'handleOrder'),
            array('GET', '/api/pay/status', 'handlePayStatus'),
        );

        $matched = false;
        foreach ($routes as $route) {
            list($method, $path, $handler) = $route;
            if ($this->requestMethod === $method && strpos($this->requestUri, $path) !== false) {
                $matched = true;
                call_user_func(array($this, $handler));
                break;
            }
        }

        if (!$matched) {
            $this->sendErrorResponse(404, 'Not Found', 'API endpoint not found');
        }
    }
}

$api = new ThirdPartyApi();
$api->run();

?>