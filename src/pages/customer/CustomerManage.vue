<script lang="ts" setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';
import { useRoute } from 'vue-router';
import { API_URL } from '@config/net';
import { confirm } from '@config/confirmSettings';
import PagInation from '@components/PagInation.vue'

interface RawPriceItem {
  id: string;
  number: string;
  customer_number: string;
  customer_name?: string;
  product_name: string;
  specification: string;
  unit_price: string;
  unit: string;
  effective_date: string;
  expiry_date: string | null;
  status: string;
  remark: string;
}

interface PriceItem {
  id: number;
  number: number;
  customer_number: number;
  customer_name?: string;
  product_name: string;
  specification: string;
  unit_price: number;
  unit: string;
  effective_date: string;
  expiry_date: string | null;
  status: number;
  remark: string;
}

interface PriceForm {
  customer_number: number;
  product_name: string;
  specification: string;
  unit_price: number;
  unit: string;
  effective_date: string;
  expiry_date: string | null;
  status: number;
  remark: string;
}

type PricePayload = PriceForm & { id?: number };

const route = useRoute();
const customerNumber = computed(() => route.params.number as string);

const customerName = ref('');
const list = ref<PriceItem[]>([]);
const loading = ref(false);
const searchKeyword = ref('');
const currentPage = ref(1);
const pageSize = ref(10);
const total = ref(0);

const modalVisible = ref(false);
const modalMode = ref<'add' | 'edit'>('add');
const editingId = ref<number | null>(null);

const currentItem = reactive<PriceForm>({
  customer_number: 0,
  product_name: '',
  specification: '',
  unit_price: 0,
  unit: '件',
  effective_date: new Date().toISOString().split('T')[0] || '',
  expiry_date: null,
  status: 0,
  remark: '',
});

const formErrors = reactive<Record<keyof PriceForm, string>>({
  customer_number: '',
  product_name: '',
  specification: '',
  unit_price: '',
  unit: '',
  effective_date: '',
  expiry_date: '',
  status: '',
  remark: '',
});

const statusOptions = [
  { label: '启用', value: 0 },
  { label: '禁用', value: 1 },
];

const statusMap: Record<number, string> = {
  0: '启用',
  1: '禁用',
};

const fetchList = async () => {
  loading.value = true;
  try {
    let url = `${API_URL}v1/api/customer/price/getList?page=${currentPage.value}&size=${pageSize.value}&customer_number=${customerNumber.value}`;
    if (searchKeyword.value.trim()) {
      url += `&keyword=${encodeURIComponent(searchKeyword.value.trim())}`;
    }
    const res = await fetch(url);
    const data = await res.json();
    list.value = (data.list || []).map((item: RawPriceItem) => ({
      id: Number(item.id),
      number: Number(item.number),
      customer_number: Number(item.customer_number),
      customer_name: item.customer_name,
      product_name: item.product_name,
      specification: item.specification,
      unit_price: Number(item.unit_price),
      unit: item.unit,
      effective_date: item.effective_date,
      expiry_date: item.expiry_date,
      status: Number(item.status),
      remark: item.remark,
    }));
    total.value = Number(data.total) || 0;
    if (list.value.length > 0 && !customerName.value) {
      customerName.value = (data.list[0] as RawPriceItem).customer_name || '';
    }
  } catch (err) {
    console.error('获取客户价格列表失败', err);
  } finally {
    loading.value = false;
  }
};

const fetchCustomerName = async () => {
  try {
    const res = await fetch(`${API_URL}v1/api/customer/getForSelect?keyword=&limit=100`);
    const data = await res.json();
    const found = (data.list || []).find((c: { number: string | number; name: string }) => c.number == customerNumber.value);
    if (found) customerName.value = found.name;
  } catch (err) {
    console.error('获取客户名称失败', err);
  }
};

const openAdd = () => {
  modalMode.value = 'add';
  editingId.value = null;
  resetForm();
  modalVisible.value = true;
};

const openEdit = (item: PriceItem) => {
  modalMode.value = 'edit';
  editingId.value = item.id;
  currentItem.customer_number = item.customer_number;
  currentItem.product_name = item.product_name;
  currentItem.specification = item.specification;
  currentItem.unit_price = item.unit_price;
  currentItem.unit = item.unit;
  currentItem.effective_date = item.effective_date;
  currentItem.expiry_date = item.expiry_date;
  currentItem.status = item.status;
  currentItem.remark = item.remark;
  modalVisible.value = true;
};

const resetForm = () => {
  currentItem.customer_number = Number(customerNumber.value);
  currentItem.product_name = '';
  currentItem.specification = '';
  currentItem.unit_price = 0;
  currentItem.unit = '件';
  currentItem.effective_date = new Date().toISOString().split('T')[0] || '';
  currentItem.expiry_date = null;
  currentItem.status = 0;
  currentItem.remark = '';
  Object.keys(formErrors).forEach(key => formErrors[key as keyof PriceForm] = '');
};

