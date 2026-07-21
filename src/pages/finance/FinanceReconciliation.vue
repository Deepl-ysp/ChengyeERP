<script lang="ts" setup>
import { ref, onMounted } from 'vue';
import { API_URL } from '@config/net';

interface ReconItem {
  id: number;
  number: number;
  period_type: number;
  period_label: string;
  start_time: string;
  end_time: string;
  income_total: number;
  expense_total: number;
  net_amount: number;
  order_count: number;
  flow_count: number;
  is_balanced: number;
  reconciler: string;
  reconcile_time: string;
  remark: string;
}

interface DetailData {
  orders: Record<string, unknown>[];
  flows: Record<string, unknown>[];
  order_count: number;
  flow_count: number;
  income_total: number;
  expense_total: number;
}

const list = ref<ReconItem[]>([]);
const total = ref(0);
const page = ref(1);
const size = ref(10);
const periodType = ref('');

const showReconcileModal = ref(false);
const reconcileForm = ref({
  period_type: 0,
  start_date: '',
  end_date: '',
  reconciler: 'admin',
  remark: '',
});

const showDetailModal = ref(false);
const detail = ref<DetailData | null>(null);
const detailLabel = ref('');

const fetchList = async () => {
  try {
    let url = `${API_URL}finance/reconciliation/getList?page=${page.value}&size=${size.value}`;
    if (periodType.value !== '') url += `&period_type=${periodType.value}`;
    const res = await fetch(url);
    const data = await res.json();
    list.value = data.list || [];
    total.value = data.total || 0;
  } catch (e) {
    console.error('获取对账记录失败', e);
  }
};

const openReconcile = () => {
  const today = new Date().toISOString().slice(0, 10);
  const monthStart = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);
  reconcileForm.value = {
    period_type: 2,
    start_date: monthStart,
    end_date: today,
    reconciler: 'admin',
    remark: '',
  };
  showReconcileModal.value = true;
};

const submitReconcile = async () => {
  if (!reconcileForm.value.start_date || !reconcileForm.value.end_date) {
    alert('请选择时间范围');
    return;
  }
  if (reconcileForm.value.start_date > reconcileForm.value.end_date) {
    alert('开始日期不能大于结束日期');
    return;
  }
  try {
    const res = await fetch(`${API_URL}finance/reconciliation/reconcile`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(reconcileForm.value),
    });
    const data = await res.json();
    if (data.success) {
      alert(data.message);
      showReconcileModal.value = false;
      fetchList();
    } else {
      alert(data.message || '对账失败');
    }
  } catch (e) {
    console.error('对账失败', e);
  }
};

const viewDetail = async (item: ReconItem) => {
  try {
    const start = item.start_time.split(' ')[0];
    const end = item.end_time.split(' ')[0];
    const res = await fetch(`${API_URL}finance/reconciliation/getDetail?start_time=${start}&end_time=${end}`);
    const data = await res.json();
    if (data.success) {
      detail.value = data.data;
      detailLabel.value = item.period_label;
      showDetailModal.value = true;
    }
  } catch (e) {
    console.error('获取对账详情失败', e);
  }
};

onMounted(() => {
  fetchList();
});

