<script lang="ts" setup>
import { ref, onMounted, computed } from 'vue';
import { Chart, registerables } from 'chart.js';
import { API_URL } from '@config/net';

Chart.register(...registerables);

interface ProfitData {
  period: string[];
  order_total: number;
  order_paid: number;
  order_count: number;
  other_income: number;
  total_income: number;
  supplier_paid: number;
  other_expense: number;
  total_cost: number;
  gross_profit: number;
  profit_margin: number;
  monthly: { month: string; income: number; expense: number; profit: number }[];
}

const data = ref<ProfitData | null>(null);
const loading = ref(true);
const startDate = ref('');
const endDate = ref('');

const initDates = () => {
  const today = new Date();
  const monthAgo = new Date();
  monthAgo.setMonth(today.getMonth() - 2);
  startDate.value = monthAgo.toISOString().slice(0, 10);
  endDate.value = today.toISOString().slice(0, 10);
};

const fetchReport = async () => {
  try {
    const res = await fetch(`${API_URL}finance/report/getProfit?start_date=${startDate.value}&end_date=${endDate.value}`);
    const json = await res.json();
    if (json.success) {
      data.value = json.data;
    }
  } catch (e) {
    console.error('获取报表失败', e);
  }
};

const refresh = async () => {
  loading.value = true;
  await fetchReport();
  loading.value = false;
  setTimeout(initChart, 100);
};

onMounted(async () => {
  initDates();
  await fetchReport();
  loading.value = false;
  setTimeout(initChart, 100);
});

const initChart = () => {
  if (!data.value) return;
  const ctx = document.getElementById('profitChart') as HTMLCanvasElement;
  if (!ctx || data.value.monthly.length === 0) return;

  // 销毁旧图
  const chart = (Chart as unknown as { getChart?: (ctx: HTMLCanvasElement) => { destroy: () => void } }).getChart?.(ctx);
  if (chart) chart.destroy();

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.value.monthly.map(m => m.month),
      datasets: [
        {
          label: '收入',
          data: data.value.monthly.map(m => Number(m.income) || 0),
          backgroundColor: '#2ecc71',
          borderRadius: 4,
        },
        {
          label: '支出',
          data: data.value.monthly.map(m => Number(m.expense) || 0),
          backgroundColor: '#e74c3c',
          borderRadius: 4,
        },
        {
          label: '利润',
          data: data.value.monthly.map(m => Number(m.profit) || 0),
          backgroundColor: '#4a90d9',
          borderRadius: 4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
        x: { grid: { display: false } },
      },
      plugins: {
        legend: { position: 'top' },
      },
    },
  });
};

const profitStatus = computed(() => {
  if (!data.value) return 'neutral';
  return data.value.gross_profit >= 0 ? 'profit' : 'loss';
});
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h2>利润分析</h2>
      <div class="date-bar">
        <input type="date" v-model="startDate" />
        <span>~</span>
        <input type="date" v-model="endDate" />
        <button class="btn-primary" @click="refresh">查询</button>
      </div>
    </div>

    <div class="cards-grid">
      <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-info">
          <div class="stat-value">{{ data?.order_total.toLocaleString() || '0.00' }}</div>
          <div class="stat-label">订单总额（收入）</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-info">
          <div class="stat-value">{{ data?.order_count || 0 }}</div>
          <div class="stat-label">订单数</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-info">
          <div class="stat-value">{{ data?.supplier_paid.toLocaleString() || '0.00' }}</div>
          <div class="stat-label">采购成本（支出）</div>
        </div>
      </div>
      <div class="stat-card" :class="profitStatus">
        <div class="stat-icon">{{ profitStatus === 'profit' ? '📈' : '📉' }}</div>
        <div class="stat-info">
          <div class="stat-value">{{ data?.gross_profit.toLocaleString() || '0.00' }}</div>
          <div class="stat-label">毛利润（利润率 {{ data?.profit_margin || 0 }}%）</div>
        </div>
      </div>
    </div>

    <div class="breakdown">
      <h3>收支明细</h3>
      <table>
        <tbody>
          <tr>
            <td>订单收入</td>
            <td class="num income-text">{{ data?.order_total.toFixed(2) }}</td>
          </tr>
          <tr>
            <td>其他收入</td>
            <td class="num income-text">+ {{ data?.other_income.toFixed(2) }}</td>
          </tr>
          <tr class="subtotal">
            <td>总收入</td>
            <td class="num">{{ data?.total_income.toFixed(2) }}</td>
          </tr>
          <tr>
            <td>采购付款</td>
            <td class="num expense-text">- {{ data?.supplier_paid.toFixed(2) }}</td>
          </tr>
          <tr>
            <td>其他支出</td>
            <td class="num expense-text">- {{ data?.other_expense.toFixed(2) }}</td>
          </tr>
          <tr class="subtotal">
            <td>总成本</td>
            <td class="num">{{ data?.total_cost.toFixed(2) }}</td>
          </tr>
          <tr class="total">
            <td>净利润</td>
            <td class="num" :class="(data?.gross_profit || 0) >= 0 ? 'income-text' : 'expense-text'">
              {{ data?.gross_profit.toFixed(2) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="chart-card">
      <div class="chart-header">
        <h3>近12个月收支与利润趋势</h3>
      </div>
      <div class="chart-body">
        <canvas id="profitChart"></canvas>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.page-container { padding: 20px; height: 100%; overflow-y: auto; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;
  h2 { font-size: 22px; color: var(--text-primary); }
}
.date-bar { display: flex; align-items: center; gap: 8px;
  input { padding: 6px 10px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-card); color: var(--text-primary); }
  span { color: var(--text-muted); }
}

.cards-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: var(--bg-card); border-radius: 8px; padding: 18px; display: flex; align-items: center; gap: 14px; box-shadow: 0 2px 8px var(--shadow-color);
  &.profit .stat-value { color: #2ecc71; }
  &.loss .stat-value { color: #e74c3c; }
  .stat-icon { font-size: 28px; }
  .stat-value { font-size: 18px; font-weight: 600; color: var(--text-primary); }
  .stat-label { font-size: 12px; color: var(--text-muted); }
}

.income-text { color: #2ecc71; font-weight: 600; }
.expense-text { color: #e74c3c; font-weight: 600; }

.breakdown { background: var(--bg-card); border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px var(--shadow-color); margin-bottom: 24px;
  h3 { font-size: 15px; margin-bottom: 12px; color: var(--text-primary); }
  table { width: 100%; max-width: 500px; margin-left: auto;
    td { padding: 8px 12px; border-bottom: 1px solid var(--border-color); font-size: 13px; }
    td.num { text-align: right; font-weight: 600; }
    tr.subtotal td { font-weight: 600; background: var(--bg-body); }
    tr.total td { font-size: 16px; padding: 12px; border-top: 2px solid var(--border-color); }
  }
}

.chart-card { background: var(--bg-card); border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px var(--shadow-color);
  .chart-header h3 { font-size: 15px; margin-bottom: 12px; color: var(--text-primary); }
  .chart-body { height: 320px; }
}

@media (max-width: 1100px) { .cards-grid { grid-template-columns: repeat(2, 1fr); } }
</style>
