<script lang="ts" setup>
import { ref, reactive, computed, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
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
  status: number; // 0.启用 1.禁用 2.异常
}

// 产品搜索结果（按名称+规格聚合，显示总可用库存）
// 供货模式下还会带上该客户的协议单价
interface ProductSearchItem {
  product_name: string;
  specification: string;
  unit: string;
  total_stock: number;
  unit_price: number;       // 供货模式=客户协议价，零售模式=0（手填）
  supplier_count: number;
  has_customer_price: boolean; // 供货模式下是否有协议价
}

// 购物车项（用户已选）
interface CartItem {
  product_name: string;
  specification: string;
  unit: string;
  quantity: number;
  unit_price: number;
  subtotal: number;
  // 供货模式标记：是否使用协议价（用户未手动改价）
  use_customer_price: boolean;
}

// 订单表单
interface OrderForm {
  order_type: OrderType;
  customer_number: number;
  customer_name: string;
  seller_name: string;
  receiver_name: string;
  receiver_address: string;
  receiver_phone: string;
  remark: string;
}

const router = useRouter();

// 订单类型
const orderType = ref<OrderType>('supply');

// 客户搜索相关
const customerSearch = ref('');
const customerList = ref<CustomerOption[]>([]);
const customerLoading = ref(false);
const selectedCustomer = ref<CustomerOption | null>(null);
let customerSearchTimer: ReturnType<typeof setTimeout> | null = null;

// 产品搜索相关
const productSearch = ref('');
const productList = ref<ProductSearchItem[]>([]);
const productLoading = ref(false);
let productSearchTimer: ReturnType<typeof setTimeout> | null = null;

// 购物车
const cart = ref<CartItem[]>([]);

// 订单表单
const form = reactive<OrderForm>({
  order_type: 'supply',
  customer_number: 0,
  customer_name: '',
  seller_name: SELLER_NAME,
  receiver_name: '',
  receiver_address: '',
  receiver_phone: '',
  remark: '',
});

// 切换订单类型时重置相关字段
const switchOrderType = (type: OrderType) => {
  orderType.value = type;
  form.order_type = type;
  // 零售模式：清空客户信息，价格手填
  if (type === 'retail') {
    selectedCustomer.value = null;
    form.customer_number = 0;
    form.customer_name = '';
    customerSearch.value = '';
    form.receiver_name = '';
    form.receiver_address = '';
    form.receiver_phone = '';
    // 清空购物车（价格规则变了）
    cart.value = [];
    productList.value = [];
    productSearch.value = '';
  } else {
    // 供货模式：保留收款单位
    form.seller_name = SELLER_NAME;
  }
};

// 总计
const totalQuantity = computed(() => {
  return cart.value.reduce((sum, item) => sum + item.quantity, 0);
});

const totalAmount = computed(() => {
  return cart.value.reduce((sum, item) => sum + item.subtotal, 0);
});

// 客户搜索（供货模式精确选择客户；零售模式也可选）
// 防抖 300ms
const onCustomerSearch = () => {
  if (customerSearchTimer) {
    clearTimeout(customerSearchTimer);
  }
  customerSearchTimer = setTimeout(async () => {
    await fetchCustomers();
  }, 300);
};

