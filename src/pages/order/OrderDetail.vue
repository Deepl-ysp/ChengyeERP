<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { API_URL } from '@config/net';
import { SELLER_ADDRESS, SELLER_PHONE } from '@config/seller';
import { confirm } from '@config/confirmSettings';

// API 响应类型
interface OrderItemApiResponse {
  product_name?: string;
  specification?: string;
  unit?: string;
  quantity?: number | string;
  unit_price?: number | string;
  subtotal?: number | string;
  deductions?: {
    product_number?: number | string;
    product_name?: string;
    specification?: string;
    warehouse_name?: string;
    deducted_quantity?: number | string;
  }[];
}

interface PaymentApiResponse {
  number?: number | string;
  amount?: number | string;
  payment_method?: number | string;
  payment_time?: string;
  operator?: string;
  remark?: string;
}

// 订单类型
type OrderType = 'supply' | 'retail';

const orderTypeMap: Record<OrderType, string> = {
  supply: '供货',
  retail: '零售',
};

// 支付状态
enum PaymentStatus {
  Unpaid = 0,
  Partial = 1,
  Paid = 2,
}

const paymentStatusMap: Record<PaymentStatus, string> = {
  [PaymentStatus.Unpaid]: '未支付',
  [PaymentStatus.Partial]: '部分支付',
  [PaymentStatus.Paid]: '全额支付',
};

// 订单状态
enum OrderStatus {
  Pending = 0,
  Confirmed = 1,
  Shipped = 2,
  Completed = 3,
  Cancelled = 4,
}

const orderStatusMap: Record<OrderStatus, string> = {
  [OrderStatus.Pending]: '待处理',
  [OrderStatus.Confirmed]: '已确认',
  [OrderStatus.Shipped]: '已发货',
  [OrderStatus.Completed]: '已完成',
  [OrderStatus.Cancelled]: '已取消',
};

// 订单明细（按名称+规格展示，不关心具体是哪个产品）
interface OrderDetailItem {
  product_name: string;
  specification: string;
  unit: string;
  quantity: number;
  unit_price: number;
  subtotal: number;
  // 实际扣减明细（可能多个产品凑出来）
  deductions?: {
    product_number: number;
    product_name: string;
    specification: string;
    warehouse_name: string;
    deducted_quantity: number;
  }[];
}

// 支付记录
interface PaymentRecord {
  number: number;
  amount: number;
  payment_method: number;
  payment_time: string;
  operator: string;
  remark: string;
}

const paymentMethodMap: Record<number, string> = {
  0: '现金',
  1: '微信',
  2: '支付宝',
  3: '银行转账',
  4: '其他',
};

const paymentMethodOptions = [
  { value: 0, label: '现金' },
  { value: 1, label: '微信' },
  { value: 2, label: '支付宝' },
  { value: 3, label: '银行转账' },
  { value: 4, label: '其他' },
];

// 订单基本信息
const orderInfo = reactive({
  number: 0,
  order_type: 'supply' as OrderType,
  customer_name: '',
  customer_number: 0,
  seller_name: '',
  receiver_name: '',
  receiver_address: '',
  receiver_phone: '',
  total_quantity: 0,
  total_amount: 0,
  paid_amount: 0,
  unpaid_amount: 0,
  payment_status: PaymentStatus.Unpaid,
  order_status: OrderStatus.Pending,
  remark: '',
  image_path: '',
  create_time: '',
  update_time: '',
});

const items = ref<OrderDetailItem[]>([]);
const payments = ref<PaymentRecord[]>([]);
const loading = ref(false);

const route = useRoute();
const router = useRouter();

const orderNumber = route.params.number || '';

