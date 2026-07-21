<script lang="ts" setup>
import { ref, onMounted, watch, inject } from 'vue';
import { useRouter } from 'vue-router';
import { API_URL } from '@config/net';
import { confirm } from '@config/confirmSettings';
import PagInation from '@components/PagInation.vue'

// 注入父组件提供的刷新函数，用于删除/状态切换后更新左侧菜单供应商列表
const refreshSuppliers = inject<() => Promise<void>>('refreshSuppliers');

enum status {
  Enable,
  Disabled,
  Abnormal,
}

interface RawSupplier {
  id: number;
  number: number;
  name: string;
  address: string;
  contact: string;
  phone_number: string;
  email: string;
  status: number;
  remark: string;
}

interface Supplier {
  id: number;
  number: number;
  name: string;
  address: string;
  contact: string;
  phoneNumber: string;
  email: string;
  status: status;
  remark: string;
}

const statusMap = {
  [status.Enable]: '启用',
  [status.Disabled]: '禁用',
  [status.Abnormal]: '异常',
};

const statusClass = (s: status) => {
  return {
    'status-enable': s === status.Enable,
    'status-disabled': s === status.Disabled,
    'status-abnormal': s === status.Abnormal,
  };
};

const list = ref<Supplier[]>([]);
const loading = ref(false);
const total = ref(0);
const currentPage = ref(1);
const pageSize = ref(10);
const router = useRouter();

const fetchList = async () => {
  loading.value = true;
  try {
    const res = await fetch(`${API_URL}v1/api/supplier/getList/${currentPage.value}/${pageSize.value}`);
    const data = await res.json();
    list.value = data.list.map((item: RawSupplier) => ({
      id: item.id,
      number: item.number,
      name: item.name,
      address: item.address,
      contact: item.contact,
      phoneNumber: item.phone_number,
      email: item.email,
      status: item.status,
      remark: item.remark
    }));
    total.value = data.total;
  } catch (err) {
    console.error('获取供应商列表失败', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchList();
});

watch([currentPage, pageSize], () => {
  fetchList();
});

const handleEdit = (item: Supplier) => {
  router.push({
    path: '/supplier/create',
    query: {
      id: item.id,
      number: item.number,
      name: item.name,
      address: item.address,
      contact: item.contact,
      phone_number: item.phoneNumber,
      email: item.email,
      status: item.status,
      remark: item.remark,
    },
  });
};

const handleDelete = async (item: Supplier) => {
  if (!confirm(`确认删除供应商"${item.name}"吗？`, 'supplier.delete')) return;
  try {
    const res = await fetch(`${API_URL}v1/api/supplier/delete?number=${item.number}`, {
      method: 'DELETE',
    });
    const result = await res.json();
    if (result.success) {
      await fetchList();
      // 刷新左侧菜单供应商列表
      refreshSuppliers?.();
    } else {
      alert(result.message || '删除失败');
    }
  } catch (err) {
    console.error('删除失败', err);
    alert('删除失败，请重试');
  }
};

const handleStatusChange = async (item: Supplier, newStatus: status) => {
  try {
    const res = await fetch(`${API_URL}v1/api/supplier/updateStatus`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ number: item.number, status: newStatus }),
    });
    const result = await res.json();
    if (result.success) {
      await fetchList();
      // 刷新左侧菜单供应商列表
      refreshSuppliers?.();
    } else {
      alert(result.message || '状态更新失败');
    }
  } catch (err) {
    console.error('状态更新失败', err);
    alert('状态更新失败，请重试');
  }
};
</script>

<template>
  <div class="MainBox">
    <div v-if="loading" class="loading">加载中...</div>
    <div v-else class="Table">
      <table class="supplier-table">
        <thead>
          <tr>
            <th>供应商名称</th>
            <th>地址</th>
            <th>联系人</th>
            <th>电话</th>
            <th>邮箱</th>
            <th>状态</th>
            <th>备注</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in list" :key="item.number">
            <td>
              <a :href="`https://aiqicha.baidu.com/s?q=${item.name}`" target="_blank">
                {{ item.name }}
              </a>
            </td>
            <td>{{ item.address }}</td>
            <td>{{ item.contact }}</td>
            <td>
              <a :href="`tel:${item.phoneNumber}`">
                {{ item.phoneNumber }}
              </a>
            </td>
            <td>
              <a :href="`mailto:${item.email}`">
                {{ item.email }}
              </a>
            </td>
            <td>
              <span class="status-tag" :class="statusClass(item.status)">
                {{ statusMap[item.status] }}
              </span>
            </td>
            <td>{{ item.remark }}</td>
            <td>
              <button v-if="item.status !== status.Disabled" class="btn btn-disable"
                @click="handleStatusChange(item, status.Disabled)">禁用</button>
              <button v-if="item.status !== status.Enable" class="btn btn-enable"
                @click="handleStatusChange(item, status.Enable)">启用</button>
              <button v-if="item.status !== status.Abnormal" class="btn btn-abnormal"
                @click="handleStatusChange(item, status.Abnormal)">异常</button>
              <button class="btn btn-edit" @click="handleEdit(item)">修改</button>
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
  background-color: var(--bg-body);
  padding: 1rem;
  height: 100%;
  overflow-y: auto;
}

.Table {
  background: var(--bg-card);
  border-radius: 6px;
  box-shadow: 0 2px 8px var(--shadow-color);
  overflow: hidden;
}

.supplier-table {
  width: 100%;
  border-collapse: collapse;
  font-family: 'Segoe UI', 'Microsoft YaHei', sans-serif;
  font-size: 14px;
  color: var(--text-primary);

  thead {
    background: var(--table-header-bg);
    color: var(--table-header-text);

    th {
      padding: 12px 10px;
      text-align: center;
      font-weight: 600;
      letter-spacing: 0.5px;
      border-right: 1px solid var(--border-color);
      white-space: nowrap;
      color: var(--table-header-text);

      &:last-child {
        border-right: none;
      }
    }
  }

  tbody {
    tr {
      border-bottom: 1px solid var(--table-border);
      transition: background 0.15s;

      &:hover {
        background: var(--table-row-hover);
      }

      &:last-child {
        border-bottom: none;
      }
    }

    td {
      padding: 10px 10px;
      vertical-align: middle;
      text-align: center;
      border-right: 1px solid var(--border-color);

      &:last-child {
        border-right: none;
      }
    }
  }

  a {
    color: var(--link-color);
    text-decoration: none;
    border-bottom: 1px dashed transparent;
    transition: border-color 0.2s;

    &:hover {
      border-bottom-color: var(--link-color);
    }
  }

  .status-tag {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    background: var(--bg-button);
    color: var(--text-primary);

    &.status-enable {
      background: #d4edda;
      color: #155724;
    }

    &.status-disabled {
      background: #e2e8f0;
      color: #4a5568;
    }

    &.status-abnormal {
      background: #f8d7da;
      color: #721c24;
    }
  }

  .btn {
    border: none;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    margin: 0 1px;
    background: var(--bg-button);
    color: var(--text-primary);

    &:hover {
      transform: translateY(-1px);
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    &.btn-disable {
      background: #e2e8f0;
      color: #4a5568;

      &:hover {
        background: #cbd5e0;
      }
    }

    &.btn-enable {
      background: #d4edda;
      color: #155724;

      &:hover {
        background: #c3e6cb;
      }
    }

    &.btn-abnormal {
      background: #fff3cd;
      color: #856404;

      &:hover {
        background: #ffeaa7;
      }
    }

    &.btn-edit {
      background: #cce5ff;
      color: #004085;

      &:hover {
        background: #b8d4ff;
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
</style>
