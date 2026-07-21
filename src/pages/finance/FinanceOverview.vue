<script lang="ts" setup>
import { ref, onMounted, computed } from 'vue';
import { Chart, registerables } from 'chart.js';
import { API_URL } from '@config/net';

Chart.register(...registerables);

interface OverviewData {
  total_income: number;
  total_expense: number;
  net_amount: number;
  flow_count: number;
  order_total: number;
  order_paid: number;
  order_unreceived: number;
  trend: { date: string; income: number; expense: number }[];
  top_receivable: { customer_number: number; customer_name: string; total_amount: number; paid_amount: number }[];
}

const data = ref<OverviewData | null>(null);
const loading = ref(true);

const fetchOverview = async () => {
  try {
    const res = await fetch(`${API_URL}finance/flow/getOverview`);
    const json = await res.json();
    if (json) {
      data.value = json;
    }
  } catch (e) {
    console.error('获取概览失败', e);
  }
};

onMounted(async () => {
  await fetchOverview();
  loading.value = false;
  setTimeout(initCharts, 100);
});

const initCharts = () => {
  if (!data.value) return;

  // 收支趋势图
  const ctx1 = document.getElementById('trendChart') as HTMLCanvasElement;
  if (ctx1 && data.value.trend.length > 0) {
    new Chart(ctx1, {
      type: 'line',
      data: {
        labels: data.value.trend.map(t => t.date),
        datasets: [
          {
            label: '收入',
            data: data.value.trend.map(t => Number(t.income) || 0),
            borderColor: '#2ecc71',
            backgroundColor: 'rgba(46, 204, 113, 0.15)',
            fill: true,
            tension: 0.4,
          },
          {
            label: '支出',
            data: data.value.trend.map(t => Number(t.expense) || 0),
            borderColor: '#e74c3c',
            backgroundColor: 'rgba(231, 76, 60, 0.15)',
            fill: true,
            tension: 0.4,
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
  }

  // 收支结构饼图
  const ctx2 = document.getElementById('summaryChart') as HTMLCanvasElement;
  if (ctx2) {
    new Chart(ctx2, {
      type: 'doughnut',
      data: {
        labels: ['已收款', '未收款', '已支出'],
        datasets: [
          {
            data: [
              data.value.order_paid,
              data.value.order_unreceived,
              data.value.total_expense,
            ],
            backgroundColor: ['#2ecc71', '#f39c12', '#e74c3c'],
            borderWidth: 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
        },
      },
    });
  }
};

const profitStatus = computed(() => {
  if (!data.value) return 'neutral';
  return data.value.net_amount >= 0 ? 'profit' : 'loss';
});
</script>

<template>
  <div class="overview-container" v-loading="loading">
    <div class="overview-header">
      <h2>资金概览</h2>
      <span class="overview-date">{{ new Date().toLocaleDateString('zh-CN') }}</span>
    </div>

    <div class="cards-grid">
      <div class="stat-card income">
        <div class="stat-icon">💰</div>
        <div class="stat-info">
          <div class="stat-value">{{ data?.total_income.toLocaleString() || '0.00' }}</div>
          <div class="stat-label">累计收入</div>
        </div>
      </div>
      <div class="stat-card expense">
        <div class="stat-icon">💸</div>
        <div class="stat-info">
          <div class="stat-value">{{ data?.total_expense.toLocaleString() || '0.00' }}</div>
          <div class="stat-label">累计支出</div>
        </div>
      </div>
      <div class="stat-card" :class="profitStatus">
        <div class="stat-icon">{{ profitStatus === 'profit' ? '📈' : '📉' }}</div>
        <div class="stat-info">
          <div class="stat-value">{{ data?.net_amount.toLocaleString() || '0.00' }}</div>
          <div class="stat-label">净收入</div>
        </div>
      </div>
      <div class="stat-card warn">
        <div class="stat-icon">⏰</div>
        <div class="stat-info">
          <div class="stat-value">{{ data?.order_unreceived.toLocaleString() || '0.00' }}</div>
          <div class="stat-label">未收账款</div>
        </div>
      </div>
    </div>

    <div class="cards-grid-2">
      <div class="stat-card small">
        <div class="stat-label">订单总额</div>
        <div class="stat-value">{{ data?.order_total.toLocaleString() || '0.00' }}</div>
      </div>
      <div class="stat-card small">
        <div class="stat-label">已收款</div>
        <div class="stat-value income-text">{{ data?.order_paid.toLocaleString() || '0.00' }}</div>
      </div>
      <div class="stat-card small">
        <div class="stat-label">未收款</div>
        <div class="stat-value warn-text">{{ data?.order_unreceived.toLocaleString() || '0.00' }}</div>
      </div>
      <div class="stat-card small">
        <div class="stat-label">流水笔数</div>
        <div class="stat-value">{{ data?.flow_count || 0 }}</div>
      </div>
    </div>

    <div class="charts-grid">
      <div class="chart-card">
        <div class="chart-header">
          <h3>近30天收支趋势</h3>
        </div>
        <div class="chart-body">
          <canvas id="trendChart"></canvas>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-header">
          <h3>资金结构</h3>
        </div>
        <div class="chart-body">
          <canvas id="summaryChart"></canvas>
        </div>
      </div>
    </div>

    <div v-if="data && data.top_receivable.length > 0" class="top-list">
      <h3>应收 TOP 10 客户</h3>
      <table>
        <thead>
          <tr>
            <th>客户名称</th>
            <th>订单总额</th>
            <th>已收款</th>
            <th>未收款</th>
            <th>收款率</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in data.top_receivable" :key="item.customer_number">
            <td>{{ item.customer_name }}</td>
            <td>{{ Number(item.total_amount).toFixed(2) }}</td>
            <td>{{ Number(item.paid_amount).toFixed(2) }}</td>
            <td class="warn-text">{{ (Number(item.total_amount) - Number(item.paid_amount)).toFixed(2) }}</td>
            <td>
              {{ ((Number(item.paid_amount) / Number(item.total_amount)) * 100).toFixed(1) }}%
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.overview-container {
  padding: 20px;
  height: 100%;
  overflow-y: auto;
}

.overview-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  h2 { font-size: 22px; color: var(--text-primary); }
  .overview-date { color: var(--text-muted); font-size: 14px; }
}

.cards-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}

.cards-grid-2 {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: var(--bg-card);
  border-radius: 8px;
  padding: 18px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 2px 8px var(--shadow-color);

  &.small {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  &.income .stat-value { color: #2ecc71; }
  &.expense .stat-value { color: #e74c3c; }
  &.profit .stat-value { color: #2ecc71; }
  &.loss .stat-value { color: #e74c3c; }
  &.warn .stat-value { color: #f39c12; }

  .stat-icon { font-size: 28px; }
  .stat-value { font-size: 20px; font-weight: 600; color: var(--text-primary); }
  .stat-label { font-size: 12px; color: var(--text-muted); }
}

.income-text { color: #2ecc71; font-weight: 600; }
.warn-text { color: #f39c12; font-weight: 600; }

.charts-grid {
  display: grid;
  grid-template-columns: 1.5fr 1fr;
  gap: 16px;
  margin-bottom: 24px;
}

.chart-card {
  background: var(--bg-card);
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 2px 8px var(--shadow-color);
}

.chart-header {
  margin-bottom: 12px;
  h3 { font-size: 15px; color: var(--text-primary); }
}

.chart-body { height: 280px; }

.top-list {
  background: var(--bg-card);
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 8px var(--shadow-color);
  h3 { font-size: 15px; margin-bottom: 12px; color: var(--text-primary); }
  table {
    width: 100%;
    border-collapse: collapse;
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid var(--border-color); }
    th { background: var(--table-header-bg); color: var(--table-header-text); }
  }
}

@media (max-width: 1100px) {
  .cards-grid, .cards-grid-2 { grid-template-columns: repeat(2, 1fr); }
  .charts-grid { grid-template-columns: 1fr; }
}
</style>
