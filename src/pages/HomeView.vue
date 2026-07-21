<script lang="ts" setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { Chart, registerables } from 'chart.js';
import { API_URL } from '@config/net';

Chart.register(...registerables);
const router = useRouter();

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

interface DashboardStats {
  finance: OverviewData | null;
  orderCount: number;
  customerCount: number;
  supplierCount: number;
  warehouseCount: number;
  pendingPurchaseCount: number;
  lowStockCount: number;
}

const stats = ref<DashboardStats>({
  finance: null,
  orderCount: 0,
  customerCount: 0,
  supplierCount: 0,
  warehouseCount: 0,
  pendingPurchaseCount: 0,
  lowStockCount: 0,
});
const loading = ref(true);

const fetchFinanceOverview = async () => {
  try {
    const res = await fetch(`${API_URL}finance/flow/getOverview`);
    stats.value.finance = await res.json();
  } catch (e) {
    console.error('获取财务概览失败', e);
  }
};

const fetchOrderCount = async () => {
  try {
    const res = await fetch(`${API_URL}order/getList?page=1&size=1`);
    const data = await res.json();
    stats.value.orderCount = data.total || 0;
  } catch (e) {
    console.error('获取订单数失败', e);
  }
};

const fetchCustomerCount = async () => {
  try {
    const res = await fetch(`${API_URL}customer/getForSelect?keyword=&limit=1`);
    const data = await res.json();
    stats.value.customerCount = (data.list || []).length;
  } catch (e) {
    console.error('获取客户数失败', e);
  }
};

const fetchSupplierCount = async () => {
  try {
    const res = await fetch(`${API_URL}supplier/getForSelect?keyword=&limit=1`);
    const data = await res.json();
    stats.value.supplierCount = (data.list || []).length;
  } catch (e) {
    console.error('获取供应商数失败', e);
  }
};

const fetchWarehouseCount = async () => {
  try {
    const res = await fetch(`${API_URL}warehouse/getForSelect`);
    const data = await res.json();
    stats.value.warehouseCount = (data.list || []).length;
  } catch (e) {
    console.error('获取仓库数失败', e);
  }
};

const fetchPurchaseCount = async () => {
  try {
    const res = await fetch(`${API_URL}warehouse/purchase/getList?page=1&size=1&status=0`);
    const data = await res.json();
    stats.value.pendingPurchaseCount = data.total || 0;
  } catch (e) {
    console.error('获取采购单数失败', e);
  }
};

const fetchLowStockCount = async () => {
  try {
    const res = await fetch(`${API_URL}warehouse/inventory/getList?page=1&size=1&low_stock=1`);
    const data = await res.json();
    stats.value.lowStockCount = data.total || 0;
  } catch (e) {
    console.error('获取低库存数失败', e);
  }
};

