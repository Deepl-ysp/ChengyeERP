<script lang="ts" setup>
import { ref, reactive } from 'vue';
import { API_URL } from '@config/net';

interface OrderItem {
  id: number;
  number: number;
  customer_name: string;
  total_amount: number;
  paid_amount: number;
  unreceived_amount: number;
  order_status: number;
  payment_status: number;
  create_time: string;
}

interface PaymentItem {
  order_number: number;
  amount: number;
  payment_method: number;
  payment_time: string;
  operator: string;
}

interface ReconciliationData {
  customer_number: number;
  customer_name: string;
  customer_phone: string;
  customer_address: string;
  period: { start: string; end: string };
  summary: {
    order_count: number;
    order_total: number;
    paid_total: number;
    unpaid_total: number;
  };
  orders: OrderItem[];
  payments: PaymentItem[];
}

const customers = ref<{ number: number; name: string }[]>([]);
const selectedCustomer = ref<number>(0);
const dateRange = reactive({ start: '', end: '' });
const loading = ref(false);
const data = ref<ReconciliationData | null>(null);

const fetchCustomers = async () => {
  try {
    const res = await fetch(`${API_URL}customer/getForSelect?keyword=&limit=100`);
    const result = await res.json();
    customers.value = (result.list || []).map((c: any) => ({
      number: c.number,
      name: c.name,
    }));
  } catch (e) {
    console.error('获取客户列表失败', e);
  }
};

const fetchReconciliation = async () => {
  if (selectedCustomer.value <= 0) {
    alert('请选择客户');
    return;
  }
  loading.value = true;
  try {
    let url = `${API_URL}finance/receivable/getReconciliation?customer_number=${selectedCustomer.value}`;
    if (dateRange.start) url += `&start_date=${dateRange.start}`;
    if (dateRange.end) url += `&end_date=${dateRange.end}`;
    const res = await fetch(url);
    const result = await res.json();
    if (result.success) {
      data.value = result.data;
    } else {
      alert(result.message || '查询失败');
    }
  } catch (e) {
    console.error('获取对账数据失败', e);
    alert('查询失败');
  } finally {
    loading.value = false;
  }
};

const orderStatusText = (s: number) => ['待处理', '已确认', '已发货', '已完成', '已取消'][s] || '-';
const paymentStatusText = (s: number) => ['未支付', '部分支付', '全额支付'][s] || '-';
const paymentMethodText = (s: number) => ['现金', '微信', '支付宝', '银行转账', '其他'][s] || '-';

fetchCustomers();
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h2>客户对账</h2>
    </div>

    <div class="filter-bar">
      <select v-model.number="selectedCustomer">
        <option :value="0">请选择客户</option>
        <option v-for="c in customers" :key="c.number" :value="c.number">
          {{ c.name }}
        </option>
      </select>
      <input v-model="dateRange.start" type="date" />
      <span>至</span>
      <input v-model="dateRange.end" type="date" />
      <button class="btn-primary" :disabled="loading" @click="fetchReconciliation">
        {{ loading ? '查询中...' : '查询' }}
      </button>
    </div>

    <div v-if="data" class="reconciliation-content">
      <div class="customer-card">
        <h3>客户信息</h3>
        <div class="info-row">
          <span>客户名称：</span>
          <b>{{ data.customer_name }}</b>
        </div>
        <div class="info-row">
          <span>客户编号：</span>
          <b>{{ data.customer_number }}</b>
        </div>
        <div class="info-row">
          <span>联系电话：</span>
          <b>{{ data.customer_phone || '-' }}</b>
        </div>
        <div class="info-row">
          <span>地址：</span>
          <b>{{ data.customer_address || '-' }}</b>
        </div>
        <div class="info-row">
          <span>对账期间：</span>
          <b>{{ data.period.start || '不限' }} ~ {{ data.period.end || '不限' }}</b>
        </div>
      </div>

      <div class="summary-card">
        <h3>对账汇总</h3>
        <div class="summary-grid">
          <div class="summary-item">
            <span class="label">订单数</span>
            <span class="value">{{ data.summary.order_count }}</span>
          </div>
          <div class="summary-item">
            <span class="label">订单总额</span>
            <span class="value total">{{ data.summary.order_total.toFixed(2) }}</span>
          </div>
          <div class="summary-item">
            <span class="label">已付款</span>
            <span class="value paid">{{ data.summary.paid_total.toFixed(2) }}</span>
          </div>
          <div class="summary-item">
            <span class="label">未付款</span>
            <span class="value unpaid">{{ data.summary.unpaid_total.toFixed(2) }}</span>
          </div>
        </div>
      </div>

      <div class="section">
        <h3>订单明细</h3>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>订单号</th>
                <th>订单总额</th>
                <th>已付款</th>
                <th>未付款</th>
                <th>支付状态</th>
                <th>订单状态</th>
                <th>下单时间</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in data.orders" :key="order.id">
                <td>{{ order.number }}</td>
                <td>{{ Number(order.total_amount).toFixed(2) }}</td>
                <td class="income-text">{{ Number(order.paid_amount).toFixed(2) }}</td>
                <td class="warn-text">{{ Number(order.unreceived_amount).toFixed(2) }}</td>
                <td>
                  <span :class="['status-tag', `status-${order.payment_status}`]">
                    {{ paymentStatusText(order.payment_status) }}
                  </span>
                </td>
                <td>{{ orderStatusText(order.order_status) }}</td>
                <td>{{ order.create_time }}</td>
              </tr>
              <tr v-if="data.orders.length === 0">
                <td colspan="7" class="empty">暂无订单记录</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="section">
        <h3>付款记录</h3>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>订单号</th>
                <th>付款金额</th>
                <th>付款方式</th>
                <th>付款时间</th>
                <th>操作人</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(payment, index) in data.payments" :key="index">
                <td>{{ payment.order_number }}</td>
                <td class="income-text">{{ Number(payment.amount).toFixed(2) }}</td>
                <td>{{ paymentMethodText(payment.payment_method) }}</td>
                <td>{{ payment.payment_time }}</td>
                <td>{{ payment.operator || '-' }}</td>
              </tr>
              <tr v-if="data.payments.length === 0">
                <td colspan="5" class="empty">暂无付款记录</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-else-if="!loading" class="empty-state">
      <p>请选择客户并点击查询查看对账信息</p>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.page-container {
  padding: 20px;
  height: 100%;
  overflow-y: auto;
}