const fetchCustomers = async () => {
  const keyword = customerSearch.value.trim();
  if (!keyword) {
    customerList.value = [];
    return;
  }
  customerLoading.value = true;
  try {
    // 仅查启用客户（status=0），避免给已禁用客户下订单
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
  // 收货单位默认=客户单位
  form.receiver_name = item.name;
  form.receiver_address = item.address;
  form.receiver_phone = item.phone_number;
  customerSearch.value = `${item.name}（${item.contact || '无联系人'}）`;
  customerList.value = [];
  // 切换客户后清空购物车（价格规则变了）
  cart.value = [];
  productList.value = [];
  productSearch.value = '';
};

// 清除已选客户（用户清空搜索框时）
const onCustomerInput = () => {
  if (!customerSearch.value.trim()) {
    selectedCustomer.value = null;
    form.customer_number = 0;
    form.customer_name = '';
    form.receiver_name = '';
    form.receiver_address = '';
    form.receiver_phone = '';
    cart.value = [];
    productList.value = [];
  }
  onCustomerSearch();
};

// 手动清除已选客户
const clearSelectedCustomer = () => {
  selectedCustomer.value = null;
  form.customer_number = 0;
  form.customer_name = '';
  customerSearch.value = '';
  form.receiver_name = '';
  form.receiver_address = '';
  form.receiver_phone = '';
  cart.value = [];
  productList.value = [];
  customerList.value = [];
};

// 产品搜索
// 供货模式：按名称+规格聚合库存，并 JOIN 客户价格表带出协议价
// 零售模式：仅按名称+规格聚合库存，价格留空
// 防抖 300ms
const onProductSearch = () => {
  if (productSearchTimer) {
    clearTimeout(productSearchTimer);
  }
  productSearchTimer = setTimeout(async () => {
    await fetchProducts();
  }, 300);
};

const fetchProducts = async () => {
  if (!productSearch.value.trim()) {
    productList.value = [];
    return;
  }
  // 供货模式必须先选客户
  if (orderType.value === 'supply' && !form.customer_number) {
    productList.value = [];
    return;
  }
  productLoading.value = true;
  try {
    let url = `${API_URL}v1/api/warehouse/inventory/searchProducts?keyword=${encodeURIComponent(productSearch.value.trim())}&limit=20`;
    if (orderType.value === 'supply') {
      url += `&customer_number=${form.customer_number}`;
    }
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

// 添加到购物车
const addToCart = (item: ProductSearchItem) => {
  // 同名同规格合并
  const existing = cart.value.find(
    c => c.product_name === item.product_name && c.specification === item.specification
  );

  // 库存校验：购物车中已有数量 + 1 不能超过可用库存
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
    if (item.total_stock <= 0) {
      alert('该产品暂无库存');
      return;
    }
    // 零售模式下若价格为0，提示用户手填
    if (orderType.value === 'retail' && item.unit_price === 0) {
      cart.value.push({
        product_name: item.product_name,
        specification: item.specification,
        unit: item.unit,
        quantity: 1,
        unit_price: 0,
        subtotal: 0,
        use_customer_price: false,
      });
      // 自动聚焦到价格输入框（用户会手动修改）
      return;
    }
    cart.value.push({
      product_name: item.product_name,
      specification: item.specification,
      unit: item.unit,
      quantity: 1,
      unit_price: item.unit_price,
      subtotal: item.unit_price,
      use_customer_price: orderType.value === 'supply' && item.has_customer_price,
    });
  }
};

// 更新购物车数量（增减 1，小于等于 0 则移除）
const updateCartQty = (index: number, delta: number) => {
  const item = cart.value[index];
  if (!item) return;
  setCartQty(index, item.quantity + delta);
};

// 直接设置购物车数量（input 输入）
const setCartQty = (index: number, qty: number) => {
  const item = cart.value[index];
  if (!item) return;
  const newQty = Number(qty) || 0;
  if (newQty <= 0) {
    cart.value.splice(index, 1);
    return;
  }
  // 库存校验：从 productList 中查找对应产品的总可用库存
  const product = productList.value.find(
    p => p.product_name === item.product_name && p.specification === item.specification
  );
  if (product && newQty > product.total_stock) {
    alert(`库存不足，当前可用库存：${product.total_stock}`);
    // 回退到库存上限
    item.quantity = product.total_stock;
    item.subtotal = +(item.quantity * item.unit_price).toFixed(2);
    return;
  }
  item.quantity = newQty;
  item.subtotal = +(item.quantity * item.unit_price).toFixed(2);
};

// 手动修改单价（供货模式下临时改价）
const updateCartPrice = (index: number, price: number) => {
  const item = cart.value[index];
  if (!item) return;
  item.unit_price = price;
  item.subtotal = +(item.quantity * item.unit_price).toFixed(2);
  // 标记为已手动改价
  item.use_customer_price = false;
};

// 重置为协议价（供货模式下，用户手动改价后可恢复）
const resetToCustomerPrice = (index: number) => {
  const item = cart.value[index];
  if (!item) return;
  // 从产品列表找到原始协议价
  const product = productList.value.find(
    p => p.product_name === item.product_name && p.specification === item.specification
  );
  if (product && product.has_customer_price) {
    item.unit_price = product.unit_price;
    item.subtotal = +(item.quantity * item.unit_price).toFixed(2);
    item.use_customer_price = true;
  } else {
    alert('未找到协议价');
  }
};

// 删除购物车项
const removeCartItem = (index: number) => {
  cart.value.splice(index, 1);
};

// 提交订单
const submitOrder = async () => {
  if (orderType.value === 'supply' && !form.customer_number) {
    alert('供货模式必须选择客户');
    return;
  }
  if (cart.value.length === 0) {
    alert('请至少添加一个产品');
    return;
  }
  // 零售模式必须有客户名（散客也行）
  if (orderType.value === 'retail' && !form.receiver_name.trim()) {
    alert('请填写收货单位');
    return;
  }
  if (!form.receiver_address.trim()) {
    alert('请填写收货地址');
    return;
  }
  // 校验零售模式或供货模式下未填价格的项目
  const zeroPriceItem = cart.value.find(item => item.unit_price <= 0);
  if (zeroPriceItem) {
    alert(`请填写"${zeroPriceItem.product_name}"的单价`);
    return;
  }

  // 未填字段自动填充"未填写"
  const payload = {
    order_type: form.order_type,
    customer_number: form.customer_number,
    customer_name: form.customer_name || '未填写',
    seller_name: form.seller_name || '未填写',
    receiver_name: form.receiver_name || '未填写',
    receiver_address: form.receiver_address || '未填写',
    receiver_phone: form.receiver_phone || '未填写',
    remark: form.remark || '',
    total_quantity: totalQuantity.value,
    total_amount: totalAmount.value,
    items: cart.value.map(item => ({
      ...item,
      // 价格已在前面校验过
    })),
  };

  // 生成订单图片（html2canvas 截图预览区域）
  // 如果预览弹窗未打开，先临时打开以便截图
  const wasPreviewOpen = previewVisible.value;
  if (!wasPreviewOpen) {
    previewVisible.value = true;
  }
  await nextTick();
  // 等待 DOM 渲染完成
  await new Promise(resolve => setTimeout(resolve, 100));

  let imageData: string | null = null;
  try {
    imageData = await generateOrderImage();
  } catch (err) {
    console.error('生成订单图片失败', err);
  }

  // 截图完成后关闭预览（如果是临时打开的）
  if (!wasPreviewOpen) {
    previewVisible.value = false;
  }

  // 调用创建订单接口
  try {
    const res = await fetch(`${API_URL}v1/api/order/set`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...payload, image_path: imageData }),
    });
    const data = await res.json();
    if (data.success) {
      alert(`订单创建成功！订单号：${data.data?.order_number ?? ''}`);
      // 跳转到订单详情页
      router.push(`/order/detail/${data.data?.order_number}`);
    } else {
      alert(`订单创建失败：${data.message || '未知错误'}`);
    }
  } catch (err) {
    console.error('创建订单失败', err);
    alert('创建订单失败，请检查网络或服务器');
  }
};

