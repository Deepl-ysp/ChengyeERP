<script lang="ts" setup>
import { ref, onMounted } from 'vue';
import { API_URL } from '@config/net';

interface ReceivableItem {
  id: number;
  number: number;
  customer_number: number;
  customer_name: string;
  total_amount: number;
  paid_amount: number;
  unreceived_amount: number;
  payment_status: number;
  order_status: number;
  create_time: string;
  last_payment_time: string | null;
}

interface ByCustomerItem {
  customer_number: number;
  customer_name: string;
  order_count: number;
  total_amount: number;
  paid_amount: number;
  unreceived_amount: number;
}

const list = ref<ReceivableItem[]>([]);
const total = ref(0);
const page = ref(1);
const size = ref(10);
const keyword = ref('');
const paymentStatus = ref('');
const sumTotal = ref(0);
const sumPaid = ref(0);
const sumUnreceived = ref(0);
const byCustomer = ref<ByCustomerItem[]>([]);
const showReceiveModal = ref(false);
const currentItem = ref<ReceivableItem | null>(null);
const receiveAmount = ref(0);
const receiveMethod = ref(0);
const receiveRemark = ref('');

const fetchList = async () => {
  try {
    let url = `${API_URL}finance/receivable/getList?page=${page.value}&size=${size.value}`;
    if (keyword.value) url += `&keyword=${encodeURIComponent(keyword.value)}`;
    if (paymentStatus.value) url += `&payment_status=${paymentStatus.value}`;
    const res = await fetch(url);
    const data = await res.json();
    list.value = data.list || [];
    total.value = data.total || 0;
    sumTotal.value = data.sum_total || 0;
    sumPaid.value = data.sum_paid || 0;
    sumUnreceived.value = data.sum_unreceived || 0;
  } catch (e) {
    console.error('获取应收列表失败', e);
  }
};

const fetchByCustomer = async () => {
  try {
    const res = await fetch(`${API_URL}finance/receivable/getByCustomer`);
    const data = await res.json();
    byCustomer.value = data.list || [];
  } catch (e) {
    console.error('获取客户汇总失败', e);
  }
};

const openReceive = (item: ReceivableItem) => {
  currentItem.value = item;
  receiveAmount.value = item.unreceived_amount;
  receiveMethod.value = 0;
  receiveRemark.value = '';
  showReceiveModal.value = true;
};

const submitReceive = async () => {
  if (!currentItem.value) return;
  if (receiveAmount.value <= 0) {
    alert('请输入收款金额');
    return;
  }
  if (receiveAmount.value > currentItem.value.unreceived_amount + 0.01) {
    alert('收款金额超出未收金额');
    return;
  }
  try {
    const res = await fetch(`${API_URL}finance/flow/set`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        flow_type: 0, // 客户收款
        party_type: 0, // 客户
        party_number: currentItem.value.customer_number,
        party_name: currentItem.value.customer_name,
        related_number: currentItem.value.number,
        amount: receiveAmount.value,
        payment_method: receiveMethod.value,
        operator: 'admin',
        remark: receiveRemark.value,
      }),
    });
    const data = await res.json();
    if (data.success) {
      alert('收款记录成功');
      showReceiveModal.value = false;
      fetchList();
    } else {
      alert(data.message || '收款失败');
    }
  } catch (e) {
    console.error('收款失败', e);
  }
};

onMounted(() => {
  fetchList();
  fetchByCustomer();
});