const initCharts = () => {
  if (!stats.value.finance) return;

  // 收支趋势图
  const ctx1 = document.getElementById('trendChart') as HTMLCanvasElement;
  if (ctx1 && stats.value.finance.trend.length > 0) {
    new Chart(ctx1, {
      type: 'line',
      data: {
        labels: stats.value.finance.trend.map(t => t.date),
        datasets: [
          {
            label: '收入',
            data: stats.value.finance.trend.map(t => Number(t.income) || 0),
            borderColor: '#2ecc71',
            backgroundColor: 'rgba(46, 204, 113, 0.15)',
            fill: true,
            tension: 0.4,
          },
          {
            label: '支出',
            data: stats.value.finance.trend.map(t => Number(t.expense) || 0),
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
        plugins: { legend: { position: 'top' } },
      },
    });
  }

  // 资金分布饼图
  const ctx2 = document.getElementById('summaryChart') as HTMLCanvasElement;
  if (ctx2 && stats.value.finance) {
    new Chart(ctx2, {
      type: 'doughnut',
      data: {
        labels: ['已收款', '未收款', '已支出'],
        datasets: [
          {
            data: [
              stats.value.finance.order_paid,
              stats.value.finance.order_unreceived,
              stats.value.finance.total_expense,
            ],
            backgroundColor: ['#2ecc71', '#f39c12', '#e74c3c'],
            borderWidth: 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'right' } },
      },
    });
  }
};

const goto = (path: string) => {
  router.push(path);
};

const shortcuts = [
  { icon: '📋', title: '创建订单', desc: '新建销售订单', path: '/order/create', color: '#3498db' },
  { icon: '🛒', title: '采购入库', desc: '创建采购单', path: '/warehouse/purchase', color: '#9b59b6' },
  { icon: '📦', title: '库存管理', desc: '查看库存清单', path: '/warehouse/inventory', color: '#e67e22' },
  { icon: '💰', title: '财务概览', desc: '资金流水统计', path: '/finance/overview', color: '#2ecc71' },
  { icon: '📈', title: '利润分析', desc: '收支利润报表', path: '/finance/profit', color: '#1abc9c' },
  { icon: '👥', title: '客户管理', desc: '客户信息维护', path: '/customer/list', color: '#e74c3c' },
  { icon: '🚚', title: '供应商管理', desc: '供应商信息维护', path: '/supplier/list', color: '#f39c12' },
  { icon: '🔍', title: '客户对账', desc: '客户欠款查询', path: '/finance/customer-reconciliation', color: '#6c5ce7' },
];

onMounted(async () => {
  await Promise.all([
    fetchFinanceOverview(),
    fetchOrderCount(),
    fetchCustomerCount(),
    fetchSupplierCount(),
    fetchWarehouseCount(),
    fetchPurchaseCount(),
    fetchLowStockCount(),
  ]);
  loading.value = false;
  setTimeout(initCharts, 100);
});

const today = computed(() => {
  const d = new Date();
  const week = ['日', '一', '二', '三', '四', '五', '六'];
  return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日 星期${week[d.getDay()]}`;
});
</script>

<template>
  <div class="home-container">
    <!-- 欢迎栏 -->
    <div class="welcome-banner">
      <div>
        <h1>欢迎回来</h1>
        <p>{{ today }}</p>
      </div>
      <div class="banner-stats">
        <div class="banner-item">
          <span class="label">今日净收益</span>
          <span class="value" :class="{ positive: stats.finance && stats.finance.net_amount >= 0, negative: stats.finance && stats.finance.net_amount < 0 }">
            ¥ {{ stats.finance ? Number(stats.finance.net_amount).toFixed(2) : '0.00' }}
          </span>
        </div>
      </div>
    </div>

    <!-- 统计卡片 -->
    <div class="stats-grid">
      <div class="stat-card" @click="goto('/order/list')">
        <div class="stat-icon" style="background: #3498db">📋</div>
        <div class="stat-content">
          <span class="stat-label">订单总数</span>
          <span class="stat-value">{{ stats.orderCount }}</span>
        </div>
      </div>

      <div class="stat-card" @click="goto('/finance/receivable')">
        <div class="stat-icon" style="background: #f39c12">⏳</div>
        <div class="stat-content">
          <span class="stat-label">未收账款</span>
          <span class="stat-value">¥ {{ stats.finance ? Number(stats.finance.order_unreceived).toFixed(2) : '0.00' }}</span>
        </div>
      </div>

      <div class="stat-card" @click="goto('/warehouse/purchase')">
        <div class="stat-icon" style="background: #9b59b6">🛒</div>
        <div class="stat-content">
          <span class="stat-label">待确认采购</span>
          <span class="stat-value">{{ stats.pendingPurchaseCount }}</span>
        </div>
      </div>

      <div class="stat-card" @click="goto('/warehouse/inventory')">
        <div class="stat-icon" style="background: #e74c3c">⚠️</div>
        <div class="stat-content">
          <span class="stat-label">低库存预警</span>
          <span class="stat-value">{{ stats.lowStockCount }}</span>
        </div>
      </div>

      <div class="stat-card" @click="goto('/customer/list')">
        <div class="stat-icon" style="background: #2ecc71">👥</div>
        <div class="stat-content">
          <span class="stat-label">客户数</span>
          <span class="stat-value">{{ stats.customerCount }}</span>
        </div>
      </div>

      <div class="stat-card" @click="goto('/supplier/list')">
        <div class="stat-icon" style="background: #e67e22">🚚</div>
        <div class="stat-content">
          <span class="stat-label">供应商数</span>
          <span class="stat-value">{{ stats.supplierCount }}</span>
        </div>
      </div>
    </div>

    <!-- 图表区 -->
    <div class="charts-row">
      <div class="chart-card large">
        <h3>近7天收支趋势</h3>
        <div class="chart-box">
          <canvas id="trendChart"></canvas>
        </div>
      </div>
      <div class="chart-card">
        <h3>资金分布</h3>
        <div class="chart-box">
          <canvas id="summaryChart"></canvas>
        </div>
      </div>
    </div>

    <!-- 常用功能 -->
    <div class="shortcuts-section">
      <h3>常用功能</h3>
      <div class="shortcuts-grid">
        <div
          v-for="shortcut in shortcuts"
          :key="shortcut.path"
          class="shortcut-card"
          @click="goto(shortcut.path)"
        >
          <div class="shortcut-icon" :style="{ background: shortcut.color }">{{ shortcut.icon }}</div>
          <div class="shortcut-info">
            <span class="shortcut-title">{{ shortcut.title }}</span>
            <span class="shortcut-desc">{{ shortcut.desc }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 欠款客户TOP5 -->
    <div v-if="stats.finance && stats.finance.top_receivable.length > 0" class="top-receivable">
      <h3>欠款客户 TOP5</h3>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>排名</th>
              <th>客户</th>
              <th>订单总额</th>
              <th>已付款</th>
              <th>未付款</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in stats.finance.top_receivable" :key="item.customer_number">
              <td>{{ index + 1 }}</td>
              <td>{{ item.customer_name }}</td>
              <td>¥ {{ Number(item.total_amount).toFixed(2) }}</td>
              <td class="income-text">¥ {{ Number(item.paid_amount).toFixed(2) }}</td>
              <td class="warn-text">¥ {{ (Number(item.total_amount) - Number(item.paid_amount)).toFixed(2) }}</td>
              <td>
                <button class="link-btn" @click="goto('/finance/customer-reconciliation')">对账</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.home-container {
  padding: 20px;
  height: 100%;
  overflow-y: auto;
}

.welcome-banner {
  background: linear-gradient(135deg, #6c5ce7, #8e44ad);
  border-radius: 12px;
  padding: 24px 32px;
  margin-bottom: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: white;

  h1 {
    font-size: 24px;
    margin-bottom: 6px;
  }

  p {
    font-size: 13px;
    opacity: 0.9;
  }

  .banner-stats {
    text-align: right;
  }

  .banner-item {
    .label {
      display: block;
      font-size: 12px;
      opacity: 0.85;
      margin-bottom: 4px;
    }

    .value {
      font-size: 28px;
      font-weight: 700;

      &.positive {
        color: #bff7d5;
      }

      &.negative {
        color: #ffd6d6;
      }
    }
  }
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 16px;
  margin-bottom: 20px;
}

.stat-card {
  background: var(--bg-card);
  border-radius: 8px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 1px 4px var(--shadow-color);

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px var(--shadow-color);
  }
}

.stat-icon {
  width: 42px;
  height: 42px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.stat-content {
  display: flex;
  flex-direction: column;
}

.stat-label {
  font-size: 12px;
  color: var(--text-muted);
}

.stat-value {
  font-size: 20px;
  font-weight: 700;
  color: var(--text-primary);
}

.charts-row {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
}

.chart-card {
  background: var(--bg-card);
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 1px 4px var(--shadow-color);

  h3 {
    font-size: 15px;
    margin-bottom: 12px;
    color: var(--text-primary);
  }
}

.chart-box {
  height: 240px;
  position: relative;
}

.shortcuts-section {
  margin-bottom: 20px;

  h3 {
    font-size: 16px;
    margin-bottom: 12px;
    color: var(--text-primary);
  }
}

.shortcuts-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.shortcut-card {
  background: var(--bg-card);
  border-radius: 8px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 1px 4px var(--shadow-color);

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px var(--shadow-color);
  }
}

.shortcut-icon {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: white;
}

.shortcut-info {
  display: flex;
  flex-direction: column;
}

.shortcut-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-primary);
}

.shortcut-desc {
  font-size: 12px;
  color: var(--text-muted);
}

.top-receivable {
  margin-bottom: 20px;

  h3 {
    font-size: 16px;
    margin-bottom: 12px;
    color: var(--text-primary);
  }
}

.table-wrap {
  background: var(--bg-card);
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 1px 4px var(--shadow-color);

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

.income-text {
  color: #2ecc71;
  font-weight: 600;
}

.warn-text {
  color: #f39c12;
  font-weight: 600;
}

.link-btn {
  background: none;
  border: 1px solid var(--border-color);
  padding: 4px 10px;
  border-radius: 4px;
  cursor: pointer;
  color: var(--text-primary);
  font-size: 12px;

  &:hover {
    background: var(--bg-button-hover);
  }
}

@media (max-width: 1200px) {
  .stats-grid {
    grid-template-columns: repeat(3, 1fr);
  }

  .charts-row {
    grid-template-columns: 1fr;
  }

  .shortcuts-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>