// ===== 订单预览 / 打印 / 图片生成 =====
const previewVisible = ref(false);
const previewRef = ref<HTMLElement | null>(null);
const generatingImage = ref(false);

// ===== 字段渲染配置（"更多设置"弹窗）=====
// 深拷贝默认配置，用户临时调整不影响全局默认
const fieldConfig = reactive<OrderFieldSettings>({
  info: orderFieldSettings.info.map(f => ({ ...f })),
  columns: orderFieldSettings.columns.map(f => ({ ...f })),
  sections: orderFieldSettings.sections.map(f => ({ ...f })),
});

const settingsModalVisible = ref(false);

const openSettingsModal = () => {
  settingsModalVisible.value = true;
};

const closeSettingsModal = () => {
  settingsModalVisible.value = false;
};

// 重置为默认配置
const resetFieldConfig = () => {
  fieldConfig.info = orderFieldSettings.info.map(f => ({ ...f }));
  fieldConfig.columns = orderFieldSettings.columns.map(f => ({ ...f }));
  fieldConfig.sections = orderFieldSettings.sections.map(f => ({ ...f }));
};

// 工具方法：判断某个 key 是否渲染
const isVisible = (group: 'info' | 'columns' | 'sections', key: string): boolean => {
  const item = fieldConfig[group].find(f => f.key === key);
  return item ? item.visible : true;
};

// 表格当前可见列数（用于合计行 colspan 计算）
const visibleColumnCount = computed(() => {
  return fieldConfig.columns.filter(c => c.visible).length;
});

// 预览用的订单数据（自动填充"未填写"）
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

// 打开预览
const openPreview = () => {
  if (cart.value.length === 0) {
    alert('请先添加产品');
    return;
  }
  previewVisible.value = true;
};

// 关闭预览
const closePreview = () => {
  previewVisible.value = false;
};