const validateForm = (): boolean => {
  let valid = true;
  Object.keys(formErrors).forEach(key => formErrors[key as keyof PriceForm] = '');

  if (!currentItem.customer_number) {
    formErrors.customer_number = '客户编号缺失';
    valid = false;
  }
  if (!currentItem.product_name.trim()) {
    formErrors.product_name = '产品名称不能为空';
    valid = false;
  } else if (currentItem.product_name.length > 20) {
    formErrors.product_name = '名称不能超过20字符';
    valid = false;
  }
  if (currentItem.unit_price < 0) {
    formErrors.unit_price = '单价不能为负数';
    valid = false;
  }
  if (!currentItem.unit.trim()) {
    formErrors.unit = '单位不能为空';
    valid = false;
  } else if (currentItem.unit.length > 5) {
    formErrors.unit = '单位不能超过5字符';
    valid = false;
  }
  if (currentItem.remark.length > 150) {
    formErrors.remark = '备注不能超过150字符';
    valid = false;
  }
  return valid;
};

const handleSubmit = async () => {
  if (!validateForm()) return;

  try {
    const payload: PricePayload = { ...currentItem };
    if (modalMode.value === 'edit' && editingId.value !== null) {
      payload.id = editingId.value;
    }

    const res = await fetch(`${API_URL}v1/api/customer/price/set`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const result = await res.json();
    if (result.success) {
      modalVisible.value = false;
      await fetchList();
    } else {
      alert(result.message || '操作失败');
    }
  } catch (err) {
    console.error('提交失败', err);
    alert('提交失败，请重试');
  }
};

const handleDelete = async (item: PriceItem) => {
  if (!confirm(`确认删除"${item.product_name}"吗？`, 'customerManage.deleteProduct')) return;
  try {
    const res = await fetch(`${API_URL}v1/api/customer/price/delete?number=${item.number}`, {
      method: 'DELETE',
    });
    const result = await res.json();
    if (result.success) {
      await fetchList();
    } else {
      alert(result.message || '删除失败');
    }
  } catch (err) {
    console.error('删除失败', err);
    alert('删除失败，请重试');
  }
};

onMounted(() => {
  fetchCustomerName();
  fetchList();
});

watch(customerNumber, () => {
  customerName.value = '';
  currentPage.value = 1;
  fetchCustomerName();
  fetchList();
});

watch([currentPage, pageSize], () => {
  fetchList();
});
</script>

<template>
  <div class="MainBox">
    <div class="page-header">
      <h2>{{ customerName || '客户' }} - 货物价格</h2>
      <div class="header-actions">
        <input v-model="searchKeyword" type="text" placeholder="搜索产品名称..." class="search-input" @input="fetchList" />
        <button class="btn btn-primary" @click="openAdd">+ 新增价格</button>
      </div>
    </div>

    <div class="table-container">
      <table class="price-table">
        <thead>
          <tr>
            <th>编号</th>
            <th>产品名称</th>
            <th>单价 (元)</th>
            <th>单位</th>
            <th>规格</th>
            <th>生效日期</th>
            <th>失效日期</th>
            <th>状态</th>
            <th>备注</th>
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
            <td>{{ item.number }}</td>
            <td>{{ item.product_name }}</td>
            <td>{{ item.unit_price.toFixed(2) }}</td>
            <td>{{ item.unit }}</td>
            <td>{{ item.specification }}</td>
            <td>{{ item.effective_date }}</td>
            <td>{{ item.expiry_date || '长期有效' }}</td>
            <td>
              <span class="status-tag" :class="item.status === 0 ? 'status-enable' : 'status-disabled'">
                {{ statusMap[item.status] }}
              </span>
            </td>
            <td>{{ item.remark }}</td>
            <td>
              <button class="btn btn-edit" @click="openEdit(item)">编辑</button>
              <button class="btn btn-delete" @click="handleDelete(item)">删除</button>
            </td>
          </tr>
        </tbody>
      </table>
      <PagInation v-if="total > 0" :total="total" v-model:currentPage="currentPage" v-model:pageSize="pageSize" />
    </div>

    <div v-if="modalVisible" class="modal-overlay" @click.self="modalVisible = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>{{ modalMode === 'add' ? '新增价格' : '编辑价格' }} - {{ customerName }}</h3>
          <button class="modal-close" @click="modalVisible = false">&times;</button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSubmit">
            <div class="form-group">
              <label>产品名称 <span class="required">*</span></label>
              <input v-model="currentItem.product_name" type="text" maxlength="20" placeholder="请输入名称" />
              <span v-if="formErrors.product_name" class="error-msg">{{ formErrors.product_name }}</span>
            </div>

            <div class="form-group">
              <label>规格</label>
              <input v-model="currentItem.specification" type="text" maxlength="20" placeholder="如：20KG" />
              <span v-if="formErrors.specification" class="error-msg">{{ formErrors.specification }}</span>
            </div>

            <div class="form-group">
              <label>单价 (元) <span class="required">*</span></label>
              <input v-model.number="currentItem.unit_price" type="number" step="0.01" min="0" placeholder="0.00" />
              <span v-if="formErrors.unit_price" class="error-msg">{{ formErrors.unit_price }}</span>
            </div>

            <div class="form-group">
              <label>单位 <span class="required">*</span></label>
              <input v-model="currentItem.unit" type="text" maxlength="5" placeholder="如：件、吨、米" />
              <span v-if="formErrors.unit" class="error-msg">{{ formErrors.unit }}</span>
            </div>

            <div class="form-group">
              <label>生效日期</label>
              <input v-model="currentItem.effective_date" type="date" />
            </div>

            <div class="form-group">
              <label>失效日期</label>
              <input v-model="currentItem.expiry_date" type="date" />
            </div>

            <div class="form-group">
              <label>状态</label>
              <select v-model="currentItem.status">
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label>备注</label>
              <input v-model="currentItem.remark" type="text" maxlength="150" placeholder="未备注" />
              <span v-if="formErrors.remark" class="error-msg">{{ formErrors.remark }}</span>
            </div>

            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="modalVisible = false">取消</button>
              <button type="submit" class="btn btn-primary">保存</button>
            </div>
          </form>
        </div>
      </div>
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
      min-width: 200px;

      &:focus {
        outline: none;
        border-color: var(--link-color);
        box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.15);
      }
    }
  }
}

