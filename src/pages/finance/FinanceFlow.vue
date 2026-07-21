<script lang="ts" setup>
import { ref, onMounted } from 'vue';
import { API_URL } from '@config/net';
import { confirm } from '@config/confirmSettings';

interface FlowItem {
  id: number;
  number: number;
  flow_type: number;
  direction: number;
  party_type: number;
  party_number: number;
  party_name: string;
  related_number: number;
  amount: number;
  payment_method: number;
  flow_time: string;
  operator: string;
  remark: string;
}

const list = ref<FlowItem[]>([]);
const total = ref(0);
const page = ref(1);
const size = ref(10);
const keyword = ref('');
const flowType = ref('');
const direction = ref('');
const startDate = ref('');
const endDate = ref('');
const incomeTotal = ref(0);
const expenseTotal = ref(0);

const showModal = ref(false);
const newFlow = ref({
  flow_type: 0,
  amount: 0,
  payment_method: 0,
  party_name: '',
  party_number: 0,
  related_number: 0,
  flow_time: '',
  remark: '',
});

const fetchList = async () => {
  try {
    let url = `${API_URL}finance/flow/getList?page=${page.value}&size=${size.value}`;
    if (keyword.value) url += `&keyword=${encodeURIComponent(keyword.value)}`;
    if (flowType.value !== '') url += `&flow_type=${flowType.value}`;
    if (direction.value !== '') url += `&direction=${direction.value}`;
    if (startDate.value) url += `&start_date=${startDate.value}`;
    if (endDate.value) url += `&end_date=${endDate.value}`;
    const res = await fetch(url);
    const data = await res.json();
    list.value = data.list || [];
    total.value = data.total || 0;
    incomeTotal.value = data.income_total || 0;
    expenseTotal.value = data.expense_total || 0;
  } catch (e) {
    console.error('获取流水失败', e);
  }
};

const openCreate = () => {
  newFlow.value = {
    flow_type: 0,
    amount: 0,
    payment_method: 0,
    party_name: '',
    party_number: 0,
    related_number: 0,
    flow_time: new Date().toISOString().slice(0, 16).replace('T', ' '),
    remark: '',
  };
  showModal.value = true;
};

const submitFlow = async () => {
  if (newFlow.value.amount <= 0) {
    alert('请输入金额');
    return;
  }
  // 自动设置收支方向和对方类型
  const direction = (newFlow.value.flow_type === 0 || newFlow.value.flow_type === 2) ? 0 : 1;
  const party_type = newFlow.value.flow_type === 0 ? 0 : (newFlow.value.flow_type === 1 ? 1 : 2);
  try {
    const res = await fetch(`${API_URL}finance/flow/set`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        flow_type: newFlow.value.flow_type,
        direction,
        party_type,
        party_number: newFlow.value.party_number,
        party_name: newFlow.value.party_name,
        related_number: newFlow.value.related_number,
        amount: newFlow.value.amount,
        payment_method: newFlow.value.payment_method,
        flow_time: newFlow.value.flow_time,
        operator: 'admin',
        remark: newFlow.value.remark,
      }),
    });
    const data = await res.json();
    if (data.success) {
      alert('记录成功');
      showModal.value = false;
      fetchList();
    } else {
      alert(data.message || '记录失败');
    }
  } catch (e) {
    console.error('记录失败', e);
  }
};

const deleteFlow = async (number: number) => {
  if (!confirm('确认删除此流水记录？', 'finance.deleteFlow')) return;
  try {
    const res = await fetch(`${API_URL}finance/flow/delete`, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ number }),
    });
    const data = await res.json();
    if (data.success) {
      fetchList();
    } else {
      alert(data.message || '删除失败');
    }
  } catch (e) {
    console.error('删除失败', e);
  }
};

onMounted(() => {
  fetchList();
});

