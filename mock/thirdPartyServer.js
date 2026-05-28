import express from 'express';
import cors from 'cors';

const app = express();
const PORT = 8080;

const VALID_API_KEY = 'your-secret-api-key';
const AUTH_ENABLED = true;

process.env.NODE_OPTIONS = '--experimental-default-type=module --enable-source-maps';

app.use(cors());
app.use(express.raw({ type: 'application/json', limit: '1mb' }));

app.use((req, res, next) => {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.setHeader('Access-Control-Allow-Origin', '*');
  
  if (AUTH_ENABLED) {
    const authHeader = req.headers['authorization'];
    if (!authHeader) {
      res.status(401).end(JSON.stringify({
        success: false,
        message: '未授权访问',
        error: 'Authorization header is required'+JSON.stringify(req.headers)
      }));
      return;
    }
    
    const parts = authHeader.split(' ');
    if (parts.length !== 2 || parts[0] !== 'Bearer') {
      res.status(401).end(JSON.stringify({
        success: false,
        message: '未授权访问',
        error: 'Invalid Authorization header format'
      }));
      return;
    }
    
    const apiKey = parts[1];
    if (apiKey !== VALID_API_KEY) {
      res.status(401).end(JSON.stringify({
        success: false,
        message: '未授权访问',
        error: 'Invalid API Key'
      }));
      return;
    }
  }
  
  if (req.body && Buffer.isBuffer(req.body)) {
    try {
      const bodyString = req.body.toString('utf-8');
      req.body = JSON.parse(bodyString);
    } catch (e) {
      try {
        const bodyString = req.body.toString('gbk');
        req.body = JSON.parse(bodyString);
      } catch (e2) {
        console.error('解析请求体失败:', e2.message);
        req.body = {};
      }
    }
  }
  next();
});

const menu = {
  '杂粮煎饼': { id: '1', price: 6 },
  '小米煎饼': { id: '2', price: 6 },
  '紫米煎饼': { id: '3', price: 6 },
  '玉米煎饼': { id: '4', price: 6 },
  '鸡蛋': { id: '5', price: 1 },
  '香肠': { id: '6', price: 1 },
  '豆浆': { id: '7', price: 1.5 }
};

app.post('/api/analyze', (req, res) => {
  const data = req.body || {};
  const content = data.content || '';
  const special_requests = data.special_requests || '';
  const list = data.list || [];
  
  console.log('[Mock API] 收到订单分析请求:', JSON.stringify(data, null, 2));
  
  let errors = [];
  let warnings = [];
  
  if (!content) {
    errors.push('content 字段不能为空');
  }
  
  if (!Array.isArray(list)) {
    errors.push('list 必须是数组');
  } else {
    list.forEach((item, index) => {
      if (!item.id) {
        errors.push(`list[${index}].id 不能为空`);
      }
      if (!item.name) {
        errors.push(`list[${index}].name 不能为空`);
      }
      
      const menuItem = Object.values(menu).find(m => m.id === item.id || m.name === item.name);
      if (menuItem) {
        if (menuItem.id !== item.id && item.id !== String(menuItem.id)) {
          warnings.push(`list[${index}].id 与菜单不匹配，建议使用 ${menuItem.id}`);
        }
        const menuKey = Object.keys(menu).find(k => menu[k].id === menuItem.id);
        if (menuKey && menuKey !== item.name) {
          warnings.push(`list[${index}].name 与菜单不匹配，建议使用 ${menuKey}`);
        }
      } else {
        warnings.push(`list[${index}].name "${item.name}" 不在菜单中`);
      }
    });
  }
  
  let totalAmount = 0;
  const items = list.map(item => {
    const menuItem = Object.values(menu).find(m => m.id === item.id || m.name === item.name);
    const price = menuItem?.price || item.price || 0;
    const quantity = item.quantity || 1;
    const amount = price * quantity;
    totalAmount += amount;
    return {
      id: item.id || menuItem?.id || '',
      name: item.name,
      quantity: quantity,
      price: price,
      amount: amount
    };
  });
  
  const result = {
    success: errors.length === 0,
    message: errors.length === 0 ? '数据验证通过' : '数据验证失败',
    errors: errors,
    warnings: warnings,
    content: content,
    special_requests: special_requests,
    list: list,
    items: items,
    total_amount: totalAmount,
    customer_info: { name: '客服电话', phone: '13800138000' }
  };
  
  console.log('[Mock API] 订单分析结果:', JSON.stringify(result, null, 2));
  res.end(JSON.stringify(result));
});

