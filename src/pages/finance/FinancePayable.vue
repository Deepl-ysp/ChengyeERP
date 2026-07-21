<script lang="ts" setup>
import { ref, onMounted } from 'vue';
import { API_URL } from '@config/net';

interface PayableItem {
  id: number;
  number: number;
  supplier_number: number;
  supplier_name: string;
  paid_amount: number;
  payment_method: number;
  flow_time: string;
  operator: string;
  remark: string;
  related_number: number;
}

interface BySupplier {
  supplier_number: number;
  supplier_name: string;
  payment_count: number;
  paid_amount: number;
}

const list = ref<PayableItem[]>([]);
const total = ref(0);
const page = ref(1);
const size = ref(10);
const keyword = ref('');
const supplierNumber = ref('');
const sumPaid = ref(0);
const bySupplier = ref<BySupplier[]>([]);

const suppliers = ref<{ number: number; name: string }[]>([]);
const showPayModal = ref(false);
const newPay = ref({
  supplier_number: 0,
  supplier_name: '',
  amount: 0,
  payment_method: 0,
  remark: '',
});

const fetchList = async () => {
  try {
    let url = `${API_URL}finance/payable/getList?page=${page.value}&size=${size.value}`;
    if (keyword.value) url += `&keyword=${encodeURIComponent(keyword.value)}`;
    if (supplierNumber.value) url += `&supplier_number=${supplierNumber.value}`;
    const res = await fetch(url);
    const data = await res.json();
    list.value = data.list || [];
    total.value = data.total || 0;
    sumPaid.value = data.sum_paid || 0;
    bySupplier.value = data.by_supplier || [];
  } catch (e) {
    console.error('获取应付列表失败', e);
  }
};

const fetchSuppliers = async () => {
  try {
    const res = await fetch(`${API_URL}supplier/getForSelect?keyword=&limit=200`);
    const data = await res.json();
    suppliers.value = (data.list || []).map((s: { number: number; name: string }) => ({ number: Number(s.number), name: s.name }));
  } catch (e) {
    console.error('获取供应商失败', e);
  }
};

const openPay = () => {
  newPay.value = {
    supplier_number: 0,
    supplier_name: '',
    amount: 0,
    payment_method: 0,
    remark: '',
  };
  showPayModal.value = true;
};

const onSupplierChange = () => {
  const s = suppliers.value.find(x => x.number === newPay.value.supplier_number);
  newPay.value.supplier_name = s ? s.name : '';
};

const submitPay = async () => {
  if (!newPay.value.supplier_number) {
    alert('请选择供应商');
    return;
  }
  if (newPay.value.amount <= 0) {
    alert('付款金额必须大于0');
    return;
  }
  try {
    const res = await fetch(`${API_URL}finance/flow/set`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        flow_type: 1, // 供应商付款
        party_type: 1, // 供应商
        party_number: newPay.value.supplier_number,
        party_name: newPay.value.supplier_name,
        amount: newPay.value.amount,
        payment_method: newPay.value.payment_method,
        operator: 'admin',
        remark: newPay.value.remark,
      }),
    });
    const data = await res.json();
    if (data.success) {
      alert('付款记录成功');
      showPayModal.value = false;
      fetchList();
    } else {
      alert(data.message || '付款失败');
    }
  } catch (e) {
    console.error('付款失败', e);
  }
};

onMounted(() => {
  fetchList();
  fetchSuppliers();
});

const methodText = (m: number) => ['现金', '微信', '支付宝', '银行转账', '其他'][m] || '-';
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h2>应付账款</h2>
      <div class="summary-bar">
        <span>累计付款：<b>{{ sumPaid.toFixed(2) }}</b></span>
        <button class="btn-primary" @click="openPay">+ 登记付款</button>
      </div>
    </div>

    <div class="filter-bar">
      <input v-model="keyword" type="text" placeholder="搜索供应商或备注" @keyup.enter="fetchList" />
      <select v-model="supplierNumber" @change="fetchList">
        <option value="">全部供应商</option>
        <option v-for="s in suppliers" :key="s.number" :value="s.number">{{ s.name }}</option>
      </select>
      <button class="btn-primary" @click="fetchList">查询</button>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>流水号</th>
            <th>供应商</th>
            <th>付款金额</th>
            <th>付款方式</th>
            <th>付款时间</th>
            <th>关联单据</th>
            <th>操作人</th>
            <th>备注</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in list" :key="item.id">
            <td>{{ item.number }}</td>
            <td>{{ item.supplier_name }}</td>
            <td class="expense-text">{{ Number(item.paid_amount).toFixed(2) }}</td>
            <td>{{ methodText(item.payment_method) }}</td>
            <td>{{ item.flow_time }}</td>
            <td>{{ item.related_number || '-' }}</td>
            <td>{{ item.operator }}</td>
            <td>{{ item.remark }}</td>
          </tr>
          <tr v-if="list.length === 0">
            <td colspan="8" class="empty">暂无付款记录</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="">
      <button :disabled="page <= 1" @click="page--; fetchList()">上一页</button>
      <span>{{ page }} / {{ Math.max(1, Math.ceil(total / size)) }}</span>
      <button :disabled="page >= Math.ceil(total / size)" @click="page++; fetchList()">下一页</button>
    </div>

    <div v-if="bySupplier.length > 0" class="by-supplier">
      <h3>按供应商汇总</h3>
      <table>
        <thead>
          <tr>
            <th>供应商</th>
            <th>付款笔数</th>
            <th>累计付款</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in bySupplier" :key="item.supplier_number">
            <td>{{ item.supplier_name }}</td>
            <td>{{ item.payment_count }}</td>
            <td class="expense-text">{{ Number(item.paid_amount).toFixed(2) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="modal" v-if="showPayModal" @click.self="showPayModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>登记付款</h3>
          <button @click="showPayModal = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>供应商 *</label>
            <select v-model.number="newPay.supplier_number" @change="onSupplierChange">
              <option :value="0">请选择</option>
              <option v-for="s in suppliers" :key="s.number" :value="s.number">{{ s.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>付款金额 *</label>
            <input type="number" step="0.01" v-model.number="newPay.amount" />
          </div>
          <div class="form-group">
            <label>付款方式</label>
            <select v-model.number="newPay.payment_method">
              <option :value="0">现金</option>
              <option :value="1">微信</option>
              <option :value="2">支付宝</option>
              <option :value="3">银行转账</option>
              <option :value="4">其他</option>
            </select>
          </div>
          <div class="form-group">
            <label>备注</label>
            <input type="text" v-model="newPay.remark" />
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showPayModal = false">取消</button>
          <button class="btn-primary" @click="submitPay">确认付款</button>
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

  b {
    color: var(--text-primary);
    font-size: 15px;
  }
}

.expense-text {
  color: #e74c3c;
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

. {
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

.by-supplier {
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
