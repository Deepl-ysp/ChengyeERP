<script lang="ts" setup>
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import html2canvas from 'html2canvas';
import { API_URL } from '@config/net';
import { SELLER_NAME, SELLER_ADDRESS, SELLER_PHONE } from '@config/seller';
import { orderFieldSettings, type OrderFieldSettings } from '@config/orderFieldSettings';

// API 响应类型（后端返回的字段可能是字符串或数字）
interface CustomerApiResponse {
  id?: number | string;
  number?: number | string;
  name?: string;
  contact?: string;
  address?: string;
  phone_number?: string;
  status?: number | string;
}

interface ProductApiResponse {
  product_name?: string;
  specification?: string;
  unit?: string;
  total_stock?: number | string;
  unit_price?: number | string;
  supplier_count?: number | string;
  has_customer_price?: boolean;
}

interface OrderItemApiResponse {
  product_name?: string;
  specification?: string;
  unit?: string;
  quantity?: number | string;
  unit_price?: number | string;
  subtotal?: number | string;
}

// 订单类型：供货 / 零售
type OrderType = 'supply' | 'retail';

// 客户搜索
interface CustomerOption {
  id: number;
  number: number;
  name: string;
  contact: string;
  address: string;
  phone_number: string;
  status: number;
}

interface ProductSearchItem {
  product_name: string;
  specification: string;
  unit: string;
  total_stock: number;
  unit_price: number;
  supplier_count: number;
  has_customer_price: boolean;
}

interface CartItem {
  product_name: string;
  specification: string;
  unit: string;
  quantity: number;
  unit_price: number;
  subtotal: number;
  use_customer_price: boolean;
}

const route = useRoute();
const router = useRouter();
const orderNumber = route.params.number as string;

// 订单类型
const orderType = ref<OrderType>('supply');

// 客户搜索
const customerSearch = ref('');
const customerList = ref<CustomerOption[]>([]);
const customerLoading = ref(false);
const selectedCustomer = ref<CustomerOption | null>(null);
let customerSearchTimer: ReturnType<typeof setTimeout> | null = null;

// 产品搜索
const productSearch = ref('');
const productList = ref<ProductSearchItem[]>([]);
const productLoading = ref(false);
let productSearchTimer: ReturnType<typeof setTimeout> | null = null;

// 购物车
const cart = ref<CartItem[]>([]);

// 订单表单
const form = reactive({
  order_type: 'supply' as OrderType,
  customer_number: 0,
  customer_name: '',
  seller_name: SELLER_NAME,
  receiver_name: '',
  receiver_address: '',
  receiver_phone: '',
  remark: '',
  order_status: 0,
  paid_amount: 0,
});

// 加载状态
const loading = ref(false);

// 总计
const totalQuantity = computed(() => cart.value.reduce((sum, item) => sum + item.quantity, 0));
const totalAmount = computed(() => cart.value.reduce((sum, item) => sum + item.subtotal, 0));

// ===== 加载订单详情 =====
const loadOrderDetail = async () => {
  loading.value = true;
  try {
    const res = await fetch(`${API_URL}v1/api/order/getDetail?number=${orderNumber}`);
    const data = await res.json();
    if (data.success) {
      const o = data.order;
      // 填充表单
      orderType.value = o.order_type === 'retail' ? 'retail' : 'supply';
      form.order_type = orderType.value;
      form.customer_name = o.customer_name || '';
      form.customer_number = o.customer_number || 0;
      form.seller_name = o.seller_name || SELLER_NAME;
      form.receiver_name = o.receiver_name || '';
      form.receiver_address = o.receiver_address || '';
      form.receiver_phone = o.receiver_phone || '';
      form.remark = (o.remark || '').replace(/^\[(供货|零售)\]\s*/, '');
      form.order_status = o.order_status || 0;
      form.paid_amount = o.paid_amount || 0;

      // 填充客户搜索
      if (form.customer_name) {
        customerSearch.value = form.customer_name;
        selectedCustomer.value = {
          id: 0,
          number: form.customer_number,
          name: form.customer_name,
          contact: '',
          address: form.receiver_address,
          phone_number: form.receiver_phone,
          status: 0,
        };
      }

      // 填充购物车
      cart.value = (data.items || []).map((it: OrderItemApiResponse) => ({
        product_name: it.product_name,
        specification: it.specification || '',
        unit: it.unit || '件',
        quantity: Number(it.quantity),
        unit_price: Number(it.unit_price),
        subtotal: Number(it.subtotal),
        use_customer_price: false,
      }));
    } else {
      alert(data.message || '加载订单失败');
    }
  } catch (err) {
    console.error('加载订单详情失败', err);
    alert('加载订单失败，请检查网络');
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  if (orderNumber) {
    loadOrderDetail();
  }
});

