<script lang="ts" setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';
import { useRoute } from 'vue-router';
import { API_URL } from '@config/net';
import Pagination from '@components/Pagination.vue';

interface RawQuoteItem {
  id: string;
  number: string;
  name: string;
  unit_price: string;
  unit: string;
  specification: string;
  supplier_id: string;
  supplier_name?: string;
}

interface QuoteItem {
  id: number;
  number: number;
  name: string;
  unit_price: number;
  unit: string;
  specification: string;
  supplier_id: number;
  supplier_name?: string;
}

interface QuoteForm {
  supplier_id: number;
  name: string;
  unit_price: number;
  unit: string;
  specification: string;
}

type QuotePayload = QuoteForm & { id?: number };

const route = useRoute();
const supplierNumber = computed(() => route.params.number as string);

const supplierName = ref('');
const supplierId = ref(0);

const list = ref<QuoteItem[]>([]);
const loading = ref(false);
const searchKeyword = ref('');
const currentPage = ref(1);
const pageSize = ref(10);
const total = ref(0);

const modalVisible = ref(false);
const modalMode = ref<'add' | 'edit'>('add');
const editingId = ref<number | null>(null);

const currentItem = reactive<QuoteForm>({
  supplier_id: 0,
  name: '',
  unit_price: 0,
  unit: '件',
  specification: '',
});

const formErrors = reactive<Record<keyof QuoteForm, string>>({
  supplier_id: '',
  name: '',
  unit_price: '',
  unit: '',
  specification: '',
});

const fetchList = async () => {
  loading.value = true;
  try {
    let url = `${API_URL}v1/api/supplier/quote/getList?page=${currentPage.value}&size=${pageSize.value}&supplier_number=${supplierNumber.value}`;
    if (searchKeyword.value.trim()) {
      url += `&keyword=${encodeURIComponent(searchKeyword.value.trim())}`;
    }
    const res = await fetch(url);
    const data = await res.json();
    list.value = (data.list || []).map((item: RawQuoteItem) => ({
      id: Number(item.id),
      number: Number(item.number),
      name: item.name,
      unit_price: Number(item.unit_price),
      unit: item.unit,
      specification: item.specification,
      supplier_id: Number(item.supplier_id),
      supplier_name: item.supplier_name,
    }));
    total.value = Number(data.total) || 0;
    // 从列表首条记录中获取 supplier_id 和 supplier_name（用于新增时绑定）
    if (list.value.length > 0) {
      if (!supplierId.value) supplierId.value = list.value[0].supplier_id;
      if (!supplierName.value) supplierName.value = list.value[0].supplier_name || '';
    }
  } catch (err) {
    console.error('获取货物列表失败', err);
  } finally {
    loading.value = false;
  }
};

const fetchSupplierInfo = async () => {
  try {
    const res = await fetch(`${API_URL}v1/api/supplier/getForSelect?keyword=&limit=100`);
    const data = await res.json();
    const found = (data.list || []).find((s: { number: string | number; id: number; name: string }) => s.number == supplierNumber.value);
    if (found) {
      supplierName.value = found.name;
      supplierId.value = Number(found.id);
    }
  } catch (err) {
    console.error('获取供应商信息失败', err);
  }
};

const openAdd = () => {
  modalMode.value = 'add';
  editingId.value = null;
  resetForm();
  modalVisible.value = true;
};

const openEdit = (item: QuoteItem) => {
  modalMode.value = 'edit';
  editingId.value = item.id;
  currentItem.supplier_id = item.supplier_id;
  currentItem.name = item.name;
  currentItem.unit_price = item.unit_price;
  currentItem.unit = item.unit;
  currentItem.specification = item.specification;
  modalVisible.value = true;
};

const resetForm = () => {
  currentItem.supplier_id = supplierId.value;
  currentItem.name = '';
  currentItem.unit_price = 0;
  currentItem.unit = '件';
  currentItem.specification = '';
  Object.keys(formErrors).forEach(key => formErrors[key as keyof QuoteForm] = '');
};

