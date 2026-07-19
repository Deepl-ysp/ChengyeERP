<script lang="ts" setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';
import { useRoute } from 'vue-router';
import { API_URL } from '@config/net';
import Pagination from '@components/Pagination.vue';

interface RawInventoryItem {
  id: string;
  number: string;
  warehouse_number: string;
  product_number: string;
  product_name?: string;
  product_unit?: string;
  product_spec?: string;
  quantity: string;
  min_stock: string;
  max_stock: string;
  last_update_time: string;
  remark: string;
  warehouse_name?: string;
}

interface InventoryItem {
  id: number;
  number: number;
  warehouse_number: number;
  product_number: number;
  product_name?: string;
  product_unit?: string;
  product_spec?: string;
  quantity: number;
  min_stock: number;
  max_stock: number;
  last_update_time: string;
  remark: string;
}

interface ProductOption {
  number: number;
  name: string;
  unit: string;
  specification: number;
  supplier_name?: string;
}

const route = useRoute();
const warehouseNumber = computed(() => route.params.number as string);

const warehouseName = ref('');
const list = ref<InventoryItem[]>([]);
const loading = ref(false);
const searchKeyword = ref('');
const currentPage = ref(1);
const pageSize = ref(10);
const total = ref(0);

const modalVisible = ref(false);
const modalMode = ref<'in' | 'out'>('in');
const modalItem = ref<InventoryItem | null>(null);

const productSearch = ref('');
const productList = ref<ProductOption[]>([]);
const productLoading = ref(false);
const selectedProduct = ref<ProductOption | null>(null);

const ioForm = reactive({
  product_number: 0,
  direction: 1,
  quantity: 0,
  related_order_no: '',
  operator: '',
  remark: '',
});

const formErrors = reactive({
  product_number: '',
  quantity: '',
  remark: '',
});

const fetchList = async () => {
  loading.value = true;
  try {
    let url = `${API_URL}v1/api/warehouse/inventory/getList?page=${currentPage.value}&size=${pageSize.value}&warehouse_number=${warehouseNumber.value}`;
    if (searchKeyword.value.trim()) {
      url += `&keyword=${encodeURIComponent(searchKeyword.value.trim())}`;
    }
    const res = await fetch(url);
    const data = await res.json();
    list.value = (data.list || []).map((item: RawInventoryItem) => ({
      id: Number(item.id),
      number: Number(item.number),
      warehouse_number: Number(item.warehouse_number),
      product_number: Number(item.product_number),
      product_name: item.product_name,
      product_unit: item.product_unit,
      product_spec: item.product_spec,
      quantity: Number(item.quantity),
      min_stock: Number(item.min_stock),
      max_stock: Number(item.max_stock),
      last_update_time: item.last_update_time,
      remark: item.remark,
    }));
    total.value = Number(data.total) || 0;
    if (list.value.length > 0 && !warehouseName.value) {
      warehouseName.value = (data.list[0] as RawInventoryItem).warehouse_name || '';
    }
  } catch (err) {
    console.error('获取库存列表失败', err);
  } finally {
    loading.value = false;
  }
};

const fetchWarehouseName = async () => {
  try {
    const res = await fetch(`${API_URL}v1/api/warehouse/getForSelect?keyword=&limit=100`);
    const data = await res.json();
    const found = (data.list || []).find((w: { number: string; name: string }) => w.number == warehouseNumber.value);
    if (found) warehouseName.value = found.name;
  } catch (err) {
    console.error('获取仓库名称失败', err);
  }
};

const stockStatus = (item: InventoryItem) => {
  if (item.min_stock > 0 && item.quantity <= item.min_stock) return 'abnormal';
  if (item.max_stock > 0 && item.quantity >= item.max_stock) return 'abnormal';
  return 'normal';
};