// ===== 客户搜索 =====
const onCustomerSearch = () => {
  if (customerSearchTimer) clearTimeout(customerSearchTimer);
  customerSearchTimer = setTimeout(fetchCustomers, 300);
};

const fetchCustomers = async () => {
  const keyword = customerSearch.value.trim();
  if (!keyword) { customerList.value = []; return; }
  customerLoading.value = true;
  try {
    const url = `${API_URL}v1/api/customer/getForSelect?keyword=${encodeURIComponent(keyword)}&limit=20&status=0`;
    const res = await fetch(url);
    const data = await res.json();
    customerList.value = (data.list || []).map((c: CustomerApiResponse) => ({
      id: Number(c.id),
      number: Number(c.number),
      name: c.name,
      contact: c.contact,
      address: c.address || '',
      phone_number: c.phone_number || '',
      status: Number(c.status ?? 0),
    }));
  } catch (err) {
    console.error('获取客户列表失败', err);
    customerList.value = [];
  } finally {
    customerLoading.value = false;
  }
};

const selectCustomer = (item: CustomerOption) => {
  selectedCustomer.value = item;
  form.customer_number = item.number;
  form.customer_name = item.name;
  form.receiver_name = item.name;
  form.receiver_address = item.address;
  form.receiver_phone = item.phone_number;
  customerSearch.value = `${item.name}（${item.contact || '无联系人'}）`;
  customerList.value = [];
};

const onCustomerInput = () => {
  if (!customerSearch.value.trim()) {
    selectedCustomer.value = null;
    form.customer_number = 0;
    form.customer_name = '';
    form.receiver_name = '';
    form.receiver_address = '';
    form.receiver_phone = '';
  }
  onCustomerSearch();
};

const clearSelectedCustomer = () => {
  selectedCustomer.value = null;
  form.customer_number = 0;
  form.customer_name = '';
  customerSearch.value = '';
  form.receiver_name = '';
  form.receiver_address = '';
  form.receiver_phone = '';
  customerList.value = [];
};

// ===== 产品搜索 =====
const onProductSearch = () => {
  if (productSearchTimer) clearTimeout(productSearchTimer);
  productSearchTimer = setTimeout(fetchProducts, 300);
};

const fetchProducts = async () => {
  if (!productSearch.value.trim()) { productList.value = []; return; }
  if (orderType.value === 'supply' && !form.customer_number) { productList.value = []; return; }
  productLoading.value = true;
  try {
    let url = `${API_URL}v1/api/warehouse/inventory/searchProducts?keyword=${encodeURIComponent(productSearch.value.trim())}&limit=20`;
    if (orderType.value === 'supply') url += `&customer_number=${form.customer_number}`;
    const res = await fetch(url);
    const data = await res.json();
    productList.value = (data.list || []).map((p: ProductApiResponse) => ({
      product_name: p.product_name,
      specification: p.specification || '',
      unit: p.unit || '件',
      total_stock: Number(p.total_stock ?? 0),
      unit_price: Number(p.unit_price ?? 0),
      supplier_count: Number(p.supplier_count ?? 0),
      has_customer_price: !!p.has_customer_price,
    }));
  } catch (err) {
    console.error('搜索产品失败', err);
    productList.value = [];
  } finally {
    productLoading.value = false;
  }
};