const statusText = (s: number) => ['未支付', '部分支付', '全额支付'][s] || '-';
const orderStatusText = (s: number) => ['待处理', '已确认', '已发货', '已完成', '已取消'][s] || '-';
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h2>应收账款</h2>
      <div class="summary-bar">
        <span>订单总额：<b>{{ sumTotal.toFixed(2) }}</b></span>
        <span>已收款：<b class="income-text">{{ sumPaid.toFixed(2) }}</b></span>
        <span>未收款：<b class="warn-text">{{ sumUnreceived.toFixed(2) }}</b></span>
      </div>
    </div>

    <div class="filter-bar">
      <input v-model="keyword" type="text" placeholder="搜索客户名或订单号" @keyup.enter="fetchList" />
      <select v-model="paymentStatus" @change="fetchList">
        <option value="">全部状态</option>
        <option value="0">未支付</option>
        <option value="1">部分支付</option>
        <option value="2">全额支付</option>
      </select>
      <button class="btn-primary" @click="fetchList">查询</button>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>订单号</th>
            <th>客户</th>
            <th>订单总额</th>
            <th>已收款</th>
            <th>未收款</th>
            <th>收款率</th>
            <th>支付状态</th>
            <th>订单状态</th>
            <th>最近收款</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in list" :key="item.id">
            <td>{{ item.number }}</td>
            <td>{{ item.customer_name }}</td>
            <td>{{ Number(item.total_amount).toFixed(2) }}</td>
            <td class="income-text">{{ Number(item.paid_amount).toFixed(2) }}</td>
            <td class="warn-text">{{ Number(item.unreceived_amount).toFixed(2) }}</td>
            <td>{{ ((Number(item.paid_amount) / Number(item.total_amount)) * 100).toFixed(1) }}%</td>
            <td>
              <span :class="['status-tag', `status-${item.payment_status}`]">
                {{ statusText(item.payment_status) }}
              </span>
            </td>
            <td>{{ orderStatusText(item.order_status) }}</td>
            <td>{{ item.last_payment_time || '-' }}</td>
            <td>
              <button v-if="item.unreceived_amount > 0" @click="openReceive(item)">登记收款</button>
            </td>
          </tr>
          <tr v-if="list.length === 0">
            <td colspan="10" class="empty">暂无应收记录</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="">
      <button :disabled="page <= 1" @click="page--; fetchList()">上一页</button>
      <span>{{ page }} / {{ Math.max(1, Math.ceil(total / size)) }}</span>
      <button :disabled="page >= Math.ceil(total / size)" @click="page++; fetchList()">下一页</button>
    </div>

    <div v-if="byCustomer.length > 0" class="by-customer">
      <h3>按客户汇总</h3>
      <table>
        <thead>
          <tr>
            <th>客户</th>
            <th>订单数</th>
            <th>总额</th>
            <th>已收</th>
            <th>未收</th>
            <th>收款率</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in byCustomer" :key="item.customer_number">
            <td>{{ item.customer_name }}</td>
            <td>{{ item.order_count }}</td>
            <td>{{ Number(item.total_amount).toFixed(2) }}</td>
            <td class="income-text">{{ Number(item.paid_amount).toFixed(2) }}</td>
            <td class="warn-text">{{ Number(item.unreceived_amount).toFixed(2) }}</td>
            <td>{{ ((Number(item.paid_amount) / Number(item.total_amount)) * 100).toFixed(1) }}%</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="modal" v-if="showReceiveModal" @click.self="showReceiveModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>登记收款</h3>
          <button @click="showReceiveModal = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>订单号</label>
            <input type="text" :value="currentItem?.number" disabled />
          </div>
          <div class="form-group">
            <label>客户</label>
            <input type="text" :value="currentItem?.customer_name" disabled />
          </div>
          <div class="form-group">
            <label>未收金额</label>
            <input type="text" :value="currentItem?.unreceived_amount.toFixed(2)" disabled />
          </div>
          <div class="form-group">
            <label>本次收款 *</label>
            <input type="number" step="0.01" v-model.number="receiveAmount" />
          </div>
          <div class="form-group">
            <label>收款方式</label>
            <select v-model.number="receiveMethod">
              <option :value="0">现金</option>
              <option :value="1">微信</option>
              <option :value="2">支付宝</option>
              <option :value="3">银行转账</option>
              <option :value="4">其他</option>
            </select>
          </div>
          <div class="form-group">
            <label>备注</label>
            <input type="text" v-model="receiveRemark" />
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showReceiveModal = false">取消</button>
          <button class="btn-primary" @click="submitReceive">确认收款</button>
        </div>
      </div>
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
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 12px;

  h2 {
    font-size: 22px;
    color: var(--text-primary);
  }
}

.summary-bar {
  display: flex;
  gap: 18px;
  font-size: 13px;
  color: var(--text-muted);

  b {
    color: var(--text-primary);
    font-size: 15px;
  }
}

.income-text {
  color: #2ecc71;
  font-weight: 600;
}

.warn-text {
  color: #f39c12;
  font-weight: 600;
}

.filter-bar {
  display: flex;
  gap: 10px;
  margin-bottom: 16px;

  input,
  select {
    padding: 6px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: var(--bg-card);
    color: var(--text-primary);
  }
}

.table-wrap {
  background: var(--bg-card);
  border-radius: 6px;
  box-shadow: 0 1px 4px var(--shadow-color);
  overflow: hidden;
  margin-bottom: 20px;

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

.form-actions {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;

  button {
    padding: 6px 16px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    cursor: pointer;

    &:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
  }
}

.by-customer {
  background: var(--bg-card);
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 8px var(--shadow-color);

  h3 {
    font-size: 16px;
    margin-bottom: 12px;
    color: var(--text-primary);
  }

  table {
    width: 100%;
    border-collapse: collapse;

    th,
    td {
      padding: 10px;
      text-align: left;
      border-bottom: 1px solid var(--border-color);
      font-size: 13px;
    }

    th {
      background: var(--table-header-bg);
      color: var(--table-header-text);
    }
  }
}

.modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: var(--bg-card);
  border-radius: 8px;
  width: 90%;
  max-width: 480px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  border-bottom: 1px solid var(--border-color);

  button {
    font-size: 24px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
  }

  h3 {
    font-size: 16px;
  }
}

.modal-body {
  padding: 20px;
}

.form-group {
  margin-bottom: 14px;

  label {
    display: block;
    margin-bottom: 4px;
    font-size: 13px;
    color: var(--text-primary);
  }

  input,
  select {
    width: 100%;
    padding: 8px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: var(--bg-body);
    color: var(--text-primary);
  }

  input:disabled {
    opacity: 0.6;
  }
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px;
  border-top: 1px solid var(--border-color);

  button {
    padding: 6px 18px;
    border: 1px solid var(--border-color);
    background: var(--bg-button);
    cursor: pointer;

    &:hover {
      background: var(--bg-button-hover);
    }
  }
}
</style>