const validateForm = (): boolean => {
  let valid = true;
  Object.keys(formErrors).forEach(key => formErrors[key as keyof QuoteForm] = '');

  if (!currentItem.supplier_id) {
    formErrors.supplier_id = '供应商编号缺失';
    valid = false;
  }
  if (!currentItem.name.trim()) {
    formErrors.name = '货物名称不能为空';
    valid = false;
  } else if (currentItem.name.length > 20) {
    formErrors.name = '名称不能超过20字符';
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
  if (currentItem.specification.length > 50) {
    formErrors.specification = '规格不能超过50字符';
    valid = false;
  }
  return valid;
};

const handleSubmit = async () => {
  if (!validateForm()) return;

  try {
    const payload: QuotePayload = { ...currentItem };
    if (modalMode.value === 'edit' && editingId.value !== null) {
      payload.id = editingId.value;
    }

    const res = await fetch(`${API_URL}v1/api/supplier/quote/set`, {
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

const handleDelete = async (item: QuoteItem) => {
  if (!confirm(`确认删除"${item.name}"吗？`)) return;
  try {
    const res = await fetch(`${API_URL}v1/api/supplier/quote/delete?number=${item.number}`, {
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
  fetchSupplierInfo();
  fetchList();
});

watch(supplierNumber, () => {
  supplierName.value = '';
  supplierId.value = 0;
  currentPage.value = 1;
  fetchSupplierInfo();
  fetchList();
});

watch([currentPage, pageSize], () => {
  fetchList();
});
</script>

<template>
  <div class="MainBox">
    <div class="page-header">
      <h2>{{ supplierName || '供应商' }} - 货物管理</h2>
      <div class="header-actions">
        <input
          v-model="searchKeyword"
          type="text"
          placeholder="搜索货物名称..."
          class="search-input"
          @input="fetchList"
        />
        <button class="btn btn-primary" @click="openAdd">+ 新增货物</button>
      </div>
    </div>

    <div class="table-container">
      <table class="quote-table">
        <thead>
          <tr>
            <th>编号</th>
            <th>货物名称</th>
            <th>单价 (元)</th>
            <th>单位</th>
            <th>规格</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="loading-text">加载中...</td>
          </tr>
          <tr v-else-if="list.length === 0">
            <td colspan="6" class="empty-text">暂无数据</td>
          </tr>
          <tr v-for="item in list" :key="item.id">
            <td>{{ item.number }}</td>
            <td>{{ item.name }}</td>
            <td>{{ item.unit_price.toFixed(2) }}</td>
            <td>{{ item.unit }}</td>
            <td>{{ item.specification }}</td>
            <td>
              <button class="btn btn-edit" @click="openEdit(item)">编辑</button>
              <button class="btn btn-delete" @click="handleDelete(item)">删除</button>
            </td>
          </tr>
        </tbody>
      </table>
      <Pagination
        v-if="total > 0"
        :total="total"
        v-model:currentPage="currentPage"
        v-model:pageSize="pageSize"
      />
    </div>

    <div v-if="modalVisible" class="modal-overlay" @click.self="modalVisible = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>{{ modalMode === 'add' ? '新增货物' : '编辑货物' }} - {{ supplierName }}</h3>
          <button class="modal-close" @click="modalVisible = false">&times;</button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSubmit">
            <div class="form-group">
              <label>货物名称 <span class="required">*</span></label>
              <input v-model="currentItem.name" type="text" maxlength="20" placeholder="请输入名称" />
              <span v-if="formErrors.name" class="error-msg">{{ formErrors.name }}</span>
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
              <label>规格</label>
              <input v-model="currentItem.specification" type="text" maxlength="50" placeholder="如：20KG、5L" />
              <span v-if="formErrors.specification" class="error-msg">{{ formErrors.specification }}</span>
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

.quote-table {
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
      &:last-child { border-right: none; }
    }
  }

  tbody {
    tr {
      border-bottom: 1px solid var(--border-color);
      &:hover {
        background: var(--table-row-hover);
      }
      &:last-child { border-bottom: none; }
    }
    td {
      padding: 0.6rem 0.5rem;
      text-align: center;
      vertical-align: middle;
      border-right: 1px solid var(--border-color);
      &:last-child { border-right: none; }
    }
    .loading-text,
    .empty-text {
      color: var(--text-muted);
      padding: 2rem 0;
      text-align: center;
    }
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
  box-shadow: 0 10px 30px rgba(0,0,0,0.2);
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
    &:hover { color: var(--text-primary); }
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
      .required { color: #e53e3e; margin-left: 2px; }
    }
    input {
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