// ===== 购物车操作 =====
const addToCart = (item: ProductSearchItem) => {
  const existing = cart.value.find(c => c.product_name === item.product_name && c.specification === item.specification);
  const currentCartQty = existing ? existing.quantity : 0;
  const newQty = currentCartQty + 1;
  if (newQty > item.total_stock) {
    alert(`库存不足，当前可用库存：${item.total_stock}，已选：${currentCartQty}`);
    return;
  }
  if (existing) {
    existing.quantity = newQty;
    existing.subtotal = +(existing.quantity * existing.unit_price).toFixed(2);
  } else {
    if (item.total_stock <= 0) { alert('该产品暂无库存'); return; }
    cart.value.push({
      product_name: item.product_name,
      specification: item.specification,
      unit: item.unit,
      quantity: 1,
      unit_price: orderType.value === 'retail' ? 0 : item.unit_price,
      subtotal: orderType.value === 'retail' ? 0 : item.unit_price,
      use_customer_price: orderType.value === 'supply' && item.has_customer_price,
    });
  }
};

const updateCartQty = (index: number, delta: number) => {
  const item = cart.value[index];
  if (!item) return;
  setCartQty(index, item.quantity + delta);
};

const setCartQty = (index: number, qty: number) => {
  const item = cart.value[index];
  if (!item) return;
  const newQty = Number(qty) || 0;
  if (newQty <= 0) { cart.value.splice(index, 1); return; }
  const product = productList.value.find(p => p.product_name === item.product_name && p.specification === item.specification);
  if (product && newQty > product.total_stock) {
    alert(`库存不足，当前可用库存：${product.total_stock}`);
    item.quantity = product.total_stock;
    item.subtotal = +(item.quantity * item.unit_price).toFixed(2);
    return;
  }
  item.quantity = newQty;
  item.subtotal = +(item.quantity * item.unit_price).toFixed(2);
};

const updateCartPrice = (index: number, price: number) => {
  const item = cart.value[index];
  if (!item) return;
  item.unit_price = price;
  item.subtotal = +(item.quantity * item.unit_price).toFixed(2);
  item.use_customer_price = false;
};

const resetToCustomerPrice = (index: number) => {
  const item = cart.value[index];
  if (!item) return;
  const product = productList.value.find(p => p.product_name === item.product_name && p.specification === item.specification);
  if (product && product.has_customer_price) {
    item.unit_price = product.unit_price;
    item.subtotal = +(item.quantity * item.unit_price).toFixed(2);
    item.use_customer_price = true;
  } else {
    alert('未找到协议价');
  }
};

const removeCartItem = (index: number) => {
  cart.value.splice(index, 1);
};

// ===== 订单类型切换 =====
const switchOrderType = (type: OrderType) => {
  orderType.value = type;
  form.order_type = type;
  if (type === 'retail') {
    selectedCustomer.value = null;
    form.customer_number = 0;
    form.customer_name = '';
    customerSearch.value = '';
    form.receiver_name = '';
    form.receiver_address = '';
    form.receiver_phone = '';
    cart.value = [];
    productList.value = [];
    productSearch.value = '';
  }
};

watch(orderType, (val) => { form.order_type = val; });