.page-header {
  margin-bottom: 20px;

  h2 {
    font-size: 22px;
    color: var(--text-primary);
  }
}

.filter-bar {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-bottom: 20px;

  input,
  select {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: var(--bg-card);
    color: var(--text-primary);
  }

  button {
    padding: 8px 18px;
  }
}

.btn-primary {
  background: #6c5ce7;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

.customer-card,
.summary-card {
  background: var(--bg-card);
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;
  box-shadow: 0 1px 4px var(--shadow-color);

  h3 {
    font-size: 16px;
    margin-bottom: 12px;
    color: var(--text-primary);
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 8px;
  }
}

.customer-card {
  .info-row {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 14px;
    color: var(--text-muted);

    b {
      color: var(--text-primary);
    }
  }
}

.summary-card {
  .summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
  }

  .summary-item {
    text-align: center;
    padding: 12px;
    background: var(--bg-body);
    border-radius: 6px;

    .label {
      display: block;
      font-size: 12px;
      color: var(--text-muted);
      margin-bottom: 4px;
    }

    .value {
      display: block;
      font-size: 20px;
      font-weight: 600;
      color: var(--text-primary);

      &.total {
        color: #3498db;
      }

      &.paid {
        color: #2ecc71;
      }

      &.unpaid {
        color: #f39c12;
      }
    }
  }
}

.section {
  margin-bottom: 20px;

  h3 {
    font-size: 16px;
    margin-bottom: 12px;
    color: var(--text-primary);
  }
}

.table-wrap {
  background: var(--bg-card);
  border-radius: 6px;
  box-shadow: 0 1px 4px var(--shadow-color);
  overflow: hidden;

  table {
    width: 100%;
    border-collapse: collapse;

    th {
      background: var(--table-header-bg);
      color: var(--table-header-text);
      padding: 10px;
      text-align: left;
      font-size: 13px;
    }

    td {
      padding: 10px;
      border-bottom: 1px solid var(--border-color);
      font-size: 13px;
    }

    tr:hover td {
      background: var(--table-row-hover);
    }
  }
}

.empty {
  text-align: center;
  padding: 30px;
  color: var(--text-muted);
}

.empty-state {
  text-align: center;
  padding: 60px;
  color: var(--text-muted);
  font-size: 16px;
}

.income-text {
  color: #2ecc71;
  font-weight: 600;
}

.warn-text {
  color: #f39c12;
  font-weight: 600;
}

.status-tag {
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 12px;
  color: white;

  &.status-0 {
    background: #f39c12;
  }

  &.status-1 {
    background: #3498db;
  }

  &.status-2 {
    background: #2ecc71;
  }
}
</style>