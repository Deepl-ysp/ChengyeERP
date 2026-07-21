<script lang="ts" setup>
import { ref, onMounted, reactive } from 'vue';
import { API_URL } from '@config/net';
import { confirm } from '@config/confirmSettings';

interface PurchaseOrder {
  id: number;
  number: number;
  supplier_number: number;
  supplier_name: string;
  warehouse_number: number;
  warehouse_name: string;
  total_amount: number;
  status: number;
  operator: string;
  remark: string;
  create_time: string;
  confirm_time: string;
  complete_time: string;
}

interface PurchaseItem {
  id: number;
  purchase_number: number;
  product_number: number;
  product_name: string;
  unit_price: number;
  quantity: number;
  total_amount: number;
  warehouse_number: number;
  status: number;
  remark: string;
}

const list = ref<PurchaseOrder[]>([]);
const total = ref(0);
const page = ref(1);
const size = ref(10);
const keyword = ref('');
const status = ref('');

const showModal = ref(false);
const showDetailModal = ref(false);
const detailOrder = ref<PurchaseOrder | null>(null);
const detailItems = ref<PurchaseItem[]>([]);

const suppliers = ref<{ id: number; number: number; name: string }[]>([]);
const warehouses = ref<{ number: number; name: string }[]>([]);
const products = ref<{ number: number; name: string; unit_price: number; unit: string; supplier_name: string; supplier_id: number }[]>([]);

const newOrder = reactive({
  supplier_number: 0,
  supplier_name: '',
  warehouse_number: 0,
  warehouse_name: '',
  operator: '',
  remark: '',
});

const orderItems = ref<PurchaseItem[]>([]);
const productSearch = ref('');
const supplierSearch = ref('');
const warehouseSearch = ref('');

const fetchList = async () => {
  let url = `${API_URL}v1/api/warehouse/purchase/getList?page=${page.value}&size=${size.value}`;
  if (keyword.value) url += `&keyword=${encodeURIComponent(keyword.value)}`;
  if (status.value !== '') url += `&status=${status.value}`;
  const res = await fetch(url);
  const data = await res.json();
  list.value = (data.list || []).map((o: PurchaseOrder) => ({
    ...o,
    total_amount: Number(o.total_amount) || 0,
  }));
  total.value = data.total || 0;
};

const fetchSuppliers = async (kw = '') => {
  const res = await fetch(`${API_URL}v1/api/supplier/getForSelect?keyword=${encodeURIComponent(kw)}&limit=20`);
  const data = await res.json();
  suppliers.value = (data.list || []).map((s: { id: number; number: number; name: string }) => ({ id: Number(s.id), number: Number(s.number), name: s.name }));
};

const fetchWarehouses = async (kw = '') => {
  const res = await fetch(`${API_URL}v1/api/warehouse/getForSelect?keyword=${encodeURIComponent(kw)}&limit=20`);
  const data = await res.json();
  warehouses.value = (data.list || []).map((w: { number: number; name: string }) => ({ number: Number(w.number), name: w.name }));
};

const fetchProducts = async (kw = '') => {
  if (!newOrder.supplier_number || !newOrder.warehouse_number) {
    products.value = [];
    return;
  }
  const res = await fetch(`${API_URL}v1/api/supplier/quote/getForSelect?keyword=${encodeURIComponent(kw)}&limit=20&supplier_number=${newOrder.supplier_number}&warehouse_number=${newOrder.warehouse_number}`);
  const data = await res.json();
  products.value = (data.list || []).map((p: { number: number; name: string; unit_price: string; unit: string; supplier_name: string; supplier_id: number }) => ({
    number: Number(p.number) || 0,
    name: p.name || '',
    unit_price: parseFloat(p.unit_price) || 0,
    unit: p.unit || '',
    supplier_name: p.supplier_name || '',
    supplier_id: Number(p.supplier_id) || 0,
  }));
};

const selectSupplier = (s: { id: number; number: number; name: string }) => {
  newOrder.supplier_number = s.number;
  newOrder.supplier_name = s.name;
  supplierSearch.value = s.name;
  suppliers.value = [];
  if (newOrder.warehouse_number) {
    fetchProducts();
  }
};

const selectWarehouse = (w: { number: number; name: string }) => {
  newOrder.warehouse_number = w.number;
  newOrder.warehouse_name = w.name;
  warehouseSearch.value = w.name;
  warehouses.value = [];
  if (newOrder.supplier_number) {
    fetchProducts();
  }
};

const selectProduct = (p: typeof products.value[0]) => {
  addProductItem(p);
};