.btn {
  padding: 0.4rem 1.2rem;
  border-radius: 4px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  font-size: 0.9rem;

  &.btn-sm {
    padding: 2px 8px;
    font-size: 0.8rem;
    margin-right: 0.3rem;
  }

  &.btn-primary {
    background: var(--btn-primary-bg);
    color: var(--btn-primary-text);

    &:hover {
      background: var(--btn-primary-hover);
      box-shadow: 0 2px 8px rgba(74, 144, 217, 0.3);
    }
  }

  &.btn-secondary {
    background: var(--bg-button);
    color: var(--text-primary);

    &:hover {
      background: var(--bg-button-hover);
    }
  }

  &.btn-edit {
    background: #cce5ff;
    color: #004085;

    &:hover {
      background: #b8d4ff;
    }

    margin-right: 0.3rem;
  }

  &.btn-delete {
    background: #f8d7da;
    color: #721c24;

    &:hover {
      background: #f5c6cb;
    }
  }
}

.table-container {
  background: var(--bg-card);
  border-radius: 8px;
  box-shadow: 0 2px 8px var(--shadow-color);
  overflow: hidden;
}

.price-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.95rem;
  color: var(--text-primary);

  thead {
    background: var(--table-header-bg);
    color: var(--table-header-text);

    th {
      padding: 0.8rem 0.5rem;
      text-align: center;
      font-weight: 600;
      border-right: 1px solid var(--border-color);

      &:last-child {
        border-right: none;
      }
    }
  }

  tbody {
    tr {
      border-bottom: 1px solid var(--border-color);

      &:hover {
        background: var(--table-row-hover);
      }

      &:last-child {
        border-bottom: none;
      }
    }

    td {
      padding: 0.6rem 0.5rem;
      text-align: center;
      vertical-align: middle;
      border-right: 1px solid var(--border-color);

      &:last-child {
        border-right: none;
      }
    }

    .loading-text,
    .empty-text {
      color: var(--text-muted);
      padding: 2rem 0;
      text-align: center;
    }
  }
}

.status-tag {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;

  &.status-enable {
    background: #d4edda;
    color: #155724;
  }

  &.status-disabled {
    background: #e2e8f0;
    color: #4a5568;
  }
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

.modal-content {
  background: var(--bg-card);
  border-radius: 12px;
  width: 90%;
  max-width: 480px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border-color);

  h3 {
    margin: 0;
    color: var(--text-primary);
  }

  .modal-close {
    background: none;
    border: none;
    font-size: 1.8rem;
    color: var(--text-muted);
    cursor: pointer;

    &:hover {
      color: var(--text-primary);
    }
  }
}

.modal-body {
  padding: 1.5rem;

  .form-group {
    margin-bottom: 1rem;

    label {
      display: block;
      font-weight: 500;
      margin-bottom: 0.3rem;
      color: var(--text-primary);

      .required {
        color: #e53e3e;
        margin-left: 2px;
      }
    }

    input,
    select {
      width: 100%;
      padding: 0.5rem 0.75rem;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      background: var(--bg-body);
      color: var(--text-primary);
      font-size: 0.95rem;

      &:focus {
        outline: none;
        border-color: var(--link-color);
        box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.15);
      }
    }

    .error-msg {
      color: #e53e3e;
      font-size: 0.8rem;
      margin-top: 0.2rem;
      display: block;
    }
  }

  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1rem;
  }
}
</style>
