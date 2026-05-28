import { config } from 'dotenv';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
config({ path: resolve(__dirname, '.env') });

import { FastMCP } from "fastmcp";
import { z } from "zod";
import axios from "axios";
import logger from "./utils/logger.js";

const env = {
  thirdPartyApiHost: process.env.THIRD_PARTY_API_HOST || "http://localhost:8080",
  authEnabled: process.env.THIRD_PARTY_AUTH_ENABLED === 'true',
  apiKey: process.env.THIRD_PARTY_API_KEY || ''
};

const headers = { 'Content-Type': 'application/json; charset=utf-8' };
if (env.authEnabled && env.apiKey) {
  headers['Authorization'] = `Bearer ${env.apiKey}`;
}
logger.info('MCP headers...', headers);

const axiosInstance = axios.create({
  baseURL: env.thirdPartyApiHost,
  headers: headers,
  timeout: 10000
});

function getErrorMessage(error) {
  if (error.response) {
    return `HTTP错误 ${error.response.status}: ${JSON.stringify(error.response.data)}`;
  } else if (error.request) {
    return `请求超时或无法连接到服务器`;
  } else {
    return error.message || '未知错误';
  }
}

logger.info('MCP服务启动中...', { 
  thirdPartyApiHost: env.thirdPartyApiHost,
  authEnabled: env.authEnabled,
  apiKeyConfigured: env.apiKey ? '已配置' : '未配置',
  authMethod: env.authEnabled ? 'Authorization: Bearer' : '无认证'
});

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
    logger.info('调用 analyze_order', args);
    try {
      const result = await axiosInstance.post('/api/analyze', args);
      logger.debug('analyze_order 返回结果', result.data);
      return JSON.stringify(result.data, null, 2);
    } catch (error) {
      const errorMsg = getErrorMessage(error);
      logger.error('analyze_order 调用失败', { error: errorMsg, args });
      return `分析失败: ${errorMsg}`;
    }
  },
});

server.addTool({
  name: "create_order",
  description: "创建订单，返回订单信息和支付二维码",
  parameters: z.object({
    list: z.array(z.object({
      id: z.string().describe("商品ID"),
      name: z.string().describe("商品名称"),
      quantity: z.number().describe("数量")
    })).describe("商品列表"),
    special_requests: z.string().optional().describe("特殊要求"),
    total_amount: z.number().optional().describe("订单总金额"),
    confirm: z.boolean().describe("是否确认下单，设置为true表示明确同意下单")
  }),
  execute: async (args) => {
    logger.info('调用 create_order', args);
    
    if (!args.confirm) {
      const itemList = args.list.map(item => ({
        id: item.id,
        name: item.name,
        quantity: item.quantity
      }));
      return JSON.stringify({
        success: false,
        code: 'NEED_CONFIRM',
        message: '请确认下单信息',
        data: {
          items: itemList,
          total_amount: args.total_amount,
          special_requests: args.special_requests,
          confirm_required: true,
          confirm_hint: '请设置 confirm: true 来确认下单'
        }
      }, null, 2);
    }
    
    try {
      const orderArgs = { ...args };
      delete orderArgs.confirm;
      const result = await axiosInstance.post('/api/order', orderArgs);
      logger.debug('create_order 返回结果', result.data);
      
      const response = {
        success: true,
        code: 'ORDER_CREATED',
        message: '订单创建成功',
        data: {
          order_id: result.data.order_id || '',
          order_no: result.data.order_no || '',
          qr_code: {
            url: result.data.qr_code || '',
            type: 'url',
            tips: '请使用微信或支付宝扫描二维码进行支付'
          },
          total_amount: result.data.total_amount || 0,
          status: result.data.status || 'pending',
          status_text: {
            pending: '待支付',
            paid: '已支付',
            cancelled: '已取消'
          }[result.data.status] || '未知状态',
          create_time: result.data.create_time || new Date().toLocaleString('zh-CN'),
          items: result.data.items || [],
          special_requests: result.data.special_requests || '',
          customer_info: result.data.customer_info || {}
        }
      };
      return JSON.stringify(response, null, 2);
    } catch (error) {
      const errorMsg = getErrorMessage(error);
      logger.error('create_order 调用失败', { error: errorMsg, args });
      return JSON.stringify({
        success: false,
        code: 'ORDER_ERROR',
        message: '创建订单失败',
        data: {
          error: errorMsg,
          items: args.list,
          total_amount: args.total_amount
        }
      }, null, 2);
    }
  },
});

server.addTool({
  name: "check_payment_status",
  description: "查询订单支付状态",
  parameters: z.object({
    orderId: z.string().describe("订单ID")
  }),
  execute: async (args) => {
    logger.info('调用 check_payment_status', args);
    try {
      const result = await axiosInstance.get(`/api/pay/status/${args.orderId}`);
      logger.debug('check_payment_status 返回结果', result.data);
      return JSON.stringify(result.data, null, 2);
    } catch (error) {
      const errorMsg = getErrorMessage(error);
      logger.error('check_payment_status 调用失败', { error: errorMsg, args });
      return `查询支付状态失败: ${errorMsg}`;
    }
  },
});

server.start({
  transportType: "stdio",
});

logger.info('MCP服务已启动，使用stdio模式');

export default server;