const typeText = (t: number) => ['客户收款', '供应商付款', '其他收入', '其他支出'][t] || '-';
const methodText = (m: number) => ['现金', '微信', '支付宝', '银行转账', '其他'][m] || '-';
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h2>资金流水</h2>
      <div class="summary-bar">
        <span>收入：<b class="income-text">{{ incomeTotal.toFixed(2) }}</b></span>
        <span>支出：<b class="expense-text">{{ expenseTotal.toFixed(2) }}</b></span>
        <span>净收入：<b :class="incomeTotal - expenseTotal >= 0 ? 'income-text' : 'expense-text'">{{ (incomeTotal -
          expenseTotal).toFixed(2) }}</b></span>
        <button class="btn-primary" @click="openCreate">+ 登记流水</button>
      </div>
    </div>

    <div class="filter-bar">
      <input v-model="keyword" type="text" placeholder="搜索对方/备注/编号" @keyup.enter="fetchList" />
      <select v-model="flowType" @change="fetchList">
        <option value="">全部类型</option>
        <option value="0">客户收款</option>
        <option value="1">供应商付款</option>
        <option value="2">其他收入</option>
        <option value="3">其他支出</option>
      </select>
      <select v-model="direction" @change="fetchList">
        <option value="">全部方向</option>
        <option value="0">收入</option>
        <option value="1">支出</option>
      </select>
      <input v-model="startDate" type="date" @change="fetchList" />
      <input v-model="endDate" type="date" @change="fetchList" />
      <button class="btn-primary" @click="fetchList">查询</button>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>流水号</th>
            <th>类型</th>
            <th>方向</th>
            <th>对方</th>
            <th>金额</th>
            <th>支付方式</th>
            <th>时间</th>
            <th>关联单据</th>
            <th>操作人</th>
            <th>备注</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in list" :key="item.id">
            <td>{{ item.number }}</td>
            <td>{{ typeText(item.flow_type) }}</td>
            <td>
              <span :class="['tag', item.direction === 0 ? 'tag-in' : 'tag-out']">
                {{ item.direction === 0 ? '收入' : '支出' }}
              </span>
            </td>
            <td>{{ item.party_name || '-' }}</td>
            <td :class="item.direction === 0 ? 'income-text' : 'expense-text'">
              {{ (item.direction === 0 ? '+' : '-') + Number(item.amount).toFixed(2) }}
            </td>
            <td>{{ methodText(item.payment_method) }}</td>
            <td>{{ item.flow_time }}</td>
            <td>{{ item.related_number || '-' }}</td>
            <td>{{ item.operator }}</td>
            <td>{{ item.remark }}</td>
            <td><button class="link" @click="deleteFlow(item.number)">删除</button></td>
          </tr>
          <tr v-if="list.length === 0">
            <td colspan="11" class="empty">暂无流水记录</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="">
      <button :disabled="page <= 1" @click="page--; fetchList()">上一页</button>
      <span>{{ page }} / {{ Math.max(1, Math.ceil(total / size)) }}</span>
      <button :disabled="page >= Math.ceil(total / size)" @click="page++; fetchList()">下一页</button>
    </div>

    <div class="modal" v-if="showModal" @click.self="showModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>登记流水</h3>
          <button @click="showModal = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>流水类型 *</label>
            <select v-model.number="newFlow.flow_type">
              <option :value="0">客户收款</option>
              <option :value="1">供应商付款</option>
              <option :value="2">其他收入</option>
              <option :value="3">其他支出</option>
            </select>
          </div>
          <div class="form-group">
            <label>对方名称</label>
            <input type="text" v-model="newFlow.party_name" />
          </div>
          <div class="form-group">
            <label>金额 *</label>
            <input type="number" step="0.01" v-model.number="newFlow.amount" />
          </div>
          <div class="form-group">
            <label>支付方式</label>
            <select v-model.number="newFlow.payment_method">
              <option :value="0">现金</option>
              <option :value="1">微信</option>
              <option :value="2">支付宝</option>
              <option :value="3">银行转账</option>
              <option :value="4">其他</option>
            </select>
          </div>
          <div class="form-group">
            <label>时间</label>
            <input type="datetime-local" v-model="newFlow.flow_time" />
          </div>
          <div class="form-group">
            <label>备注</label>
            <input type="text" v-model="newFlow.remark" />
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showModal = false">取消</button>
          <button class="btn-primary" @click="submitFlow">确认</button>
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
  align-items: center;
  gap: 18px;
  font-size: 13px;
  color: var(--text-muted);
  flex-wrap: wrap;

  b {
    font-size: 15px;
  }
}

.income-text {
  color: #2ecc71;
  font-weight: 600;
}

.expense-text {
  color: #e74c3c;
  font-weight: 600;
}

.filter-bar {
  display: flex;
  gap: 10px;
  margin-bottom: 16px;
  flex-wrap: wrap;

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

.tag {
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
  color: white;

  &.tag-in {
    background: #2ecc71;
  }

  &.tag-out {
    background: #e74c3c;
  }
}

.link {
  background: none;
  border: none;
  color: var(--link-color);
  cursor: pointer;
  padding: 0;

  &:hover {
    color: var(--link-hover);
  }
}

. {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;

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
