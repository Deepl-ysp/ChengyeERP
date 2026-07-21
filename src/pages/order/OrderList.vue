<script lang="ts" setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { API_URL } from '@config/net';
import { confirm } from '@config/confirmSettings';
import PagInation from '@components/PagInation.vue'

// 订单类型
type OrderType = 'supply' | 'retail';

const orderTypeMap: Record<OrderType, string> = {
  supply: '供货',
  retail: '零售',
};

const orderTypeClass = (t: string) => ({
  'type-supply': t === 'supply',
  'type-retail': t === 'retail',
});

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

const paymentStatusClass = (s: PaymentStatus) => ({
  'status-unpaid': s === PaymentStatus.Unpaid,
  'status-partial': s === PaymentStatus.Partial,
  'status-paid': s === PaymentStatus.Paid,
});

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

const orderStatusClass = (s: OrderStatus) => ({
  'status-pending': s === OrderStatus.Pending,
  'status-confirmed': s === OrderStatus.Confirmed,
  'status-shipped': s === OrderStatus.Shipped,
  'status-completed': s === OrderStatus.Completed,
  'status-cancelled': s === OrderStatus.Cancelled,
});

interface RawOrderItem {
  id: string;
  number: string;
  order_type: string;
  customer_name: string;
  customer_number: string;
  total_amount: string;
  paid_amount: string;
  unpaid_amount: string;
  payment_status: string;
  order_status: string;
  remark: string;
  image_path: string;
  create_time: string;
}

interface OrderItem {
  id: number;
  number: number;
  order_type: OrderType;
  customer_name: string;
  customer_number: number;
  total_amount: number;
  paid_amount: number;
  unpaid_amount: number;
  payment_status: PaymentStatus;
  order_status: OrderStatus;
  remark: string;
  image_path: string;
  create_time: string;
}

const list = ref<OrderItem[]>([]);
const loading = ref(false);
const searchKeyword = ref('');
const currentPage = ref(1);
const pageSize = ref(10);
const total = ref(0);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const router = useRouter();

// 列表查询
const fetchList = async () => {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page: String(currentPage.value),
      size: String(pageSize.value),
    });
    if (searchKeyword.value.trim()) {
      params.set('keyword', searchKeyword.value.trim());
    }
    const res = await fetch(`${API_URL}v1/api/order/getList?${params.toString()}`);
    const data = await res.json();
    list.value = (data.list || []).map((o: RawOrderItem) => ({
      id: Number(o.id),
      number: Number(o.number),
      order_type: (o.order_type === 'retail' ? 'retail' : 'supply') as OrderType,
      customer_name: o.customer_name || '',
      customer_number: Number(o.customer_number || 0),
      total_amount: Number(o.total_amount || 0),
      paid_amount: Number(o.paid_amount || 0),
      unpaid_amount: Number(o.unpaid_amount || 0),
      payment_status: Number(o.payment_status) as PaymentStatus,
      order_status: Number(o.order_status) as OrderStatus,
      remark: o.remark || '',
      image_path: o.image_path || '',
      create_time: o.create_time || '',
    }));
    total.value = Number(data.total || 0);
  } catch (err) {
    console.error('获取订单列表失败', err);
    list.value = [];
    total.value = 0;
  } finally {
    loading.value = false;
  }
};

// 关键词搜索防抖
const onSearchInput = () => {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    currentPage.value = 1;
    fetchList();
  }, 300);
};

const goDetail = (item: OrderItem) => {
  router.push(`/order/detail/${item.number}`);
};

const handleDelete = async (item: OrderItem) => {
  if (!confirm(`确认删除订单"${item.number}"吗？删除后可在回收站恢复。`, 'order.delete')) return;
  try {
    const res = await fetch(`${API_URL}v1/api/order/delete?number=${item.number}`, {
      method: 'DELETE',
    });
    const data = await res.json();
    if (data.success) {
      alert('删除成功');
      fetchList();
    } else {
      alert(`删除失败：${data.message || '未知错误'}`);
    }
  } catch (err) {
    console.error('删除订单失败', err);
    alert('删除失败，请检查网络');
  }
};

const handleStatusChange = async (item: OrderItem, newStatus: OrderStatus) => {
  try {
    const res = await fetch(`${API_URL}v1/api/order/updateStatus`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ number: item.number, order_status: newStatus }),
    });
    const data = await res.json();
    if (data.success) {
      fetchList();
    } else {
      alert(`状态更新失败：${data.message || '未知错误'}`);
    }
  } catch (err) {
    console.error('更新订单状态失败', err);
    alert('状态更新失败，请检查网络');
  }
};

onMounted(() => {
  fetchList();
});

watch([currentPage, pageSize], () => {
  fetchList();
});
</script>