// ===== 提交修改 =====
const submitUpdate = async () => {
  if (orderType.value === 'supply' && !form.customer_number) {
    alert('供货模式必须选择客户');
    return;
  }
  if (cart.value.length === 0) {
    alert('请至少添加一个产品');
    return;
  }
  if (orderType.value === 'retail' && !form.receiver_name.trim()) {
    alert('请填写收货单位');
    return;
  }
  if (!form.receiver_address.trim()) {
    alert('请填写收货地址');
    return;
  }
  const zeroPriceItem = cart.value.find(item => item.unit_price <= 0);
  if (zeroPriceItem) {
    alert(`请填写"${zeroPriceItem.product_name}"的单价`);
    return;
  }

  // 生成新图片
  const wasPreviewOpen = previewVisible.value;
  if (!wasPreviewOpen) previewVisible.value = true;
  await nextTick();
  await new Promise(resolve => setTimeout(resolve, 100));
  let imageData: string | null = null;
  try {
    imageData = await generateOrderImage();
  } catch (err) {
    console.error('生成订单图片失败', err);
  }
  if (!wasPreviewOpen) previewVisible.value = false;

  const payload = {
    number: Number(orderNumber),
    order_type: form.order_type,
    customer_number: form.customer_number,
    customer_name: form.customer_name || '未填写',
    seller_name: form.seller_name || '未填写',
    receiver_name: form.receiver_name || '未填写',
    receiver_address: form.receiver_address || '未填写',
    receiver_phone: form.receiver_phone || '未填写',
    remark: form.remark || '',
    order_status: form.order_status,
    paid_amount: form.paid_amount,
    total_quantity: totalQuantity.value,
    total_amount: totalAmount.value,
    items: cart.value,
    image_path: imageData,
  };

  try {
    const res = await fetch(`${API_URL}v1/api/order/update`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (data.success) {
      alert('订单修改成功');
      router.push(`/order/detail/${orderNumber}`);
    } else {
      alert(`订单修改失败：${data.message || '未知错误'}`);
    }
  } catch (err) {
    console.error('修改订单失败', err);
    alert('修改订单失败，请检查网络');
  }
};

// ===== 预览/图片生成 =====
const previewVisible = ref(false);
const previewRef = ref<HTMLElement | null>(null);
const generatingImage = ref(false);

const fieldConfig = reactive<OrderFieldSettings>({
  info: orderFieldSettings.info.map(f => ({ ...f })),
  columns: orderFieldSettings.columns.map(f => ({ ...f })),
  sections: orderFieldSettings.sections.map(f => ({ ...f })),
});

const settingsModalVisible = ref(false);
const openSettingsModal = () => { settingsModalVisible.value = true; };
const closeSettingsModal = () => { settingsModalVisible.value = false; };
const resetFieldConfig = () => {
  fieldConfig.info = orderFieldSettings.info.map(f => ({ ...f }));
  fieldConfig.columns = orderFieldSettings.columns.map(f => ({ ...f }));
  fieldConfig.sections = orderFieldSettings.sections.map(f => ({ ...f }));
};

const isVisible = (group: 'info' | 'columns' | 'sections', key: string): boolean => {
  const item = fieldConfig[group].find(f => f.key === key);
  return item ? item.visible : true;
};

const visibleColumnCount = computed(() => fieldConfig.columns.filter(c => c.visible).length);

const previewData = computed(() => ({
  order_type: form.order_type,
  customer_name: form.customer_name || '未填写',
  seller_name: form.seller_name || '未填写',
  receiver_name: form.receiver_name || '未填写',
  receiver_address: form.receiver_address || '未填写',
  receiver_phone: form.receiver_phone || '未填写',
  remark: form.remark || '未填写',
  create_time: new Date().toLocaleString('zh-CN'),
  seller_address: SELLER_ADDRESS,
  seller_phone: SELLER_PHONE,
  items: cart.value,
  total_quantity: totalQuantity.value,
  total_amount: totalAmount.value,
}));

const openPreview = () => { if (cart.value.length === 0) { alert('请先添加产品'); return; } previewVisible.value = true; };
const closePreview = () => { previewVisible.value = false; };

const generateOrderImage = async (): Promise<string | null> => {
  if (!previewRef.value) return null;
  generatingImage.value = true;
  try {
    await nextTick();
    const canvas = await html2canvas(previewRef.value, { scale: 2, backgroundColor: '#ffffff', useCORS: true, logging: false });
    return canvas.toDataURL('image/png');
  } catch (err) {
    console.error('生成图片失败', err);
    return null;
  } finally {
    generatingImage.value = false;
  }
};

const printOrder = async () => {
  if (!previewRef.value) return;
  generatingImage.value = true;
  try {
    await nextTick();
    const canvas = await html2canvas(previewRef.value, { scale: 2, backgroundColor: '#ffffff', useCORS: true, logging: false });
    const imageData = canvas.toDataURL('image/png');
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    if (!printWindow) { alert('请允许弹出窗口以打印订单'); return; }
    printWindow.document.write(`<!DOCTYPE html><html><head><title>订单打印 - ${previewData.value.customer_name}</title><style>* { margin: 0; padding: 0; box-sizing: border-box; } body { padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Microsoft YaHei", sans-serif; } img { max-width: 100%; display: block; margin: 0 auto; } @media print { body { padding: 0; } @page { margin: 10mm; } }</style></head><body><img src="${imageData}" alt="订单图片" /></body></html>`);
    printWindow.document.close();
    printWindow.onload = () => { setTimeout(() => printWindow.print(), 300); };
  } catch (err) {
    console.error('打印失败', err);
    alert('打印失败，请重试');
  } finally {
    generatingImage.value = false;
  }
};

const downloadOrderImage = async () => {
  if (!previewRef.value) return;
  generatingImage.value = true;
  try {
    await nextTick();
    const canvas = await html2canvas(previewRef.value, { scale: 2, backgroundColor: '#ffffff', useCORS: true, logging: false });
    const link = document.createElement('a');
    link.download = `订单_${previewData.value.customer_name}_${Date.now()}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
  } catch (err) {
    console.error('下载图片失败', err);
    alert('下载失败，请重试');
  } finally {
    generatingImage.value = false;
  }
};
</script>

<template>
  <div class="MainBox">
    <div class="page-header">
      <h2>修改订单 <span class="order-number">#{{ orderNumber }}</span></h2>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="cart = []">清空购物车</button>
        <button class="btn btn-preview" @click="openPreview">👁 预览</button>
        <button class="btn btn-primary" @click="submitUpdate">保存修改</button>
      </div>
    </div>

    <!-- 订单类型切换 -->
    <div class="type-switcher">
      <button class="type-btn" :class="{ active: orderType === 'supply' }" @click="switchOrderType('supply')">📦 供货模式</button>
      <button class="type-btn" :class="{ active: orderType === 'retail' }" @click="switchOrderType('retail')">🛒 零售模式</button>
      <div class="type-tip">
        <span v-if="orderType === 'supply'">供货模式：选择客户后自动加载协议价，可临时改价</span>
        <span v-else>零售模式：价格、规格手动填写，客户可空（散客）</span>
      </div>
    </div>

    <div class="order-layout">
      <!-- 左侧：产品搜索 + 购物车 -->
      <div class="left-panel">
        <!-- 产品搜索 -->
        <div class="panel-card product-search-card">
          <div class="card-title">
            产品搜索
            <span v-if="orderType === 'supply' && selectedCustomer" class="card-subtitle">当前客户：{{ selectedCustomer.name }}</span>
          </div>
          <div class="search-wrapper">
            <input v-model="productSearch" type="text" :placeholder="orderType === 'supply' && !form.customer_number ? '请先选择客户' : '输入产品名称搜索...'" :disabled="orderType === 'supply' && !form.customer_number" class="search-input" @input="onProductSearch" />
          </div>
          <div class="product-list">
            <div v-if="productLoading" class="loading-item">加载中...</div>
            <div v-else-if="orderType === 'supply' && !form.customer_number" class="empty-item">请先在右侧选择客户</div>
            <div v-else-if="productList.length === 0 && productSearch.trim()" class="empty-item">未找到相关产品</div>
            <div v-else-if="productList.length === 0" class="empty-item">输入关键词搜索产品</div>
            <div v-for="(item, idx) in productList" :key="`${item.product_name}-${item.specification}-${idx}`" class="product-item" @click="addToCart(item)">
              <div class="product-info">
                <div class="product-name">{{ item.product_name }}</div>
                <div class="product-spec">规格：{{ item.specification || '无' }} · 单位：{{ item.unit }}</div>
              </div>
              <div class="product-meta">
                <div v-if="orderType === 'supply'" class="product-price">
                  <span v-if="item.has_customer_price">¥{{ item.unit_price.toFixed(2) }}</span>
                  <span v-else class="no-price">无协议价</span>
                </div>
                <div class="product-stock" :class="{ 'out-of-stock': item.total_stock <= 0 }">库存：{{ item.total_stock }}</div>
              </div>
              <div class="add-btn">+</div>
            </div>
          </div>
        </div>

        <!-- 购物车 -->
        <div class="panel-card cart-card">
          <div class="card-title">已选产品 <span class="cart-count">{{ cart.length }} 项</span></div>
          <div class="cart-list">
            <div v-if="cart.length === 0" class="empty-item">购物车为空</div>
            <div v-for="(item, index) in cart" :key="index" class="cart-item">
              <div class="cart-info">
                <div class="cart-name">{{ item.product_name }}</div>
                <div class="cart-spec">规格：{{ item.specification || '无' }} · {{ item.unit }}</div>
              </div>
              <div class="cart-controls">
                <div class="qty-control">
                  <button class="qty-btn" @click="updateCartQty(index, -1)">-</button>
                  <input :value="item.quantity" type="number" step="1" min="0" class="qty-input" @input="setCartQty(index, Number(($event.target as HTMLInputElement).value))" />
                  <button class="qty-btn" @click="updateCartQty(index, 1)">+</button>
                </div>
                <div class="price-control">
                  <span class="price-prefix">¥</span>
                  <input v-model.number="item.unit_price" type="number" step="0.01" min="0" class="price-input" :placeholder="orderType === 'retail' ? '手填' : ''" @change="updateCartPrice(index, item.unit_price)" />
                  <button v-if="orderType === 'supply' && !item.use_customer_price" class="reset-price-btn" title="恢复协议价" @click="resetToCustomerPrice(index)">↻</button>
                </div>
                <div class="cart-subtotal">¥{{ item.subtotal.toFixed(2) }}</div>
                <button class="remove-btn" @click="removeCartItem(index)" title="移除">×</button>
              </div>
              <div v-if="orderType === 'supply' && item.use_customer_price" class="price-tag">协议价</div>
              <div v-else-if="orderType === 'supply'" class="price-tag modified">已改价</div>
            </div>
          </div>
          <div class="cart-summary">
            <div class="summary-row"><span>商品总数：</span><span class="summary-value">{{ totalQuantity }}</span></div>
            <div class="summary-row total-row"><span>订单总额：</span><span class="summary-value total-amount">¥{{ totalAmount.toFixed(2) }}</span></div>
          </div>
        </div>
      </div>

      <!-- 右侧：订单信息 -->
      <div class="right-panel">
        <div class="panel-card">
          <div class="card-title">订单信息</div>
          <div class="form-body">
            <!-- 订单状态 -->
            <div class="form-group">
              <label>订单状态</label>
              <select v-model.number="form.order_status" class="form-input">
                <option value="0">待处理</option>
                <option value="1">已确认</option>
                <option value="2">已发货</option>
                <option value="3">已完成</option>
                <option value="4">已取消</option>
              </select>
            </div>

            <!-- 已支付金额 -->
            <div class="form-group">
              <label>已支付金额</label>
              <input v-model.number="form.paid_amount" type="number" step="0.01" min="0" class="form-input" />
              <div class="field-hint">修改后系统会自动重新计算支付状态</div>
            </div>

            <div class="form-divider"></div>

            <!-- 客户选择 -->
            <div class="form-group">
              <label>客户单位 <span v-if="orderType === 'supply'" class="required">*</span><span v-else class="optional">（选填）</span></label>
              <div class="select-wrapper">
                <input v-model="customerSearch" type="text" :placeholder="orderType === 'supply' ? '输入客户名称/联系人/电话搜索' : '散客可留空，也可搜索选择客户'" class="form-input" @input="onCustomerInput" @focus="onCustomerSearch" />
                <ul v-if="customerList.length || customerLoading" class="dropdown-list">
                  <li v-if="customerLoading" class="loading-item">加载中...</li>
                  <li v-for="item in customerList" :key="item.number" class="dropdown-item" @click="selectCustomer(item)">
                    <div class="customer-row"><span class="customer-name">{{ item.name }}</span><span class="customer-contact">联系人：{{ item.contact || '无' }}</span></div>
                    <div class="customer-meta"><span v-if="item.phone_number" class="customer-phone">📞 {{ item.phone_number }}</span><span v-if="item.address" class="customer-address">📍 {{ item.address }}</span></div>
                  </li>
                </ul>
                <div v-if="selectedCustomer" class="selected-customer-tag">已选：{{ selectedCustomer.name }} · {{ selectedCustomer.contact || '无联系人' }}<span class="clear-customer" @click="clearSelectedCustomer">×</span></div>
              </div>
            </div>

            <!-- 收款单位 -->
            <div class="form-group">
              <label>收款单位</label>
              <input v-model="form.seller_name" type="text" placeholder="请输入收款单位" class="form-input" />
            </div>

            <div class="form-divider"></div>

            <!-- 收货信息 -->
            <div class="form-subtitle">收货信息</div>

            <div class="form-group">
              <label>收货单位 <span class="required">*</span></label>
              <input v-model="form.receiver_name" type="text" placeholder="请输入收货单位" class="form-input" />
            </div>

            <div class="form-group">
              <label>收货地址 <span class="required">*</span></label>
              <input v-model="form.receiver_address" type="text" placeholder="请输入收货地址" class="form-input" />
            </div>

            <div class="form-group">
              <label>联系电话 <span class="optional">（选填）</span></label>
              <input v-model="form.receiver_phone" type="text" placeholder="选填" class="form-input" />
            </div>

            <div class="form-divider"></div>

            <div class="form-group">
              <label>备注</label>
              <textarea v-model="form.remark" rows="3" placeholder="选填" class="form-textarea"></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 订单预览弹窗 -->
    <div v-if="previewVisible" class="preview-overlay" @click.self="closePreview">
      <div class="preview-modal">
        <div class="preview-header">
          <h3>订单预览</h3>
          <div class="preview-actions">
            <button class="btn btn-settings" @click="openSettingsModal">⚙ 更多设置</button>
            <button class="btn btn-download" :disabled="generatingImage" @click="downloadOrderImage">{{ generatingImage ? '生成中...' : '⬇ 下载图片' }}</button>
            <button class="btn btn-print" :disabled="generatingImage" @click="printOrder">{{ generatingImage ? '生成中...' : '🖨 打印' }}</button>
            <button class="btn btn-close-preview" @click="closePreview">×</button>
          </div>
        </div>
        <div class="preview-body">
          <div ref="previewRef" class="order-print-template">
            <div class="print-header">
              <div class="print-title">{{ previewData.seller_name }}销货清单</div>
              <div class="print-subtitle">{{ previewData.order_type === 'supply' ? '供货单' : '零售单' }}</div>
            </div>
            <div class="print-info-grid">
              <div v-if="isVisible('info', 'customer_name')" class="print-info-row"><span class="print-label">客户单位：</span><span class="print-value">{{ previewData.customer_name }}</span></div>
              <div v-if="isVisible('info', 'seller_name')" class="print-info-row"><span class="print-label">收款单位：</span><span class="print-value">{{ previewData.seller_name }}</span></div>
              <div v-if="isVisible('info', 'receiver_name')" class="print-info-row"><span class="print-label">收货单位：</span><span class="print-value">{{ previewData.receiver_name }}</span></div>
              <div v-if="isVisible('info', 'receiver_phone')" class="print-info-row"><span class="print-label">联系电话：</span><span class="print-value">{{ previewData.receiver_phone }}</span></div>
              <div v-if="isVisible('info', 'receiver_address')" class="print-info-row full-row"><span class="print-label">收货地址：</span><span class="print-value">{{ previewData.receiver_address }}</span></div>
              <div v-if="isVisible('info', 'create_time')" class="print-info-row full-row"><span class="print-label">创建时间：</span><span class="print-value">{{ previewData.create_time }}</span></div>
            </div>
            <table class="print-table">
              <thead>
                <tr>
                  <th v-if="isVisible('columns', 'index')">序号</th>
                  <th v-if="isVisible('columns', 'product_name')">商品名称</th>
                  <th v-if="isVisible('columns', 'specification')">规格</th>
                  <th v-if="isVisible('columns', 'unit')">单位</th>
                  <th v-if="isVisible('columns', 'quantity')">数量</th>
                  <th v-if="isVisible('columns', 'unit_price')">单价</th>
                  <th v-if="isVisible('columns', 'subtotal')">金额</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in previewData.items" :key="idx">
                  <td v-if="isVisible('columns', 'index')" class="center">{{ idx + 1 }}</td>
                  <td v-if="isVisible('columns', 'product_name')">{{ item.product_name }}</td>
                  <td v-if="isVisible('columns', 'specification')" class="center">{{ item.specification || '-' }}</td>
                  <td v-if="isVisible('columns', 'unit')" class="center">{{ item.unit }}</td>
                  <td v-if="isVisible('columns', 'quantity')" class="right">{{ item.quantity }}</td>
                  <td v-if="isVisible('columns', 'unit_price')" class="right">¥{{ item.unit_price.toFixed(2) }}</td>
                  <td v-if="isVisible('columns', 'subtotal')" class="right">¥{{ item.subtotal.toFixed(2) }}</td>
                </tr>
              </tbody>
              <tfoot v-if="isVisible('sections', 'total_row')">
                <tr>
                  <td :colspan="Math.max(1, visibleColumnCount - 3)" class="right total-label">合计</td>
                  <td v-if="isVisible('columns', 'quantity')" class="right total-qty">{{ previewData.total_quantity }}</td>
                  <td v-if="isVisible('columns', 'unit_price')"></td>
                  <td v-if="isVisible('columns', 'subtotal')" class="right total-amount">¥{{ previewData.total_amount.toFixed(2) }}</td>
                </tr>
              </tfoot>
            </table>
            <div class="print-footer">
              <div v-if="isVisible('sections', 'footer_seller_address')" class="footer-section"><span class="footer-label">收款单位地址：{{ previewData.seller_address }}</span></div>
              <div v-if="isVisible('sections', 'footer_seller_phone')" class="footer-section"><span class="footer-label">收款单位电话：{{ previewData.seller_phone }}</span></div>
              <div v-if="isVisible('sections', 'footer_remark')" class="footer-section"><span class="footer-label">备注：{{ previewData.remark }}</span></div>
              <div v-if="isVisible('sections', 'signature_row')" class="signature-row"><div class="signature-item">客户签字：____________</div><div class="signature-item">经办人签字：____________</div></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 更多设置弹窗 -->
    <div v-if="settingsModalVisible" class="settings-overlay" @click.self="closeSettingsModal">
      <div class="settings-modal">
        <div class="settings-header">
          <h3>更多设置</h3>
          <button class="btn-close" @click="closeSettingsModal">×</button>
        </div>
        <div class="settings-body">
          <p class="settings-tip">勾选字段在前端预览和打印中渲染，取消勾选则不渲染。</p>
          <div class="settings-group">
            <div class="settings-group-title">订单信息区</div>
            <div class="settings-items">
              <label v-for="f in fieldConfig.info" :key="f.key" class="settings-item"><input type="checkbox" v-model="f.visible" /><span>{{ f.label }}</span></label>
            </div>
          </div>
          <div class="settings-group">
            <div class="settings-group-title">商品表格列</div>
            <div class="settings-items">
              <label v-for="f in fieldConfig.columns" :key="f.key" class="settings-item"><input type="checkbox" v-model="f.visible" /><span>{{ f.label }}</span></label>
            </div>
          </div>
          <div class="settings-group">
            <div class="settings-group-title">其他区块</div>
            <div class="settings-items">
              <label v-for="f in fieldConfig.sections" :key="f.key" class="settings-item"><input type="checkbox" v-model="f.visible" /><span>{{ f.label }}</span></label>
            </div>
          </div>
        </div>
        <div class="settings-footer">
          <button class="btn btn-reset" @click="resetFieldConfig">恢复默认</button>
          <button class="btn btn-primary" @click="closeSettingsModal">完成</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
@use './order-form.scss';
.order-number { font-size: 1rem; color: var(--text-muted); font-weight: normal; margin-left: 0.5rem; }
</style>