app.post('/api/order', (req, res) => {
  const data = req.body || {};
  const list = data.list || data.items || [];
  const special_requests = data.special_requests || data.specialRequests || '';
  const total_amount = data.total_amount || data.totalAmount || 0;
  
  console.log('[Mock API] 收到创建订单请求:', JSON.stringify(data, null, 2));
  
  let errors = [];
  let warnings = [];
  
  if (!Array.isArray(list) || list.length === 0) {
    errors.push('list 不能为空');
  } else {
    list.forEach((item, index) => {
      if (!item.id) {
        errors.push(`list[${index}].id 不能为空`);
      }
      if (!item.name) {
        errors.push(`list[${index}].name 不能为空`);
      }
    });
  }
  
  if (errors.length > 0) {
    res.end(JSON.stringify({
      success: false,
      message: '数据验证失败',
      errors: errors
    }));
    return;
  }
  
  let calculatedAmount = 0;
  const items = list.map(item => {
    const menuItem = Object.values(menu).find(m => m.id === item.id || m.name === item.name);
    const price = menuItem?.price || item.price || 0;
    const quantity = item.quantity || 1;
    const amount = price * quantity;
    calculatedAmount += amount;
    return {
      id: item.id || menuItem?.id || '',
      name: item.name,
      quantity: quantity,
      price: price,
      amount: amount
    };
  });
  
  if (total_amount > 0 && Math.abs(total_amount - calculatedAmount) > 0.01) {
    warnings.push(`传入的 total_amount (${total_amount}) 与计算金额 (${calculatedAmount}) 不一致`);
  }
  
  const orderId = 'ORD' + Date.now() + Math.random().toString(36).substr(2, 4).toUpperCase();
  const orderNo = 'DD' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + Math.floor(Math.random() * 900000 + 100000);
  
  const result = {
    success: true,
    message: '订单创建成功',
    warnings: warnings,
    order_id: orderId,
    order_no: orderNo,
    qr_code: 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(orderId),
    total_amount: calculatedAmount,
    status: 'pending',
    create_time: new Date().toLocaleString('zh-CN'),
    items: items,
    special_requests: special_requests,
    customer_info: data.customer_info || data.customerInfo || { name: '顾客', phone: '13800138000' }
  };
  
  console.log('[Mock API] 创建订单结果:', JSON.stringify(result, null, 2));
  res.end(JSON.stringify(result));
});

app.get('/api/pay/status/:orderId', (req, res) => {
  const orderId = req.params.orderId || req.query.order_id;
  
  const mockStatus = ['pending', 'paid', 'paid', 'pending'];
  const randomStatus = mockStatus[Math.floor(Math.random() * mockStatus.length)];
  
  const result = {
    order_id: orderId,
    status: randomStatus,
    pay_time: randomStatus === 'paid' ? new Date().toLocaleString('zh-CN') : null,
    amount: 7.5
  };
  
  console.log('[Mock API] 查询支付状态:', JSON.stringify(result, null, 2));
  res.end(JSON.stringify(result));
});

app.get('/api/pay/status', (req, res) => {
  const orderId = req.query.order_id;
  const mockStatus = ['pending', 'paid', 'paid', 'pending'];
  const randomStatus = mockStatus[Math.floor(Math.random() * mockStatus.length)];
  
  res.end(JSON.stringify({
    order_id: orderId,
    status: randomStatus,
    pay_time: randomStatus === 'paid' ? new Date().toLocaleString('zh-CN') : null,
    amount: 7.5
  }));
});

app.listen(PORT, () => {
  console.log(`模拟第三方API服务已启动，监听端口: ${PORT}`);
  console.log(`API Key 认证: ${AUTH_ENABLED ? '已启用 (Authorization: Bearer)' : '已禁用'}`);
});