// 获取订单详情
const fetchOrderDetail = async () => {
  loading.value = true;
  try {
    const res = await fetch(`${API_URL}v1/api/order/getDetail?number=${orderNumber}`);
    const data = await res.json();
    if (data.success) {
      const o = data.order || {};
      orderInfo.number = Number(o.number || 0);
      orderInfo.order_type = (o.order_type === 'retail' ? 'retail' : 'supply') as OrderType;
      orderInfo.customer_name = o.customer_name || '';
      orderInfo.customer_number = Number(o.customer_number || 0);
      orderInfo.seller_name = o.seller_name || '';
      orderInfo.receiver_name = o.receiver_name || '';
      orderInfo.receiver_address = o.receiver_address || '';
      orderInfo.receiver_phone = o.receiver_phone || '';
      orderInfo.total_quantity = Number(o.total_quantity || 0);
      orderInfo.total_amount = Number(o.total_amount || 0);
      orderInfo.paid_amount = Number(o.paid_amount || 0);
      orderInfo.unpaid_amount = Number(o.unpaid_amount || 0);
      orderInfo.payment_status = Number(o.payment_status) as PaymentStatus;
      orderInfo.order_status = Number(o.order_status) as OrderStatus;
      orderInfo.remark = o.remark || '';
      orderInfo.image_path = o.image_path || '';
      orderInfo.create_time = o.create_time || '';
      orderInfo.update_time = o.update_time || '';

      items.value = (data.items || []).map((it: OrderItemApiResponse) => ({
        product_name: it.product_name || '',
        specification: it.specification || '',
        unit: it.unit || '件',
        quantity: Number(it.quantity || 0),
        unit_price: Number(it.unit_price || 0),
        subtotal: Number(it.subtotal || 0),
        deductions: (it.deductions || []).map((d) => ({
          product_number: Number(d.product_number || 0),
          product_name: d.product_name || it.product_name || '',
          specification: d.specification || '',
          warehouse_name: d.warehouse_name || '未知仓库',
          deducted_quantity: Number(d.deducted_quantity || 0),
        })),
      }));

      payments.value = (data.payments || []).map((p: PaymentApiResponse) => ({
        number: Number(p.number || 0),
        amount: Number(p.amount || 0),
        payment_method: Number(p.payment_method ?? 0),
        payment_time: p.payment_time || '',
        operator: p.operator || '',
        remark: p.remark || '',
      }));
    } else {
      alert(data.message || '获取订单详情失败');
    }
  } catch (err) {
    console.error('获取订单详情失败', err);
    alert('获取订单详情失败，请检查网络');
  } finally {
    loading.value = false;
  }
};