const addProductItem = (p: typeof products.value[0]) => {
  const unitPrice = p.unit_price || 0;
  const quantity = 1;
  orderItems.value.push({
    id: 0,
    purchase_number: 0,
    product_number: p.number,
    product_name: p.name,
    unit_price: unitPrice,
    quantity: quantity,
    total_amount: unitPrice * quantity,
    warehouse_number: newOrder.warehouse_number,
    status: 0,
    remark: '',
  });
  productSearch.value = '';
  products.value = [];
};

const removeItem = (index: number) => {
  orderItems.value.splice(index, 1);
};

const updateQuantity = (index: number) => {
  const item = orderItems.value[index];
  if (item) {
    const price = item.unit_price || 0;
    const qty = item.quantity || 0;
    item.total_amount = price * qty;
  }
};

const openCreate = () => {
  newOrder.supplier_number = 0;
  newOrder.supplier_name = '';
  newOrder.warehouse_number = 0;
  newOrder.warehouse_name = '';
  newOrder.operator = '';
  newOrder.remark = '';
  orderItems.value = [];
  productSearch.value = '';
  products.value = [];
  showModal.value = true;
};

const submitOrder = async () => {
  if (!newOrder.supplier_number || !newOrder.warehouse_number) {
    alert('请选择供应商和仓库');
    return;
  }
  if (orderItems.value.length === 0) {
    alert('请添加采购商品');
    return;
  }

  const hasInvalidItem = orderItems.value.some(item => {
    return isNaN(item.unit_price) || isNaN(item.quantity) || isNaN(item.total_amount);
  });
  if (hasInvalidItem) {
    alert('存在无效的单价或数量，请检查');
    return;
  }

  try {
    const res = await fetch(`${API_URL}v1/api/warehouse/purchase/set`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ...newOrder,
        items: orderItems.value,
      }),
    });
    const data = await res.json();
    if (data.success) {
      alert('采购单创建成功');
      showModal.value = false;
      fetchList();
    } else {
      alert(data.message || '创建失败');
    }
  } catch (e) {
    console.error('创建失败', e);
  }
};

const viewDetail = async (order: PurchaseOrder) => {
  const res = await fetch(`${API_URL}v1/api/warehouse/purchase/getDetail?purchase_number=${order.number}`);
  const data = await res.json();
  if (data.success) {
    detailOrder.value = {
      ...data.data.order,
      total_amount: Number(data.data.order.total_amount) || 0,
    };
    detailItems.value = data.data.items.map((i: PurchaseItem) => ({
      ...i,
      unit_price: Number(i.unit_price) || 0,
      total_amount: Number(i.total_amount) || 0,
    }));
    showDetailModal.value = true;
  }
};

const confirmOrder = async (order: PurchaseOrder) => {
  if (!confirm('确认此采购单？', 'warehouse.confirmPurchase')) return;
  const res = await fetch(`${API_URL}v1/api/warehouse/purchase/confirm`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ purchase_number: order.number }),
  });
  const data = await res.json();
  if (data.success) {
    fetchList();
    if (showDetailModal.value) {
      viewDetail({ number: order.number } as PurchaseOrder);
    }
  } else {
    alert(data.message);
  }
};

const handleDetailConfirm = () => {
  if (!detailOrder.value) return;
  confirmOrder({ number: detailOrder.value.number } as PurchaseOrder);
};

const completeOrder = async (order: PurchaseOrder) => {
  if (!confirm('确认完成此采购单？', 'warehouse.completePurchase')) return;
  const res = await fetch(`${API_URL}v1/api/warehouse/purchase/complete`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ purchase_number: order.number }),
  });
  const data = await res.json();
  if (data.success) {
    fetchList();
    if (showDetailModal.value) {
      viewDetail({ number: order.number } as PurchaseOrder);
    }
  } else {
    alert(data.message);
  }
};

const handleDetailComplete = () => {
  if (!detailOrder.value) return;
  completeOrder({ number: detailOrder.value.number } as PurchaseOrder);
};

const statusText = (s: number) => ['待确认', '已确认', '已入库', '已完成'][s] || '-';
const totalAmount = () => {
  return orderItems.value.reduce((sum, i) => {
    const amount = i.total_amount || 0;
    return sum + amount;
  }, 0);
};

onMounted(() => {
  fetchList();
});
</script>