<template>
  <div class="MainBox">
    <div class="page-header">
      <h2>订单列表</h2>
      <div class="header-actions">
        <input v-model="searchKeyword" type="text" placeholder="搜索订单号/客户名称..." class="search-input"
          @input="onSearchInput" />
        <button class="btn btn-primary" @click="router.push('/order/create')">+ 新建订单</button>
      </div>
    </div>

    <div class="table-container">
      <table class="order-table">
        <thead>
          <tr>
            <th>订单号</th>
            <th>类型</th>
            <th>客户单位</th>
            <th>订单金额</th>
            <th>已付金额</th>
            <th>未付金额</th>
            <th>支付状态</th>
            <th>订单状态</th>
            <th>创建时间</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="10" class="loading-text">加载中...</td>
          </tr>
          <tr v-else-if="list.length === 0">
            <td colspan="10" class="empty-text">暂无数据</td>
          </tr>
          <tr v-for="item in list" :key="item.id">
            <td class="order-number" @click="goDetail(item)">{{ item.number }}</td>
            <td>
              <span class="type-tag" :class="orderTypeClass(item.order_type)">
                {{ orderTypeMap[item.order_type] }}
              </span>
            </td>
            <td>{{ item.customer_name || '散客' }}</td>
            <td>¥{{ item.total_amount.toFixed(2) }}</td>
            <td>¥{{ item.paid_amount.toFixed(2) }}</td>
            <td class="unpaid-amount">¥{{ item.unpaid_amount.toFixed(2) }}</td>
            <td>
              <span class="status-tag" :class="paymentStatusClass(item.payment_status)">
                {{ paymentStatusMap[item.payment_status] }}
              </span>
            </td>
            <td>
              <span class="status-tag" :class="orderStatusClass(item.order_status)">
                {{ orderStatusMap[item.order_status] }}
              </span>
            </td>
            <td>{{ item.create_time }}</td>
            <td class="action-cell">
              <button class="btn btn-detail" @click="goDetail(item)">详情</button>
              <button v-if="item.order_status === OrderStatus.Pending" class="btn btn-confirm"
                @click="handleStatusChange(item, OrderStatus.Confirmed)">确认</button>
              <button v-if="item.order_status === OrderStatus.Confirmed" class="btn btn-ship"
                @click="handleStatusChange(item, OrderStatus.Shipped)">发货</button>
              <button v-if="item.order_status === OrderStatus.Shipped" class="btn btn-complete"
                @click="handleStatusChange(item, OrderStatus.Completed)">完成</button>
              <button v-if="item.order_status !== OrderStatus.Cancelled && item.order_status !== OrderStatus.Completed"
                class="btn btn-cancel" @click="handleStatusChange(item, OrderStatus.Cancelled)">取消</button>
              <button class="btn btn-delete" @click="handleDelete(item)">删除</button>
            </td>
          </tr>
        </tbody>
      </table>
      <PagInation v-if="total > 0" :total="total" v-model:currentPage="currentPage" v-model:pageSize="pageSize" />
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

  h2 {
    font-size: 1.5rem;
    color: var(--text-primary);
    margin: 0;
  }

  .header-actions {
    display: flex;
    align-items: center;
    gap: 0.8rem;

    .search-input {
      padding: 0.4rem 0.8rem;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      background: var(--bg-card);
      color: var(--text-primary);
      font-size: 0.9rem;
      min-width: 220px;

      &:focus {
        outline: none;
        border-color: var(--link-color);
        box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.15);
      }
    }
  }
}

.table-container {
  background: var(--bg-card);
  border-radius: 8px;
  box-shadow: 0 2px 8px var(--shadow-color);
  overflow: hidden;
}

.order-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
  color: var(--text-primary);

  thead {
    background: var(--table-header-bg);
    color: var(--table-header-text);

    th {
      padding: 0.8rem 0.6rem;
      text-align: center;
      font-weight: 600;
      border-right: 1px solid rgba(255, 255, 255, 0.1);
      white-space: nowrap;

      &:last-child {
        border-right: none;
      }
    }
  }

  tbody {
    tr {
      border-bottom: 1px solid var(--border-color);
      transition: background 0.15s;

      &:hover {
        background: var(--table-row-hover);
      }

      &:last-child {
        border-bottom: none;
      }
    }

    td {
      padding: 0.7rem 0.6rem;
      text-align: center;
      vertical-align: middle;
      border-right: 1px solid var(--border-color);

      &:last-child {
        border-right: none;
      }
    }

    .order-number {
      color: var(--link-color);
      cursor: pointer;
      font-weight: 500;

      &:hover {
        text-decoration: underline;
      }
    }

    .unpaid-amount {
      color: #e53e3e;
      font-weight: 500;
    }

    .loading-text,
    .empty-text {
      color: var(--text-muted);
      padding: 2.5rem 0;
      text-align: center;
    }

    .action-cell {
      .btn {
        margin: 0 2px;
        padding: 0.25rem 0.6rem;
        font-size: 0.8rem;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        transition: all 0.2s;

        &:hover {
          transform: translateY(-1px);
          box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        &.btn-detail {
          background: #e6f0fa;
          color: #2c5282;

          &:hover {
            background: #bee3f8;
          }
        }

        &.btn-confirm {
          background: #c6f6d5;
          color: #22543d;

          &:hover {
            background: #9ae6b4;
          }
        }

        &.btn-ship {
          background: #fefcbf;
          color: #744210;

          &:hover {
            background: #faf089;
          }
        }

        &.btn-complete {
          background: #d6bcfa;
          color: #44337a;

          &:hover {
            background: #b794f4;
          }
        }

        &.btn-cancel {
          background: #fed7d7;
          color: #742a2a;

          &:hover {
            background: #feb2b2;
          }
        }

        &.btn-delete {
          background: #f8d7da;
          color: #721c24;

          &:hover {
            background: #f5c6cb;
          }
        }
      }
    }
  }
}

.status-tag {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  background: var(--bg-button);
  color: var(--text-primary);

  &.status-unpaid {
    background: #fed7d7;
    color: #742a2a;
  }

  &.status-partial {
    background: #fefcbf;
    color: #744210;
  }

  &.status-paid {
    background: #c6f6d5;
    color: #22543d;
  }

  &.status-pending {
    background: #e2e8f0;
    color: #4a5568;
  }

  &.status-confirmed {
    background: #bee3f8;
    color: #2a4365;
  }

  &.status-shipped {
    background: #fefcbf;
    color: #744210;
  }

  &.status-completed {
    background: #c6f6d5;
    color: #22543d;
  }

  &.status-cancelled {
    background: #e2e8f0;
    color: #718096;
  }
}

.type-tag {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 0.75rem;
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
</style>