// 打印订单（基于订单数据重新渲染打印模板）
const printOrder = () => {
  const rowsHtml = items.value.map((it, idx) => `
    <tr>
      <td class="center">${idx + 1}</td>
      <td>${escapeHtml(it.product_name)}</td>
      <td class="center">${escapeHtml(it.specification || '-')}</td>
      <td class="center">${escapeHtml(it.unit)}</td>
      <td class="right">${it.quantity}</td>
      <td class="right">¥${it.unit_price.toFixed(2)}</td>
      <td class="right">¥${it.subtotal.toFixed(2)}</td>
    </tr>
  `).join('');

  const printWindow = window.open('', '_blank', 'width=800,height=600');
  if (!printWindow) {
    alert('请允许弹出窗口以打印订单');
    return;
  }
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>订单打印 - ${escapeHtml(orderInfo.customer_name)}</title>
      <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { padding: 30px; font-family: "Microsoft YaHei", "SimSun", Arial, sans-serif; color: #000; }
        .print-header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 12px; }
        .print-title { font-size: 24px; font-weight: bold; letter-spacing: 2px; }
        .print-subtitle { font-size: 14px; color: #555; margin-top: 4px; }
        .print-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 20px; margin-bottom: 16px; font-size: 13px; }
        .print-info-row { display: flex; }
        .print-info-row.full-row { grid-column: span 2; }
        .print-label { color: #555; min-width: 80px; }
        .print-value { color: #000; flex: 1; font-weight: 500; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 13px; }
        th, td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
        thead th { background: #f0f0f0; font-weight: bold; text-align: center; }
        .center { text-align: center; }
        .right { text-align: right; }
        tfoot td { background: #f0f0f0; font-weight: bold; }
        tfoot .total-amount { color: #c00; font-size: 15px; }
        .print-footer { margin-top: 20px; padding-top: 12px; border-top: 1px dashed #999; font-size: 12px; color: #555; }
        .signature-row { display: flex; justify-content: space-between; margin-top: 30px; font-size: 13px; color: #000; }
        @media print { body { padding: 0; } @page { margin: 10mm; } }
      </style>
    </head>
    <body>
      <div class="print-header">
        <div class="print-title">${escapeHtml(orderInfo.seller_name || '销货清单')}销货清单</div>
        <div class="print-subtitle">${orderInfo.order_type === 'supply' ? '供货单' : '零售单'}</div>
      </div>
      <div class="print-info-grid">
        <div class="print-info-row"><span class="print-label">客户单位：</span><span class="print-value">${escapeHtml(orderInfo.customer_name || '-')}</span></div>
        <div class="print-info-row"><span class="print-label">收款单位：</span><span class="print-value">${escapeHtml(orderInfo.seller_name || '-')}</span></div>
        <div class="print-info-row"><span class="print-label">收货单位：</span><span class="print-value">${escapeHtml(orderInfo.receiver_name || '-')}</span></div>
        <div class="print-info-row"><span class="print-label">联系电话：</span><span class="print-value">${escapeHtml(orderInfo.receiver_phone || '-')}</span></div>
        <div class="print-info-row full-row"><span class="print-label">收货地址：</span><span class="print-value">${escapeHtml(orderInfo.receiver_address || '-')}</span></div>
        <div class="print-info-row full-row"><span class="print-label">创建时间：</span><span class="print-value">${escapeHtml(orderInfo.create_time || '-')}</span></div>
      </div>
      <table>
        <thead>
          <tr>
            <th>序号</th><th>商品名称</th><th>规格</th><th>单位</th><th>数量</th><th>单价</th><th>金额</th>
          </tr>
        </thead>
        <tbody>${rowsHtml}</tbody>
        <tfoot>
          <tr>
            <td colspan="4" class="right">合计</td>
            <td class="right">${orderInfo.total_quantity}</td>
            <td></td>
            <td class="right total-amount">¥${orderInfo.total_amount.toFixed(2)}</td>
          </tr>
        </tfoot>
      </table>
      <div class="print-footer">
        <div>收款单位地址：${escapeHtml(SELLER_ADDRESS)}</div>
        <div>收款单位电话：${escapeHtml(SELLER_PHONE)}</div>
        <div>备注：${escapeHtml(orderInfo.remark || '-')}</div>
        <div class="signature-row">
          <div>客户签字：____________</div>
          <div>经办人签字：____________</div>
        </div>
      </div>
    </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.onload = () => {
    setTimeout(() => printWindow.print(), 300);
  };
};

// 简单 HTML 转义，防止打印时 XSS
const escapeHtml = (s: string): string => {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
};

// ===== 新增收款弹窗 =====
const paymentModalVisible = ref(false);
const paymentSubmitting = ref(false);
const paymentForm = reactive({
  amount: 0,
  payment_method: 0,
  remark: '',
});

const openPaymentModal = () => {
  // 默认填入未付金额
  paymentForm.amount = orderInfo.unpaid_amount > 0 ? orderInfo.unpaid_amount : 0;
  paymentForm.payment_method = 0;
  paymentForm.remark = '';
  paymentModalVisible.value = true;
};

const closePaymentModal = () => {
  paymentModalVisible.value = false;
};

// 提交支付记录
const submitPayment = async () => {
  if (paymentForm.amount <= 0) {
    alert('支付金额必须大于0');
    return;
  }
  if (paymentForm.amount > orderInfo.unpaid_amount) {
    if (!confirm(`本次支付金额 ${paymentForm.amount} 超过未付金额 ${orderInfo.unpaid_amount}，确认提交吗？`, 'order.paymentExceed')) {
      return;
    }
  }
  paymentSubmitting.value = true;
  try {
    const res = await fetch(`${API_URL}v1/api/order/payment/set`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        order_number: orderInfo.number,
        amount: paymentForm.amount,
        payment_method: paymentForm.payment_method,
        remark: paymentForm.remark,
        operator: '系统',
      }),
    });
    const data = await res.json();
    if (data.success) {
      alert('收款成功');
      paymentModalVisible.value = false;
      fetchOrderDetail();
    } else {
      alert(`收款失败：${data.message || '未知错误'}`);
    }
  } catch (err) {
    console.error('新增收款失败', err);
    alert('收款失败，请检查网络');
  } finally {
    paymentSubmitting.value = false;
  }
};

// 更新订单状态
const updateOrderStatus = async (status: OrderStatus) => {
  const statusText = orderStatusMap[status];
  if (!confirm(`确认将订单状态更新为"${statusText}"吗？`, 'order.updateStatus')) return;
  try {
    const res = await fetch(`${API_URL}v1/api/order/updateStatus`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ number: orderInfo.number, order_status: status }),
    });
    const data = await res.json();
    if (data.success) {
      fetchOrderDetail();
    } else {
      alert(`状态更新失败：${data.message || '未知错误'}`);
    }
  } catch (err) {
    console.error('更新订单状态失败', err);
    alert('状态更新失败，请检查网络');
  }
};

onMounted(() => {
  if (orderNumber) {
    fetchOrderDetail();
  }
});
</script>

<template>
  <div class="MainBox">
    <div class="page-header">
      <div class="header-left">
        <button class="btn btn-back" @click="router.back()">
          ← 返回
        </button>
        <h2>订单详情</h2>
        <span class="order-no">订单号：{{ orderInfo.number }}</span>
      </div>
      <div class="header-actions">
        <button class="btn btn-edit" @click="router.push(`/order/edit/${orderInfo.number}`)">✏ 修改订单</button>
        <button class="btn btn-secondary" @click="printOrder">🖨 打印</button>
        <button
          v-if="orderInfo.order_status === OrderStatus.Pending"
          class="btn btn-primary"
          @click="updateOrderStatus(OrderStatus.Confirmed)"
        >确认订单</button>
        <button
          v-if="orderInfo.order_status === OrderStatus.Confirmed"
          class="btn btn-primary"
          @click="updateOrderStatus(OrderStatus.Shipped)"
        >标记发货</button>
        <button
          v-if="orderInfo.order_status === OrderStatus.Shipped"
          class="btn btn-primary"
          @click="updateOrderStatus(OrderStatus.Completed)"
        >标记完成</button>
        <button
          v-if="orderInfo.order_status !== OrderStatus.Cancelled && orderInfo.order_status !== OrderStatus.Completed"
          class="btn btn-cancel"
          @click="updateOrderStatus(OrderStatus.Cancelled)"
        >取消订单</button>
      </div>
    </div>

    <div v-if="loading" class="loading-wrap">加载中...</div>

    <div v-else class="detail-container">
      <!-- 订单基本信息 -->
      <div class="info-card">
        <div class="card-title">订单信息</div>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">订单类型：</span>
            <span class="type-tag" :class="`type-${orderInfo.order_type}`">
              {{ orderTypeMap[orderInfo.order_type] }}
            </span>
          </div>
          <div class="info-item">
            <span class="info-label">客户单位：</span>
            <span class="info-value">{{ orderInfo.customer_name || '散客' }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">收款单位：</span>
            <span class="info-value">{{ orderInfo.seller_name || '-' }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">收货单位：</span>
            <span class="info-value">{{ orderInfo.receiver_name || '-' }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">联系电话：</span>
            <span class="info-value">{{ orderInfo.receiver_phone || '无' }}</span>
          </div>
          <div class="info-item info-item-full">
            <span class="info-label">收货地址：</span>
            <span class="info-value">{{ orderInfo.receiver_address || '-' }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">订单状态：</span>
            <span class="status-tag" :class="`status-${orderInfo.order_status}`">
              {{ orderStatusMap[orderInfo.order_status] }}
            </span>
          </div>
          <div class="info-item">
            <span class="info-label">支付状态：</span>
            <span class="status-tag" :class="`payment-${orderInfo.payment_status}`">
              {{ paymentStatusMap[orderInfo.payment_status] }}
            </span>
          </div>
          <div class="info-item">
            <span class="info-label">创建时间：</span>
            <span class="info-value">{{ orderInfo.create_time || '-' }}</span>
          </div>
          <div class="info-item info-item-full">
            <span class="info-label">备注：</span>
            <span class="info-value">{{ orderInfo.remark || '-' }}</span>
          </div>
        </div>
      </div>

      <!-- 金额汇总 -->
      <div class="amount-card">
        <div class="amount-row">
          <span class="amount-label">商品总数：</span>
          <span class="amount-value">{{ orderInfo.total_quantity }}</span>
        </div>
        <div class="amount-row">
          <span class="amount-label">订单总额：</span>
          <span class="amount-value amount-total">¥{{ orderInfo.total_amount.toFixed(2) }}</span>
        </div>
        <div class="amount-row">
          <span class="amount-label">已付金额：</span>
          <span class="amount-value amount-paid">¥{{ orderInfo.paid_amount.toFixed(2) }}</span>
        </div>
        <div class="amount-row">
          <span class="amount-label">未付金额：</span>
          <span class="amount-value amount-unpaid">¥{{ orderInfo.unpaid_amount.toFixed(2) }}</span>
        </div>
        <button
          v-if="orderInfo.payment_status !== PaymentStatus.Paid && orderInfo.order_status !== OrderStatus.Cancelled"
          class="btn btn-primary btn-pay"
          @click="openPaymentModal"
        >+ 新增收款</button>
      </div>

      <!-- 商品明细 -->
      <div class="items-card">
        <div class="card-title">商品明细</div>
        <table class="items-table">
          <thead>
            <tr>
              <th>商品名称</th>
              <th>规格</th>
              <th>单位</th>
              <th>数量</th>
              <th>单价</th>
              <th>金额</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in items" :key="idx">
              <td>{{ item.product_name }}</td>
              <td>{{ item.specification || '-' }}</td>
              <td>{{ item.unit }}</td>
              <td>{{ item.quantity }}</td>
              <td>¥{{ item.unit_price.toFixed(2) }}</td>
              <td class="subtotal-col">¥{{ item.subtotal.toFixed(2) }}</td>
            </tr>
            <tr v-if="items.length === 0">
              <td colspan="6" class="empty-text">暂无商品数据</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" class="total-label">合计</td>
              <td class="total-qty">{{ orderInfo.total_quantity }}</td>
              <td></td>
              <td class="total-amount">¥{{ orderInfo.total_amount.toFixed(2) }}</td>
            </tr>
          </tfoot>
        </table>

        <!-- 扣减明细（折叠展示，显示实际从哪些产品/仓库扣的） -->
        <div v-if="items.some(i => i.deductions && i.deductions.length > 1)" class="deduction-note">
          <div class="note-title">📦 库存扣减说明</div>
          <div class="note-desc">以下商品由多个同名同规格产品凑单出库：</div>
          <div v-for="(item, idx) in items" :key="`d-${idx}`" class="deduction-group" v-show="item.deductions && item.deductions.length > 1">
            <div class="deduction-product">{{ item.product_name }}（{{ item.specification || '无规格' }}）</div>
            <div class="deduction-list">
              <div v-for="(d, di) in item.deductions" :key="di" class="deduction-item">
                <span>产品编号：{{ d.product_number }}</span>
                <span>仓库：{{ d.warehouse_name }}</span>
                <span>扣减：{{ d.deducted_quantity }} {{ item.unit }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 支付记录 -->
      <div class="payment-card">
        <div class="card-title">支付记录</div>
        <table class="payment-table">
          <thead>
            <tr>
              <th>支付单号</th>
              <th>支付方式</th>
              <th>支付金额</th>
              <th>支付时间</th>
              <th>操作人</th>
              <th>备注</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(p, idx) in payments" :key="idx">
              <td>{{ p.number }}</td>
              <td>{{ paymentMethodMap[p.payment_method] || '-' }}</td>
              <td class="amount-col">¥{{ p.amount.toFixed(2) }}</td>
              <td>{{ p.payment_time }}</td>
              <td>{{ p.operator }}</td>
              <td>{{ p.remark || '-' }}</td>
            </tr>
            <tr v-if="payments.length === 0">
              <td colspan="6" class="empty-text">暂无支付记录</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 订单图片说明：图片保存在后端绝对路径，前端不直接展示，可通过"打印"按钮重新生成打印件 -->
    </div>

    <!-- 新增收款弹窗 -->
    <div v-if="paymentModalVisible" class="payment-overlay" @click.self="closePaymentModal">
      <div class="payment-modal">
        <div class="payment-header">
          <h3>新增收款</h3>
          <button class="btn-close" @click="closePaymentModal">×</button>
        </div>
        <div class="payment-body">
          <div class="payment-info-row">
            <span>订单总额：</span>
            <span class="amount">¥{{ orderInfo.total_amount.toFixed(2) }}</span>
          </div>
          <div class="payment-info-row">
            <span>已付金额：</span>
            <span class="amount paid">¥{{ orderInfo.paid_amount.toFixed(2) }}</span>
          </div>
          <div class="payment-info-row">
            <span>未付金额：</span>
            <span class="amount unpaid">¥{{ orderInfo.unpaid_amount.toFixed(2) }}</span>
          </div>

          <div class="payment-form-group">
            <label>本次收款金额 <span class="required">*</span></label>
            <input
              v-model.number="paymentForm.amount"
              type="number"
              step="0.01"
              min="0"
              class="payment-input"
              placeholder="请输入金额"
            />
          </div>

          <div class="payment-form-group">
            <label>支付方式</label>
            <select v-model.number="paymentForm.payment_method" class="payment-input">
              <option v-for="opt in paymentMethodOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>

          <div class="payment-form-group">
            <label>备注</label>
            <textarea v-model="paymentForm.remark" rows="2" class="payment-input" placeholder="选填"></textarea>
          </div>
        </div>
        <div class="payment-footer">
          <button class="btn btn-cancel" @click="closePaymentModal">取消</button>
          <button class="btn btn-primary" :disabled="paymentSubmitting" @click="submitPayment">
            {{ paymentSubmitting ? '提交中...' : '确认收款' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.MainBox {
  padding: 1.5rem;
  background-color: var(--bg-body);
  height: 100%;
  overflow-y: auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  .header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    h2 {
      font-size: 1.5rem;
      color: var(--text-primary);
      margin: 0;
    }
    .order-no {
      font-size: 0.9rem;
      color: var(--text-muted);
      background: var(--bg-button);
      padding: 0.2rem 0.6rem;
      border-radius: 4px;
    }
  }
  .header-actions {
    display: flex;
    gap: 0.6rem;
  }
}

.btn {
  padding: 0.45rem 1.2rem;
  border-radius: 4px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  font-size: 0.9rem;
  transition: all 0.2s;
  &.btn-primary {
    background: var(--btn-primary-bg);
    color: var(--btn-primary-text);
    &:hover {
      background: var(--btn-primary-hover);
      box-shadow: 0 2px 8px rgba(74, 144, 217, 0.3);
    }
  }
  &.btn-secondary {
    background: var(--bg-button);
    color: var(--text-primary);
    &:hover {
      background: var(--bg-button-hover);
    }
  }
  &.btn-edit {
    background: #fefcbf;
    color: #744210;
    &:hover {
      background: #faf089;
    }
  }
  &.btn-back {
    background: transparent;
    color: var(--link-color);
    padding: 0.3rem 0.6rem;
    &:hover {
      background: var(--bg-button);
    }
  }
  &.btn-cancel {
    background: #fed7d7;
    color: #742a2a;
    &:hover { background: #feb2b2; }
  }
}

.detail-container {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.info-card,
.amount-card,
.items-card,
.payment-card {
  background: var(--bg-card);
  border-radius: 8px;
  box-shadow: 0 2px 8px var(--shadow-color);
  overflow: hidden;
}

.card-title {
  padding: 0.8rem 1rem;
  font-weight: 600;
  color: var(--text-primary);
  background: var(--table-header-bg);
  color: var(--table-header-text);
  border-bottom: 1px solid var(--border-color);
}

/* 订单信息 */
.info-grid {
  padding: 1rem;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.8rem 1.5rem;
}

.info-item {
  display: flex;
  align-items: center;
  font-size: 0.9rem;
  &.info-item-full {
    grid-column: span 2;
  }
  .info-label {
    color: var(--text-muted);
    min-width: 80px;
  }
  .info-value {
    color: var(--text-primary);
    flex: 1;
  }
}

.status-tag {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;

  &.status-0 { background: #e2e8f0; color: #4a5568; }
  &.status-1 { background: #bee3f8; color: #2a4365; }
  &.status-2 { background: #fefcbf; color: #744210; }
  &.status-3 { background: #c6f6d5; color: #22543d; }
  &.status-4 { background: #e2e8f0; color: #718096; }

  &.payment-0 { background: #fed7d7; color: #742a2a; }
  &.payment-1 { background: #fefcbf; color: #744210; }
  &.payment-2 { background: #c6f6d5; color: #22543d; }
}

.type-tag {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;

  &.type-supply {
    background: #bee3f8;
    color: #2a4365;
  }
  &.type-retail {
    background: #fefcbf;
    color: #744210;
  }
}

/* 金额汇总 */
.amount-card {
  padding: 1rem;
  display: flex;
  align-items: center;
  gap: 2rem;
  flex-wrap: wrap;
}

.amount-row {
  display: flex;
  align-items: center;
  font-size: 0.9rem;
  .amount-label {
    color: var(--text-muted);
  }
  .amount-value {
    color: var(--text-primary);
    font-weight: 500;
    margin-left: 0.3rem;
  }
  .amount-total {
    font-size: 1.1rem;
    font-weight: 700;
  }
  .amount-paid {
    color: #38a169;
  }
  .amount-unpaid {
    color: #e53e3e;
  }
}

.btn-pay {
  margin-left: auto;
}

/* 商品明细 */
.items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
  color: var(--text-primary);

  thead {
    background: var(--bg-button);
    th {
      padding: 0.6rem 0.8rem;
      text-align: center;
      font-weight: 600;
      border-bottom: 2px solid var(--border-color);
    }
  }

  tbody {
    td {
      padding: 0.6rem 0.8rem;
      text-align: center;
      border-bottom: 1px solid var(--border-color);
      &.subtotal-col { font-weight: 500; }
    }
    .empty-text {
      color: var(--text-muted);
      padding: 2rem 0;
      text-align: center;
    }
  }

  tfoot {
    background: var(--bg-body);
    font-weight: 600;
    td {
      padding: 0.6rem 0.8rem;
      text-align: center;
      &.total-label { text-align: right; }
      &.total-qty { color: var(--link-color); }
      &.total-amount {
        color: #e53e3e;
        font-size: 1rem;
      }
    }
  }
}

/* 扣减明细 */
.deduction-note {
  padding: 1rem;
  border-top: 1px solid var(--border-color);
  background: #f7fafc;

  .note-title {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.3rem;
  }
  .note-desc {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 0.8rem;
  }
  .deduction-group {
    margin-bottom: 0.6rem;
    .deduction-product {
      font-size: 0.85rem;
      font-weight: 500;
      color: #2c5282;
      margin-bottom: 0.3rem;
    }
    .deduction-list {
      padding-left: 1rem;
    }
    .deduction-item {
      display: flex;
      gap: 1rem;
      font-size: 0.8rem;
      color: var(--text-muted);
      padding: 0.2rem 0;
    }
  }
}

/* 支付记录 */
.payment-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
  color: var(--text-primary);

  thead {
    background: var(--bg-button);
    th {
      padding: 0.6rem 0.8rem;
      text-align: center;
      font-weight: 600;
      border-bottom: 2px solid var(--border-color);
    }
  }

  tbody {
    td {
      padding: 0.6rem 0.8rem;
      text-align: center;
      border-bottom: 1px solid var(--border-color);
      &.amount-col {
        color: #38a169;
        font-weight: 500;
      }
    }
    .empty-text {
      color: var(--text-muted);
      padding: 2rem 0;
      text-align: center;
    }
  }
}

.loading-wrap {
  text-align: center;
  padding: 3rem;
  color: var(--text-muted);
}

/* ===== 收款弹窗 ===== */
.payment-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.payment-modal {
  background: var(--bg-card);
  border-radius: 8px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  width: 90%;
  max-width: 460px;
  overflow: hidden;
}

.payment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.8rem 1.2rem;
  background: var(--table-header-bg);
  color: var(--table-header-text);
  border-bottom: 1px solid var(--border-color);
  h3 { margin: 0; font-size: 1.1rem; }
  .btn-close {
    background: transparent;
    color: var(--table-header-text);
    border: none;
    cursor: pointer;
    font-size: 1.4rem;
    line-height: 1;
    &:hover { opacity: 0.7; }
  }
}

.payment-body {
  padding: 1.2rem;
}

.payment-info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.3rem 0;
  font-size: 0.9rem;
  color: var(--text-muted);
  .amount {
    color: var(--text-primary);
    font-weight: 500;
    &.paid { color: #38a169; }
    &.unpaid { color: #e53e3e; font-weight: 600; }
  }
}

.payment-form-group {
  margin-top: 0.8rem;
  label {
    display: block;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.3rem;
    .required { color: #e53e3e; margin-left: 2px; }
  }
  .payment-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: var(--bg-body);
    color: var(--text-primary);
    font-size: 0.9rem;
    font-family: inherit;
    &:focus {
      outline: none;
      border-color: var(--link-color);
      box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.15);
    }
  }
  textarea.payment-input {
    resize: vertical;
  }
}

.payment-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.6rem;
  padding: 0.8rem 1.2rem;
  border-top: 1px solid var(--border-color);
  background: var(--bg-body);
}
</style>