// 生成订单图片（base64）
const generateOrderImage = async (): Promise<string | null> => {
  if (!previewRef.value) return null;
  generatingImage.value = true;
  try {
    // 等待 DOM 更新
    await nextTick();
    const canvas = await html2canvas(previewRef.value, {
      scale: 2,
      backgroundColor: '#ffffff',
      useCORS: true,
      logging: false,
    });
    return canvas.toDataURL('image/png');
  } catch (err) {
    console.error('生成图片失败', err);
    return null;
  } finally {
    generatingImage.value = false;
  }
};

// 打印订单
const printOrder = async () => {
  if (!previewRef.value) return;
  generatingImage.value = true;
  try {
    await nextTick();
    const canvas = await html2canvas(previewRef.value, {
      scale: 2,
      backgroundColor: '#ffffff',
      useCORS: true,
      logging: false,
    });
    const imageData = canvas.toDataURL('image/png');

    // 打开新窗口打印
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    if (!printWindow) {
      alert('请允许弹出窗口以打印订单');
      return;
    }
    printWindow.document.write(`
      <!DOCTYPE html>
      <html>
      <head>
        <title>订单打印 - ${previewData.value.customer_name}</title>
        <style>
          * { margin: 0; padding: 0; box-sizing: border-box; }
          body { padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Microsoft YaHei", sans-serif; }
          img { max-width: 100%; display: block; margin: 0 auto; }
          @media print {
            body { padding: 0; }
            @page { margin: 10mm; }
          }
        </style>
      </head>
      <body>
        <img src="${imageData}" alt="订单图片" />
      </body>
      </html>
    `);
    printWindow.document.close();
    // 等图片加载完再打印
    printWindow.onload = () => {
      setTimeout(() => {
        printWindow.print();
      }, 300);
    };
  } catch (err) {
    console.error('打印失败', err);
    alert('打印失败，请重试');
  } finally {
    generatingImage.value = false;
  }
};

