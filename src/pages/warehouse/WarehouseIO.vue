<script lang="ts" setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { API_URL } from '@config/net';
import Pagination from '@components/Pagination.vue';

interface RawIOItem {
  id: string;
  number: string;
  warehouse_number: string;
  product_number: string;
  product_name?: string;
  direction: string;
  quantity: string;
  before_quantity: string;
  after_quantity: string;
  related_order_no: string;
  operator: string;
  remark: string;
  creata_time: string;
  warehouse_name?: string;
}

interface WarehouseOption {
  id: number;
  number: number;
  name: string;
  contact: string;
}

interface ProductOption {
  number: number;
  name: string;
  unit: string;
  specification: number;
  supplier_name?: string;
}

interface IOForm {
  warehouse_number: number;
  product_number: number;
  direction: number;
  quantity: number;
  related_order_no: string;
  operator: string;
  remark: string;
}

interface IOItem extends IOForm {
  id: number;
  number: number;
  before_quantity: number;
  after_quantity: number;
  creata_time: string;
  warehouse_name?: string;
  product_name?: string;
}

const list = ref<IOItem[]>([]);
const loading = ref(false);
const searchKeyword = ref('');
const currentPage = ref(1);
const pageSize = ref(10);
const total = ref(0);

const warehouseSearch = ref('');
const warehouseList = ref<WarehouseOption[]>([]);
const warehouseLoading = ref(false);
const selectedWarehouse = ref<WarehouseOption | null>(null);

const productSearch = ref('');
const productList = ref<ProductOption[]>([]);
const productLoading = ref(false);
const selectedProduct = ref<ProductOption | null>(null);

const modalVisible = ref(false);
const currentItem = reactive<IOForm>({
  warehouse_number: 0,
  product_number: 0,
  direction: 1,
  quantity: 0,
  related_order_no: '',
  operator: '',
  remark: '',
});

const formErrors = reactive<Record<keyof IOForm, string>>({
  warehouse_number: '',
  product_number: '',
  direction: '',
  quantity: '',
  related_order_no: '',
  operator: '',
  remark: '',
});

const directionOptions = [
  { label: '入库', value: 1 },
  { label: '出库', value: 0 },
];

const directionMap: Record<number, string> = {
  1: '入库',
  0: '出库',
};

const fetchList = async () => {
  loading.value = true;
  try {
    let url = `${API_URL}v1/api/warehouse/io/getList?page=${currentPage.value}&size=${pageSize.value}`;
    if (searchKeyword.value.trim()) {
      url += `&keyword=${encodeURIComponent(searchKeyword.value.trim())}`;
    }
    const res = await fetch(url);
    const data = await res.json();
    list.value = (data.list || []).map((item: RawIOItem) => ({
      id: Number(item.id),
      number: Number(item.number),
      warehouse_number: Number(item.warehouse_number),
      product_number: Number(item.product_number),
      product_name: item.product_name,
      direction: Number(item.direction),
      quantity: Number(item.quantity),
      before_quantity: Number(item.before_quantity),
      after_quantity: Number(item.after_quantity),
      related_order_no: item.related_order_no,
      operator: item.operator,
      remark: item.remark,
      creata_time: item.creata_time,
      warehouse_name: item.warehouse_name,
    }));
    total.value = Number(data.total) || 0;
  } catch (err) {
    console.error('获取进出库列表失败', err);
  } finally {
    loading.value = false;
  }
};

const fetchWarehouses = async (keyword = '') => {
  warehouseLoading.value = true;
  try {
    const url = `${API_URL}v1/api/warehouse/getForSelect?keyword=${encodeURIComponent(keyword)}&limit=10`;
    const res = await fetch(url);
    const data = await res.json();
    warehouseList.value = data.list || [];
  } catch (err) {
    console.error('获取仓库失败', err);
  } finally {
    warehouseLoading.value = false;
  }
};

const onWarehouseSearch = () => {
  fetchWarehouses(warehouseSearch.value);
};

const selectWarehouse = (item: WarehouseOption) => {
  selectedWarehouse.value = item;
  currentItem.warehouse_number = item.number;
  warehouseSearch.value = `${item.name}（${item.contact || '无联系人'}）`;
  warehouseList.value = [];
  formErrors.warehouse_number = '';
};