<template>
  <div class="MainBox">
    <div class="page-header">
      <h2>采购管理</h2>
      <div class="header-actions">
        <input v-model="keyword" type="text" placeholder="搜索采购单/供应商/仓库" @keyup.enter="fetchList" />
        <select v-model="status" @change="fetchList">
          <option value="">全部状态</option>
          <option :value="0">待确认</option>
          <option :value="1">已确认</option>
          <option :value="2">已入库</option>
          <option :value="3">已完成</option>
        </select>
        <button class="btn btn-primary" @click="openCreate">+ 新建采购单</button>
      </div>
    </div>

    <div class="table-container">
      <table class="purchase-table">
        <thead>
          <tr>
            <th>采购单号</th>
            <th>供应商</th>
            <th>入库仓库</th>
            <th>采购总额</th>
            <th>状态</th>
            <th>操作人</th>
            <th>创建时间</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in list" :key="item.id">
            <td>{{ item.number }}</td>
            <td>{{ item.supplier_name }}</td>
            <td>{{ item.warehouse_name }}</td>
            <td>{{ item.total_amount.toFixed(2) }}</td>
            <td>
              <span :class="['status-tag', `status-${item.status}`]">{{ statusText(item.status) }}</span>
            </td>
            <td>{{ item.operator }}</td>
            <td>{{ item.create_time }}</td>
            <td>
              <button class="btn btn-secondary" @click="viewDetail(item)">查看</button>
              <button class="btn btn-primary" v-if="item.status === 0" @click="confirmOrder(item)">确认采购</button>
              <button class="btn btn-primary" v-if="item.status === 1" @click="completeOrder(item)">完成入库</button>
              <button class="btn btn-secondary" v-if="item.status === 2" @click="completeOrder(item)">确认完成</button>
            </td>
          </tr>
          <tr v-if="list.length === 0">
            <td colspan="8" class="empty-text">暂无采购单</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 新建采购单弹窗 -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>新建采购单</h3>
          <button class="modal-close" @click="showModal = false">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>供应商 *</label>
            <div class="select-wrapper">
              <input v-model="supplierSearch" @input="(e) => fetchSuppliers((e.target as HTMLInputElement)?.value || '')" @focus="() => fetchSuppliers()" />
              <ul v-if="suppliers.length">
                <li v-for="s in suppliers" :key="s.number" @click="selectSupplier(s)">{{ s.name }}</li>
              </ul>
            </div>
          </div>
          <div class="form-group">
            <label>入库仓库 *</label>
            <div class="select-wrapper">
              <input v-model="warehouseSearch" @input="(e) => fetchWarehouses((e.target as HTMLInputElement)?.value || '')" @focus="() => fetchWarehouses()" />
              <ul v-if="warehouses.length">
                <li v-for="w in warehouses" :key="w.number" @click="selectWarehouse(w)">{{ w.name }}</li>
              </ul>
            </div>
          </div>
          <div class="form-group">
            <label>操作人</label>
            <input v-model="newOrder.operator" />
          </div>
          <div class="form-group">
            <label>备注</label>
            <input v-model="newOrder.remark" />
          </div>
          <div class="form-group" v-if="newOrder.supplier_number && newOrder.warehouse_number">
            <label>采购商品</label>
            <div class="select-wrapper">
              <input v-model="productSearch" @input="(e) => fetchProducts((e.target as HTMLInputElement)?.value || '')" @focus="() => fetchProducts()" placeholder="搜索商品添加" />
              <ul v-if="products.length">
                <li v-for="p in products" :key="p.number" @click="selectProduct(p)">
                  <span class="product-name">{{ p.name }}</span>
                  <span class="product-price">¥{{ (p.unit_price || 0).toFixed(2) }}/{{ p.unit }}</span>
                  <span v-if="p.supplier_name" class="product-supplier">来自：{{ p.supplier_name }}</span>
                </li>
              </ul>
              <div v-else-if="productSearch" class="no-results">暂无匹配商品</div>
            </div>
          </div>
          <div v-else class="form-group disabled-hint">
            <label>采购商品</label>
            <div class="hint-text">请先选择供应商和入库仓库</div>
          </div>
          <div v-if="orderItems.length" class="items-table">
            <table>
              <thead>
                <tr><th>商品</th><th>单价</th><th>数量</th><th>小计</th><th></th></tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in orderItems" :key="index">
                  <td>{{ item.product_name }}</td>
                  <td>{{ (item.unit_price || 0).toFixed(2) }}</td>
                  <td><input type="number" min="1" v-model.number="item.quantity" @change="updateQuantity(index)" /></td>
                  <td>{{ (item.total_amount || 0).toFixed(2) }}</td>
                  <td><button class="remove-btn" @click="removeItem(index)">×</button></td>
                </tr>
              </tbody>
              <tfoot>
                <tr><td colspan="3">合计</td><td colspan="2">{{ (totalAmount() || 0).toFixed(2) }}</td></tr>
              </tfoot>
            </table>
          </div>
          <div class="form-actions">
            <button class="btn btn-secondary" @click="showModal = false">取消</button>
            <button class="btn btn-primary" @click="submitOrder">确认创建</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 采购单详情弹窗 -->
    <div v-if="showDetailModal" class="modal-overlay" @click.self="showDetailModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>采购单详情</h3>
          <button class="modal-close" @click="showDetailModal = false">&times;</button>
        </div>
        <div v-if="detailOrder" class="modal-body">
          <div class="info-row">
            <span>采购单号：{{ detailOrder.number }}</span>
            <span>供应商：{{ detailOrder.supplier_name }}</span>
            <span>仓库：{{ detailOrder.warehouse_name }}</span>
          </div>
          <div class="info-row">
            <span>总额：{{ (detailOrder.total_amount || 0).toFixed(2) }}</span>
            <span>状态：{{ statusText(detailOrder.status) }}</span>
            <span>操作人：{{ detailOrder.operator }}</span>
          </div>
          <div class="items-table">
            <table>
              <thead>
                <tr><th>商品</th><th>单价</th><th>数量</th><th>小计</th></tr>
              </thead>
              <tbody>
                <tr v-for="item in detailItems" :key="item.id">
                  <td>{{ item.product_name }}</td>
                  <td>{{ (item.unit_price || 0).toFixed(2) }}</td>
                  <td>{{ item.quantity }}</td>
                  <td>{{ (item.total_amount || 0).toFixed(2) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="form-actions">
            <button class="btn btn-secondary" @click="showDetailModal = false">关闭</button>
            <button class="btn btn-primary" v-if="Number(detailOrder.status) === 0" @click="handleDetailConfirm">确认采购</button>
            <button class="btn btn-primary" v-if="Number(detailOrder.status) === 1" @click="handleDetailComplete">完成入库</button>
            <button class="btn btn-secondary" v-if="Number(detailOrder.status) === 2" @click="handleDetailComplete">确认完成</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.MainBox { padding: 1.5rem; height: 100%; overflow-y: auto; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap;
  h2 { margin: 0; }
  input, select { padding: 0.4rem 0.8rem; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-body); color: var(--text-primary); }
}
.btn { padding: 0.4rem 1.2rem; border-radius: 4px; cursor: pointer; border: none;
  &.btn-primary { background: var(--btn-primary-bg); color: var(--btn-primary-text); }
  &.btn-secondary { background: var(--bg-button); color: var(--text-primary); }
}

.table-container { background: var(--bg-card); border-radius: 8px; overflow: hidden; }
.purchase-table { width: 100%; border-collapse: collapse;
  th { background: var(--table-header-bg); padding: 0.8rem; text-align: left; color: var(--table-header-text); }
  td { padding: 0.6rem; border-bottom: 1px solid var(--border-color); }
}
.status-tag { padding: 2px 8px; border-radius: 4px; font-size: 12px;
  &.status-0 { background: #f39c12; color: white; }
  &.status-1 { background: #3498db; color: white; }
  &.status-2 { background: #9b59b6; color: white; }
  &.status-3 { background: #2ecc71; color: white; }
}
.empty-text { text-align: center; padding: 2rem; color: var(--text-muted); }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 9999; }
.modal-content { background: var(--bg-card); border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; padding: 1rem; border-bottom: 1px solid var(--border-color);
  h3 { margin: 0; }
  .modal-close { font-size: 24px; background: none; border: none; cursor: pointer; color: var(--text-muted); }
}
.modal-body { padding: 1.5rem; }
.form-group { margin-bottom: 1rem;
  label { display: block; margin-bottom: 0.3rem; }
  input { width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-card); color: var(--text-primary);
    &:focus { outline: none; border-color: var(--link-color); box-shadow: 0 0 0 2px rgba(74, 144, 217, 0.2); }
  }
}
.select-wrapper { position: relative;
  ul { position: absolute; top: 100%; left: 0; right: 0; max-height: 150px; overflow-y: auto; background: var(--bg-card); border: 1px solid var(--border-color); list-style: none; padding: 4px 0; margin: 2px 0 0; z-index: 100;
    li { padding: 8px 12px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;
      &:hover { background: var(--bg-button); }
      .product-name { font-weight: 500; }
      .product-price { font-size: 0.8rem; color: var(--text-muted); }
      .product-supplier { font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; }
    }
  }
}
.items-table { margin-top: 1rem;
  table { width: 100%; border-collapse: collapse;
    th, td { padding: 8px; border: 1px solid var(--border-color); }
    th { background: var(--table-header-bg); color: var(--table-header-text); }
    tfoot td { font-weight: 600; }
    input[type="number"] { background: var(--bg-body); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 4px; padding: 4px; width: 80px; }
    .remove-btn { background: none; border: none; font-size: 18px; color: var(--text-muted); cursor: pointer; padding: 0; }
  }
}
.info-row { display: flex; gap: 1rem; margin-bottom: 0.5rem; font-size: 0.9rem; flex-wrap: wrap; }
.form-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem; }
.disabled-hint { opacity: 0.6; }
.hint-text { padding: 0.5rem; background: var(--bg-body); border: 1px dashed var(--border-color); border-radius: 4px; color: var(--text-muted); }
.no-results { padding: 0.5rem; color: var(--text-muted); font-size: 0.9rem; }
</style>