// 下载订单图片
const downloadOrderImage = async () => {
  if (!previewRef.value) return;
  generatingImage.value = true;
  try {
    await nextTick();
    const canvas = await html2canvas(previewRef.value, {
      scale: 2,
      backgroundColor: '#ffffff',
      useCORS: true,
      logging: false,
    });
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

// 监听订单类型变化，同步到 form
watch(orderType, (val) => {
  form.order_type = val;
});
</script>

<template>
  <div class="MainBox">
    <div class="page-header">
      <h2>创建订单</h2>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="cart = []">清空购物车</button>
        <button class="btn btn-preview" @click="openPreview">👁 预览</button>
        <button class="btn btn-primary" @click="submitOrder">提交订单</button>
      </div>
    </div>

    <!-- 订单类型切换 -->
    <div class="type-switcher">
      <button
        class="type-btn"
        :class="{ active: orderType === 'supply' }"
        @click="switchOrderType('supply')"
      >📦 供货模式</button>
      <button
        class="type-btn"
        :class="{ active: orderType === 'retail' }"
        @click="switchOrderType('retail')"
      >🛒 零售模式</button>
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
            <span v-if="orderType === 'supply' && selectedCustomer" class="card-subtitle">
              当前客户：{{ selectedCustomer.name }}
            </span>
          </div>
          <div class="search-wrapper">
            <input
              v-model="productSearch"
              type="text"
              :placeholder="orderType === 'supply' && !form.customer_number ? '请先选择客户' : '输入产品名称搜索...'"
              :disabled="orderType === 'supply' && !form.customer_number"
              class="search-input"
              @input="onProductSearch"
            />
          </div>
          <div class="product-list">
            <div v-if="productLoading" class="loading-item">加载中...</div>
            <div v-else-if="orderType === 'supply' && !form.customer_number" class="empty-item">
              请先在右侧选择客户
            </div>
            <div v-else-if="productList.length === 0 && productSearch.trim()" class="empty-item">
              未找到相关产品
            </div>
            <div v-else-if="productList.length === 0" class="empty-item">
              输入关键词搜索产品
            </div>
            <div
              v-for="(item, idx) in productList"
              :key="`${item.product_name}-${item.specification}-${idx}`"
              class="product-item"
              @click="addToCart(item)"
            >
              <div class="product-info">
                <div class="product-name">{{ item.product_name }}</div>
                <div class="product-spec">规格：{{ item.specification || '无' }} · 单位：{{ item.unit }}</div>
              </div>
              <div class="product-meta">
                <div v-if="orderType === 'supply'" class="product-price">
                  <span v-if="item.has_customer_price">¥{{ item.unit_price.toFixed(2) }}</span>
                  <span v-else class="no-price">无协议价</span>
                </div>
                <div class="product-stock" :class="{ 'out-of-stock': item.total_stock <= 0 }">
                  库存：{{ item.total_stock }}
                </div>
              </div>
              <div class="add-btn">+</div>
            </div>
          </div>
        </div>

        <!-- 购物车 -->
        <div class="panel-card cart-card">
          <div class="card-title">
            已选产品
            <span class="cart-count">{{ cart.length }} 项</span>
          </div>
          <div class="cart-list">
            <div v-if="cart.length === 0" class="empty-item">
              购物车为空
            </div>
            <div v-for="(item, index) in cart" :key="index" class="cart-item">
              <div class="cart-info">
                <div class="cart-name">{{ item.product_name }}</div>
                <div class="cart-spec">规格：{{ item.specification || '无' }} · {{ item.unit }}</div>
              </div>
              <div class="cart-controls">
                <div class="qty-control">
                  <button class="qty-btn" @click="updateCartQty(index, -1)" title="减少">-</button>
                  <input
                    :value="item.quantity"
                    type="number"
                    step="1"
                    min="0"
                    class="qty-input"
                    @input="setCartQty(index, Number(($event.target as HTMLInputElement).value))"
                  />
                  <button class="qty-btn" @click="updateCartQty(index, 1)" title="增加">+</button>
                </div>
                <div class="price-control">
                  <span class="price-prefix">¥</span>
                  <input
                    v-model.number="item.unit_price"
                    type="number"
                    step="0.01"
                    min="0"
                    class="price-input"
                    :placeholder="orderType === 'retail' ? '手填' : ''"
                    @change="updateCartPrice(index, item.unit_price)"
                  />
                  <button
                    v-if="orderType === 'supply' && !item.use_customer_price"
                    class="reset-price-btn"
                    title="恢复协议价"
                    @click="resetToCustomerPrice(index)"
                  >↻</button>
                </div>
                <div class="cart-subtotal">¥{{ item.subtotal.toFixed(2) }}</div>
                <button class="remove-btn" @click="removeCartItem(index)" title="移除">×</button>
              </div>
              <div v-if="orderType === 'supply' && item.use_customer_price" class="price-tag">协议价</div>
              <div v-else-if="orderType === 'supply'" class="price-tag modified">已改价</div>
            </div>
          </div>
          <div class="cart-summary">
            <div class="summary-row">
              <span>商品总数：</span>
              <span class="summary-value">{{ totalQuantity }}</span>
            </div>
            <div class="summary-row total-row">
              <span>订单总额：</span>
              <span class="summary-value total-amount">¥{{ totalAmount.toFixed(2) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 右侧：订单信息 -->
      <div class="right-panel">
        <div class="panel-card">
          <div class="card-title">订单信息</div>
          <div class="form-body">
            <!-- 客户选择（供货模式必填，零售模式可选） -->
            <div class="form-group">
              <label>
                客户单位
                <span v-if="orderType === 'supply'" class="required">*</span>
                <span v-else class="optional">（选填）</span>
              </label>
              <div class="select-wrapper">
                <input
                  v-model="customerSearch"
                  type="text"
                  :placeholder="orderType === 'supply' ? '输入客户名称/联系人/电话搜索' : '散客可留空，也可搜索选择客户'"
                  class="form-input"
                  @input="onCustomerInput"
                  @focus="onCustomerSearch"
                />
                <ul v-if="customerList.length || customerLoading" class="dropdown-list">
                  <li v-if="customerLoading" class="loading-item">加载中...</li>
                  <li
                    v-for="item in customerList"
                    :key="item.number"
                    class="dropdown-item"
                    @click="selectCustomer(item)"
                  >
                    <div class="customer-row">
                      <span class="customer-name">{{ item.name }}</span>
                      <span class="customer-contact">联系人：{{ item.contact || '无' }}</span>
                    </div>
                    <div class="customer-meta">
                      <span v-if="item.phone_number" class="customer-phone">📞 {{ item.phone_number }}</span>
                      <span v-if="item.address" class="customer-address">📍 {{ item.address }}</span>
                    </div>
                  </li>
                </ul>
                <div v-if="selectedCustomer" class="selected-customer-tag">
                  已选：{{ selectedCustomer.name }} · {{ selectedCustomer.contact || '无联系人' }}
                  <span class="clear-customer" @click="clearSelectedCustomer">×</span>
                </div>
              </div>
            </div>

            <!-- 收款单位（从config读取，可改） -->
            <div class="form-group">
              <label>收款单位</label>
              <input v-model="form.seller_name" type="text" placeholder="请输入收款单位" class="form-input" />
              <div class="field-hint">默认从 config/seller.ts 读取</div>
            </div>

            <div class="form-divider"></div>

            <!-- 收货信息 -->
            <div class="form-subtitle">收货信息</div>

            <div class="form-group">
              <label>
                收货单位
                <span class="required">*</span>
                <span class="optional">（默认=客户单位，可修改）</span>
              </label>
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
            <button class="btn btn-download" :disabled="generatingImage" @click="downloadOrderImage">
              {{ generatingImage ? '生成中...' : '⬇ 下载图片' }}
            </button>
            <button class="btn btn-print" :disabled="generatingImage" @click="printOrder">
              {{ generatingImage ? '生成中...' : '🖨 打印' }}
            </button>
            <button class="btn btn-close-preview" @click="closePreview">×</button>
          </div>
        </div>
        <div class="preview-body">
          <!-- 此 div 是 html2canvas 截图区域 -->
          <div ref="previewRef" class="order-print-template">
            <div class="print-header">
              <div class="print-title">{{ previewData.seller_name }}销货清单</div>
              <div class="print-subtitle">
                {{ previewData.order_type === 'supply' ? '供货单' : '零售单' }}
              </div>
            </div>

            <div class="print-info-grid">
              <div v-if="isVisible('info', 'customer_name')" class="print-info-row">
                <span class="print-label">客户单位：</span>
                <span class="print-value">{{ previewData.customer_name }}</span>
              </div>
              <div v-if="isVisible('info', 'seller_name')" class="print-info-row">
                <span class="print-label">收款单位：</span>
                <span class="print-value">{{ previewData.seller_name }}</span>
              </div>
              <div v-if="isVisible('info', 'receiver_name')" class="print-info-row">
                <span class="print-label">收货单位：</span>
                <span class="print-value">{{ previewData.receiver_name }}</span>
              </div>
              <div v-if="isVisible('info', 'receiver_phone')" class="print-info-row">
                <span class="print-label">联系电话：</span>
                <span class="print-value">{{ previewData.receiver_phone }}</span>
              </div>
              <div v-if="isVisible('info', 'receiver_address')" class="print-info-row full-row">
                <span class="print-label">收货地址：</span>
                <span class="print-value">{{ previewData.receiver_address }}</span>
              </div>
              <div v-if="isVisible('info', 'create_time')" class="print-info-row full-row">
                <span class="print-label">创建时间：</span>
                <span class="print-value">{{ previewData.create_time }}</span>
              </div>
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
              <div v-if="isVisible('sections', 'footer_seller_address')" class="footer-section">
                <span class="footer-label">收款单位地址：{{ previewData.seller_address }}</span>
              </div>
              <div v-if="isVisible('sections', 'footer_seller_phone')" class="footer-section">
                <span class="footer-label">收款单位电话：{{ previewData.seller_phone }}</span>
              </div>
              <div v-if="isVisible('sections', 'footer_remark')" class="footer-section">
                <span class="footer-label">备注：{{ previewData.remark }}</span>
              </div>
              <div v-if="isVisible('sections', 'signature_row')" class="signature-row">
                <div class="signature-item">客户签字：____________</div>
                <div class="signature-item">经办人签字：____________</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 更多设置弹窗：控制预览/打印中哪些字段不渲染 -->
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
              <label v-for="f in fieldConfig.info" :key="f.key" class="settings-item">
                <input type="checkbox" v-model="f.visible" />
                <span>{{ f.label }}</span>
              </label>
            </div>
          </div>

          <div class="settings-group">
            <div class="settings-group-title">商品表格列</div>
            <div class="settings-items">
              <label v-for="f in fieldConfig.columns" :key="f.key" class="settings-item">
                <input type="checkbox" v-model="f.visible" />
                <span>{{ f.label }}</span>
              </label>
            </div>
          </div>

          <div class="settings-group">
            <div class="settings-group-title">其他区块</div>
            <div class="settings-items">
              <label v-for="f in fieldConfig.sections" :key="f.key" class="settings-item">
                <input type="checkbox" v-model="f.visible" />
                <span>{{ f.label }}</span>
              </label>
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
@import './order-form.scss';
</style>