// ===== 出入库弹窗 =====
const openIO = (mode: 'in' | 'out', item?: InventoryItem) => {
  modalMode.value = mode;
  modalItem.value = item || null;
  ioForm.product_number = item?.product_number || 0;
  ioForm.direction = mode === 'in' ? 1 : 0;
  ioForm.quantity = 0;
  ioForm.related_order_no = '';
  ioForm.operator = '';
  ioForm.remark = '';
  selectedProduct.value = null;
  productSearch.value = item?.product_name || '';
  Object.keys(formErrors).forEach(key => formErrors[key as keyof typeof formErrors] = '');
  modalVisible.value = true;
};

const fetchProducts = async (keyword = '') => {
  productLoading.value = true;
  try {
    const url = `${API_URL}v1/api/warehouse/inventory/getProducts?warehouse_number=${warehouseNumber.value}&keyword=${encodeURIComponent(keyword)}&limit=20`;
    const res = await fetch(url);
    const data = await res.json();
    productList.value = (data.list || []).map((item: any) => ({
      number: Number(item.product_number),
      name: item.product_name || '',
      unit: item.unit || '',
      specification: Number(item.specification) || 0,
      supplier_name: item.supplier_name,
    }));
  } catch (err) {
    console.error('获取产品失败', err);
  } finally {
    productLoading.value = false;
  }
};

const onProductSearch = () => {
  fetchProducts(productSearch.value);
};

const selectProduct = (item: ProductOption) => {
  selectedProduct.value = item;
  ioForm.product_number = item.number;
  productSearch.value = `${item.name}（来自：${item.supplier_name || '未知供应商'}）`;
  productList.value = [];
  formErrors.product_number = '';
};

const validateForm = (): boolean => {
  let valid = true;
  formErrors.product_number = '';
  formErrors.quantity = '';
  formErrors.remark = '';

  if (!ioForm.product_number) {
    formErrors.product_number = '请选择产品';
    valid = false;
  }
  if (ioForm.quantity <= 0) {
    formErrors.quantity = '数量必须大于0';
    valid = false;
  }
  if (ioForm.remark.length > 150) {
    formErrors.remark = '备注不能超过150字符';
    valid = false;
  }
  return valid;
};