const fetchProducts = async (keyword = '') => {
  productLoading.value = true;
  try {
    const url = `${API_URL}v1/api/supplier/quote/getForSelect?keyword=${encodeURIComponent(keyword)}&limit=10`;
    const res = await fetch(url);
    const data = await res.json();
    productList.value = (data.list || []).map((item: ProductOption) => ({
      number: Number(item.number),
      name: item.name,
      unit: item.unit,
      specification: Number(item.specification),
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
  currentItem.product_number = item.number;
  productSearch.value = `${item.name}（来自：${item.supplier_name || '未知供应商'}）`;
  productList.value = [];
  formErrors.product_number = '';
};

const openAdd = () => {
  resetForm();
  modalVisible.value = true;
};

const resetForm = () => {
  currentItem.warehouse_number = 0;
  currentItem.product_number = 0;
  currentItem.direction = 1;
  currentItem.quantity = 0;
  currentItem.related_order_no = '';
  currentItem.operator = '';
  currentItem.remark = '';
  selectedWarehouse.value = null;
  warehouseSearch.value = '';
  warehouseList.value = [];
  selectedProduct.value = null;
  productSearch.value = '';
  productList.value = [];
  Object.keys(formErrors).forEach(key => formErrors[key as keyof IOForm] = '');
};

const validateForm = (): boolean => {
  let valid = true;
  Object.keys(formErrors).forEach(key => formErrors[key as keyof IOForm] = '');

  if (!currentItem.warehouse_number) {
    formErrors.warehouse_number = '请选择仓库';
    valid = false;
  }
  if (!currentItem.product_number) {
    formErrors.product_number = '产品编号不能为空';
    valid = false;
  }
  if (currentItem.quantity <= 0) {
    formErrors.quantity = '数量必须大于0';
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
    const payload = { ...currentItem };
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
  fetchList();
});

watch([currentPage, pageSize], () => {
  fetchList();
});
</script>

<template>
  <div class="MainBox">
    <div class="page-header">
      <h2>进出库记录</h2>
      <div class="header-actions">
        <input
          v-model="searchKeyword"
          type="text"
          placeholder="搜索仓库、产品或单号..."
          class="search-input"
          @input="fetchList"
        />
        <button class="btn btn-primary" @click="openAdd">+ 新增记录</button>
      </div>
    </div>

    <div class="table-container">
      <table class="io-table">
        <thead>
          <tr>
            <th>编号</th>
            <th>所属仓库</th>
            <th>产品名称</th>
            <th>方向</th>
            <th>数量</th>
            <th>变动前</th>
            <th>变动后</th>
            <th>关联单号</th>
            <th>操作人</th>
            <th>日期</th>
            <th>备注</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="11" class="loading-text">加载中...</td>
          </tr>
          <tr v-else-if="list.length === 0">
            <td colspan="11" class="empty-text">暂无数据</td>
          </tr>
          <tr v-for="item in list" :key="item.id">
            <td>{{ item.number }}</td>
            <td>{{ item.warehouse_name || '未关联' }}</td>
            <td>{{ item.product_name || item.product_number }}</td>
            <td>
              <span class="direction-tag" :class="item.direction === 1 ? 'direction-in' : 'direction-out'">
                {{ directionMap[item.direction] }}
              </span>
            </td>
            <td>{{ item.quantity }}</td>
            <td>{{ item.before_quantity }}</td>
            <td>{{ item.after_quantity }}</td>
            <td>{{ item.related_order_no || '-' }}</td>
            <td>{{ item.operator }}</td>
            <td>{{ item.creata_time }}</td>
            <td>{{ item.remark }}</td>
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
          <h3>新增进出库</h3>
          <button class="modal-close" @click="modalVisible = false">&times;</button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSubmit">
            <div class="form-group">
              <label>关联仓库 <span class="required">*</span></label>
              <div class="warehouse-select-wrapper">
                <input
                  v-model="warehouseSearch"
                  type="text"
                  placeholder="输入仓库名称搜索"
                  class="warehouse-input"
                  @input="onWarehouseSearch"
                  @focus="onWarehouseSearch"
                />
                <ul v-if="warehouseList.length" class="dropdown-list">
                  <li
                    v-for="item in warehouseList"
                    :key="item.id"
                    @click="selectWarehouse(item)"
                  >
                    {{ item.name }}（{{ item.contact || '无联系人' }}）
                  </li>
                  <li v-if="warehouseLoading" class="loading-item">加载中...</li>
                </ul>
              </div>
              <span v-if="formErrors.warehouse_number" class="error-msg">{{ formErrors.warehouse_number }}</span>
            </div>

            <div class="form-group">
              <label>产品编号 <span class="required">*</span></label>
              <div class="warehouse-select-wrapper">
                <input
                  v-model="productSearch"
                  type="text"
                  placeholder="输入产品名称搜索"
                  class="warehouse-input"
                  @input="onProductSearch"
                  @focus="onProductSearch"
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
              <label>出入方向 <span class="required">*</span></label>
              <select v-model="currentItem.direction">
                <option v-for="opt in directionOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label>数量 <span class="required">*</span></label>
              <input v-model.number="currentItem.quantity" type="number" min="1" placeholder="0" />
              <span v-if="formErrors.quantity" class="error-msg">{{ formErrors.quantity }}</span>
            </div>

            <div class="form-group">
              <label>关联单号</label>
              <input v-model="currentItem.related_order_no" type="text" maxlength="30" placeholder="如采购单号/销售单号" />
            </div>

            <div class="form-group">
              <label>操作人</label>
              <input v-model="currentItem.operator" type="text" maxlength="10" placeholder="操作人" />
            </div>

            <div class="form-group">
              <label>备注</label>
              <input v-model="currentItem.remark" type="text" maxlength="150" placeholder="未备注" />
              <span v-if="formErrors.remark" class="error-msg">{{ formErrors.remark }}</span>
            </div>

            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="modalVisible = false">取消</button>
              <button type="submit" class="btn btn-primary">确认</button>
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
    gap: 1rem;
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
}

.table-container {
  background: var(--bg-card);
  border-radius: 8px;
  box-shadow: 0 2px 8px var(--shadow-color);
  overflow: hidden;
}

.io-table {
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

.direction-tag {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  &.direction-in {
    background: #d4edda;
    color: #155724;
  }
  &.direction-out {
    background: #f8d7da;
    color: #721c24;
  }
}

.warehouse-select-wrapper {
  position: relative;
  .warehouse-input {
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
      &:hover {
        background: var(--bg-button);
      }
    }
    .loading-item {
      color: var(--text-muted);
      text-align: center;
      padding: 6px 12px;
      cursor: default;
    }
    li {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.5rem;
      .product-name {
        font-weight: 500;
      }
      .product-supplier {
        font-size: 0.8rem;
        color: var(--text-muted);
        white-space: nowrap;
      }
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
  max-width: 520px;
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