const periodText = (t: number) => ['日', '周', '月', '自定义'][t] || '-';
const flowTypeText = (t: number) => ['客户收款', '供应商付款', '其他收入', '其他支出'][t] || '-';
const directionText = (d: number) => d === 0 ? '收入' : '支出';
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h2>分时对账</h2>
      <button class="btn-primary" @click="openReconcile">+ 执行对账</button>
    </div>

    <div class="filter-bar">
      <select v-model="periodType" @change="fetchList">
        <option value="">全部周期</option>
        <option value="0">日对账</option>
        <option value="1">周对账</option>
        <option value="2">月对账</option>
        <option value="3">自定义</option>
      </select>
      <button class="btn-primary" @click="fetchList">查询</button>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>对账单号</th>
            <th>周期</th>
            <th>周期标签</th>
            <th>开始时间</th>
            <th>结束时间</th>
            <th>收入合计</th>
            <th>支出合计</th>
            <th>净收入</th>
            <th>订单数</th>
            <th>流水笔数</th>
            <th>对账人</th>
            <th>对账时间</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in list" :key="item.id">
            <td>{{ item.number }}</td>
            <td>{{ periodText(item.period_type) }}</td>
            <td>{{ item.period_label }}</td>
            <td>{{ item.start_time }}</td>
            <td>{{ item.end_time }}</td>
            <td class="income-text">{{ Number(item.income_total).toFixed(2) }}</td>
            <td class="expense-text">{{ Number(item.expense_total).toFixed(2) }}</td>
            <td :class="item.net_amount >= 0 ? 'income-text' : 'expense-text'">
              {{ Number(item.net_amount).toFixed(2) }}
            </td>
            <td>{{ item.order_count }}</td>
            <td>{{ item.flow_count }}</td>
            <td>{{ item.reconciler }}</td>
            <td>{{ item.reconcile_time }}</td>
            <td><button class="link" @click="viewDetail(item)">查看</button></td>
          </tr>
          <tr v-if="list.length === 0">
            <td colspan="13" class="empty">暂无对账记录</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="">
      <button :disabled="page <= 1" @click="page--; fetchList()">上一页</button>
      <span>{{ page }} / {{ Math.max(1, Math.ceil(total / size)) }}</span>
      <button :disabled="page >= Math.ceil(total / size)" @click="page++; fetchList()">下一页</button>
    </div>

    <!-- 对账执行弹窗 -->
    <div class="modal" v-if="showReconcileModal" @click.self="showReconcileModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>执行分时对账</h3>
          <button @click="showReconcileModal = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>对账周期 *</label>
            <select v-model.number="reconcileForm.period_type">
              <option :value="0">日对账</option>
              <option :value="1">周对账</option>
              <option :value="2">月对账</option>
              <option :value="3">自定义时段</option>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>开始日期 *</label>
              <input type="date" v-model="reconcileForm.start_date" />
            </div>
            <div class="form-group">
              <label>结束日期 *</label>
              <input type="date" v-model="reconcileForm.end_date" />
            </div>
          </div>
          <div class="form-group">
            <label>对账人</label>
            <input type="text" v-model="reconcileForm.reconciler" />
          </div>
          <div class="form-group">
            <label>备注</label>
            <input type="text" v-model="reconcileForm.remark" />
          </div>
          <div class="info-tip">
            对账将汇总该时间段内的所有订单收入、供应商付款及其他收支，并写入对账记录。
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showReconcileModal = false">取消</button>
          <button class="btn-primary" @click="submitReconcile">执行</button>
        </div>
      </div>
    </div>

    <!-- 对账详情弹窗 -->
    <div class="modal" v-if="showDetailModal" @click.self="showDetailModal = false">
      <div class="modal-content modal-wide">
        <div class="modal-header">
          <h3>对账详情 - {{ detailLabel }}</h3>
          <button @click="showDetailModal = false">×</button>
        </div>
        <div class="modal-body">
          <div class="summary-row" v-if="detail">
            <div class="summary-item">
              <div class="label">订单数</div>
              <div class="value">{{ detail.order_count }}</div>
            </div>
            <div class="summary-item">
              <div class="label">流水笔数</div>
              <div class="value">{{ detail.flow_count }}</div>
            </div>
            <div class="summary-item">
              <div class="label">收入合计</div>
              <div class="value income-text">{{ detail.income_total.toFixed(2) }}</div>
            </div>
            <div class="summary-item">
              <div class="label">支出合计</div>
              <div class="value expense-text">{{ detail.expense_total.toFixed(2) }}</div>
            </div>
            <div class="summary-item">
              <div class="label">净收入</div>
              <div class="value" :class="detail.net_amount >= 0 ? 'income-text' : 'expense-text'">
                {{ detail.net_amount.toFixed(2) }}
              </div>
            </div>
          </div>

          <div class="section">
            <h4>订单列表 ({{ detail?.orders.length }})</h4>
            <table class="detail-table">
              <thead>
                <tr>
                  <th>订单号</th>
                  <th>客户</th>
                  <th>订单金额</th>
                  <th>已收</th>
                  <th>支付状态</th>
                  <th>订单状态</th>
                  <th>创建时间</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="o in detail?.orders" :key="o.id">
                  <td>{{ o.number }}</td>
                  <td>{{ o.customer_name }}</td>
                  <td>{{ Number(o.total_amount).toFixed(2) }}</td>
                  <td>{{ Number(o.paid_amount).toFixed(2) }}</td>
                  <td>{{ ['未支付', '部分支付', '全额支付'][o.payment_status] }}</td>
                  <td>{{ ['待处理', '已确认', '已发货', '已完成', '已取消'][o.order_status] }}</td>
                  <td>{{ o.create_time }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="section">
            <h4>流水列表 ({{ detail?.flows.length }})</h4>
            <table class="detail-table">
              <thead>
                <tr>
                  <th>流水号</th>
                  <th>类型</th>
                  <th>方向</th>
                  <th>对方</th>
                  <th>金额</th>
                  <th>时间</th>
                  <th>操作人</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="f in detail?.flows" :key="f.id">
                  <td>{{ f.number }}</td>
                  <td>{{ flowTypeText(f.flow_type) }}</td>
                  <td>{{ directionText(f.direction) }}</td>
                  <td>{{ f.party_name }}</td>
                  <td :class="f.direction === 0 ? 'income-text' : 'expense-text'">
                    {{ Number(f.amount).toFixed(2) }}
                  </td>
                  <td>{{ f.flow_time }}</td>
                  <td>{{ f.operator }}</td>
                </tr>
              </tbody>
            </table>
          </div>
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

  h2 {
    font-size: 22px;
    color: var(--text-primary);
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
      font-size: 12px;
    }

    td {
      padding: 10px;
      border-bottom: 1px solid var(--border-color);
      font-size: 12px;
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

.link {
  background: none;
  border: none;
  color: var(--link-color);
  cursor: pointer;
  padding: 0;
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
  max-height: 90vh;
  overflow-y: auto;
}

.modal-wide {
  max-width: 1100px;
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

.form-row {
  display: flex;
  gap: 12px;

  .form-group {
    flex: 1;
  }
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

.info-tip {
  padding: 10px;
  background: var(--bg-body);
  border-radius: 4px;
  font-size: 12px;
  color: var(--text-muted);
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

.summary-row {
  display: flex;
  gap: 16px;
  margin-bottom: 24px;
  padding: 16px;
  background: var(--bg-body);
  border-radius: 8px;
}

.summary-item {
  flex: 1;
  text-align: center;

  .label {
    font-size: 12px;
    color: var(--text-muted);
    margin-bottom: 4px;
  }

  .value {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
  }
}

.section {
  margin-bottom: 20px;

  h4 {
    margin-bottom: 10px;
    font-size: 14px;
    color: var(--text-primary);
  }
}

.detail-table {
  width: 100%;
  border-collapse: collapse;

  th,
  td {
    padding: 8px;
    border: 1px solid var(--border-color);
    font-size: 12px;
  }

  th {
    background: var(--table-header-bg);
    color: var(--table-header-text);
  }
}
</style>