const handleSubmit = async () => {
  if (!validateForm()) return;

  try {
    const payload = {
      warehouse_number: Number(warehouseNumber.value),
      product_number: ioForm.product_number,
      direction: ioForm.direction,
      quantity: ioForm.quantity,
      related_order_no: ioForm.related_order_no,
      operator: ioForm.operator,
      remark: ioForm.remark,
    };
    const res = await fetch(`${API_URL}v1/api/warehouse/io/set`, {
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

onMounted(() => {
  fetchWarehouseName();
  fetchList();
});

watch(warehouseNumber, () => {
  warehouseName.value = '';
  currentPage.value = 1;
  fetchWarehouseName();
  fetchList();
});

watch([currentPage, pageSize], () => {
  fetchList();
});
</script>

<template>
  <div class="MainBox">
    <div class="page-header">
      <h2>{{ warehouseName || '仓库' }} - 库存管理</h2>
      <div class="header-actions">
        <input
          v-model="searchKeyword"
          type="text"
          placeholder="搜索产品名称..."
          class="search-input"
          @input="fetchList"
        />
        <button class="btn btn-in" @click="openIO('in')">+ 入库</button>
        <button class="btn btn-out" @click="openIO('out')">- 出库</button>
      </div>
    </div>

    <div class="table-container">
      <table class="inventory-table">
        <thead>
          <tr>
            <th>编号</th>
            <th>产品名称</th>
            <th>当前库存</th>
            <th>单位</th>
            <th>安全下限</th>
            <th>安全上限</th>
            <th>库存状态</th>
            <th>最后更新</th>
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
            <td>{{ item.product_name || item.product_number }}</td>
            <td>{{ item.quantity }}</td>
            <td>{{ item.product_unit || '-' }}</td>
            <td>{{ item.min_stock }}</td>
            <td>{{ item.max_stock }}</td>
            <td>
              <span class="stock-tag" :class="`stock-${stockStatus(item)}`">
                {{ stockStatus(item) === 'abnormal' ? '异常' : '正常' }}
              </span>
            </td>
            <td>{{ item.last_update_time }}</td>
            <td>{{ item.remark }}</td>
            <td>
              <button class="btn btn-sm btn-in" @click="openIO('in', item)">入库</button>
              <button class="btn btn-sm btn-out" @click="openIO('out', item)">出库</button>
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
          <h3>{{ modalMode === 'in' ? '入库' : '出库' }} - {{ warehouseName }}</h3>
          <button class="modal-close" @click="modalVisible = false">&times;</button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSubmit">
            <div class="form-group">
              <label>产品 <span class="required">*</span></label>
              <div class="product-select-wrapper">
                <input
                  v-model="productSearch"
                  type="text"
                  placeholder="输入产品名称搜索"
                  class="product-input"
                  @input="onProductSearch"
                  @focus="onProductSearch"
                  :disabled="!!modalItem"
                />
                <ul v-if="productList.length" class="dropdown-list">
                  <li
                    v-for="item in productList"
                    :key="item.number"
                    @click="selectProduct(item)"
                  >
                    <span class="product-name">{{ item.name }}</span>
                    <span class="product-supplier">来自：{{ item.supplier_name || '未知供应商' }}</span>
                  </li>
                  <li v-if="productLoading" class="loading-item">加载中...</li>
                </ul>
              </div>
              <span v-if="formErrors.product_number" class="error-msg">{{ formErrors.product_number }}</span>
            </div>

            <div class="form-group">
              <label>数量 <span class="required">*</span></label>
              <input v-model.number="ioForm.quantity" type="number" min="1" placeholder="0" />
              <span v-if="formErrors.quantity" class="error-msg">{{ formErrors.quantity }}</span>
            </div>

            <div class="form-group">
              <label>关联单号</label>
              <input v-model="ioForm.related_order_no" type="text" maxlength="30" placeholder="如采购单号/销售单号" />
            </div>

            <div class="form-group">
              <label>操作人</label>
              <input v-model="ioForm.operator" type="text" maxlength="10" placeholder="操作人" />
            </div>

            <div class="form-group">
              <label>备注</label>
              <input v-model="ioForm.remark" type="text" maxlength="150" placeholder="未备注" />
              <span v-if="formErrors.remark" class="error-msg">{{ formErrors.remark }}</span>
            </div>

            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="modalVisible = false">取消</button>
              <button type="submit" class="btn" :class="modalMode === 'in' ? 'btn-in' : 'btn-out'">
                确认{{ modalMode === 'in' ? '入库' : '出库' }}
              </button>
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
  &.btn-in {
    background: #d4edda;
    color: #155724;
    &:hover {
      background: #c3e6cb;
    }
  }
  &.btn-out {
    background: #f8d7da;
    color: #721c24;
    &:hover {
      background: #f5c6cb;
    }
  }
  &.btn-secondary {
    background: var(--bg-button);
    color: var(--text-primary);
    &:hover {
      background: var(--bg-button-hover);
    }
  }
}

.table-container {
  background: var(--bg-card);
  border-radius: 8px;
  box-shadow: 0 2px 8px var(--shadow-color);
  overflow: hidden;
}

.inventory-table {
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

.stock-tag {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  &.stock-normal {
    background: #d4edda;
    color: #155724;
  }
  &.stock-abnormal {
    background: #f8d7da;
    color: #721c24;
  }
}

.product-select-wrapper {
  position: relative;
  .product-input {
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
    &:disabled {
      opacity: 0.7;
      cursor: not-allowed;
    }
  }
  .dropdown-list {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    max-height: 180px;
    overflow-y: auto;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    list-style: none;
    padding: 4px 0;
    margin: 2px 0 0;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    li {
      padding: 6px 12px;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.5rem;
      &:hover {
        background: var(--bg-button);
      }
      .product-name {
        font-weight: 500;
      }
      .product-supplier {
        font-size: 0.8rem;
        color: var(--text-muted);
        white-space: nowrap;
      }
    }
    .loading-item {
      color: var(--text-muted);
      text-align: center;
      padding: 6px 12px;
      cursor: default;
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
    input, select {
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
