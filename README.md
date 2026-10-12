# TCOS 进销存管理系统

一套基于 **Vue 3 + Vite + PHP + MySQL** 的轻量级进销存管理系统，涵盖仓库、客户、供应商、订单、财务五大核心模块，支持深色模式、响应式布局、数据可视化。

---

## 目录

- [系统概览](#系统概览)
- [技术栈](#技术栈)
- [目录结构](#目录结构)
- [快速开始](#快速开始)
- [模块说明](#模块说明)
- [数据库](#数据库)
- [API 规范](#api-规范)
- [配置说明](#配置说明)
- [打包部署](#打包部署)

---

## 系统概览

TCOS 是一套面向中小商贸企业的进销存一体化管理系统，核心能力包括：

| 模块 | 主要功能 |
|------|----------|
| 🏠 首页看板 | 数据统计卡片、收支趋势图、资金分布、欠款TOP5、常用功能快捷入口 |
| 📦 仓库管理 | 仓库档案、库存清单、手动出入库、采购入库、低库存预警 |
| 👥 客户管理 | 客户档案、客户协议价、客户商品管理、客户对账 |
| 🚚 供应商管理 | 供应商档案、供应商报价、供应商商品管理 |
| 📋 订单管理 | 销售订单创建/编辑/详情、订单状态流转、收款记录、打印导出 |
| 💰 财务管理 | 资金流水、应收应付、利润分析、对账管理、客户对账 |

---

## 技术栈

### 前端
- **框架**：Vue 3.5 + Composition API + `<script setup>`
- **构建**：Vite 8 + TypeScript 6
- **路由**：Vue Router 5
- **样式**：SCSS + CSS 变量（深色/浅色主题）
- **图表**：Chart.js 4
- **导出**：html2canvas（图片导出）
- **代码规范**：ESLint + Prettier + oxlint

### 后端
- **语言**：PHP 7.4+
- **路由**：Mezon Router
- **数据库**：MySQL 5.7+ / 8.x（PDO 连接）
- **架构**：单文件入口 + 模块化 API

---

## 目录结构

```
TCOS/
├── src/                          # 前端源码
│   ├── pages/                    # 页面组件
│   │   ├── HomeView.vue          # 首页数据看板
│   │   ├── warehouse/            # 仓库模块
│   │   │   ├── WarehouseList.vue     # 仓库列表
│   │   │   ├── WarehouseCreate.vue   # 新建/编辑仓库
│   │   │   ├── WarehouseManage.vue   # 仓库库存详情
│   │   │   ├── WarehouseInventory.vue # 库存总览
│   │   │   ├── WarehouseIO.vue       # 手动出入库
│   │   │   └── WarehousePurchase.vue  # 采购管理
│   │   ├── customer/             # 客户模块
│   │   │   ├── CustomerList.vue
│   │   │   ├── CustomerCreate.vue
│   │   │   └── CustomerManage.vue    # 客户商品/协议价
│   │   ├── supplier/             # 供应商模块
│   │   │   ├── SupplierList.vue
│   │   │   ├── SupplierCreate.vue
│   │   │   └── SupplierManage.vue    # 供应商商品/报价
│   │   ├── order/                # 订单模块
│   │   │   ├── OrderList.vue
│   │   │   ├── OrderCreate.vue
│   │   │   ├── OrderEdit.vue
│   │   │   ├── OrderDetail.vue
│   │   │   └── order-form.scss
│   │   └── finance/              # 财务模块
│   │       ├── FinanceOverview.vue    # 财务概览
│   │       ├── FinanceFlow.vue        # 资金流水
│   │       ├── FinanceReceivable.vue  # 应收账款
│   │       ├── FinancePayable.vue     # 应付账款
│   │       ├── FinanceProfit.vue      # 利润分析
│   │       ├── FinanceReconciliation.vue # 对账管理
│   │       └── FinanceCustomerReconciliation.vue # 客户对账
│   ├── components/               # 公共组件
│   │   ├── BaseTable.vue
│   │   ├── CollapseMenu.vue
│   │   └── PagInation.vue
│   ├── config/                   # 前端配置
│   │   ├── net.ts                # API 地址配置
│   │   ├── seller.ts             # 商家信息配置
│   │   ├── confirmSettings.ts    # 确认弹窗配置
│   │   └── orderFieldSettings.ts # 订单字段显隐配置
│   ├── router/
│   │   └── index.ts              # 路由定义
│   ├── styles/
│   │   └── global.scss           # 全局样式 + 主题变量
│   ├── App.vue
│   └── main.ts
│
├── src-net/                      # 后端源码（PHP）
│   ├── .htaccess                 # URL 重写
│   ├── index.php                 # 入口文件
│   └── v1/
│       ├── api.php               # 数据库初始化页面
│       ├── index.php             # API 路由入口
│       ├── api/                  # API 接口
│       │   ├── config/           # 配置文件
│       │   │   ├── pdo.php       # 数据库连接配置
│       │   │   ├── image.config.php # 图片存储配置
│       │   │   ├── order.config.php # 订单配置
│       │   │   └── log.config.php
│       │   ├── utils/            # 工具类
│       │   │   ├── get.php       # 通用查询基类
│       │   │   ├── set.php       # 通用写入基类
│       │   │   ├── update.php    # 通用更新基类
│       │   │   ├── image.php     # 图片处理
│       │   │   └── log/          # 日志工具
│       │   ├── warehouse/        # 仓库 API
│       │   │   ├── get.php / set.php / delete.php
│       │   │   ├── getForSelect.php
│       │   │   ├── updateStatus.php
│       │   │   ├── inventory/    # 库存 API
│       │   │   ├── io/           # 出入库 API
│       │   │   └── purchase/     # 采购 API
│       │   ├── customer/         # 客户 API
│       │   │   ├── get.php / set.php / delete.php
│       │   │   ├── getForSelect.php
│       │   │   ├── updateStatus.php
│       │   │   └── price/        # 客户协议价 API
│       │   ├── supplier/         # 供应商 API
│       │   │   ├── get.php / set.php / delete.php
│       │   │   ├── getForSelect.php
│       │   │   ├── update.php
│       │   │   ├── updateStatus.php
│       │   │   └── quote/        # 供应商报价 API
│       │   ├── order/            # 订单 API
│       │   │   ├── get.php / set.php / update.php
│       │   │   ├── getDetail.php
│       │   │   ├── delete.php
│       │   │   ├── updateStatus.php
│       │   │   └── payment/      # 订单收款 API
│       │   └── finance/          # 财务 API
│       │       ├── flow/         # 资金流水 API
│       │       ├── receivable/   # 应收账款 API
│       │       ├── payable/      # 应付账款 API
│       │       ├── reconciliation/ # 对账 API
│       │       └── report/       # 财务报表 API
│       ├── sql/                  # 数据库 SQL 文件
│       │   ├── warehouse/
│       │   ├── customer/
│       │   ├── supplier/
│       │   ├── order/
│       │   └── finance/
│       └── vendor/               # Composer 依赖
│
├── public/                       # 静态资源
├── index.html                    # 前端入口 HTML
├── vite.config.ts                # Vite 配置
├── package.json                  # 前端依赖
└── README.md
```

---

## 快速开始

### 环境要求
- Node.js >= 22.18.0 或 >= 24.12.0
- PHP >= 7.4
- MySQL >= 5.7

### 1. 初始化数据库

浏览器访问后端地址的初始化页面：

```
http://your-domain/api.php
```

填写数据库连接信息（地址、库名、用户名、密码），点击 **开始初始化**，系统将自动：
- 创建数据库（如果不存在）
- 执行所有建表 SQL
- 创建触发器

> 数据库连接配置同时需要在 `src-net/v1/api/config/pdo.php` 中修改。

### 2. 启动前端

```bash
# 安装依赖
npm install

# 启动开发服务器
npm run dev
```

### 3. 配置 API 地址

修改 [src/config/net.ts](src/config/net.ts) 中的 `API_URL` 为你的后端地址。

---

## 模块说明

### 🏠 首页看板 (HomeView.vue)

- **数据卡片**：订单总数、未收账款、待确认采购、低库存预警、客户数、供应商数
- **收支趋势图**：近 7 天收入/支出折线图（Chart.js）
- **资金分布**：已收款/未收款/已支出 环形饼图
- **欠款客户 TOP5**：表格展示，支持跳转对账
- **常用功能**：8 个快捷入口卡片

### 📦 仓库管理

| 页面 | 功能 |
|------|------|
| WarehouseList | 仓库列表，启用/禁用，删除 |
| WarehouseCreate | 新建/编辑仓库 |
| WarehouseManage | 仓库内库存详情、库存调整 |
| WarehouseInventory | 全局库存总览、低库存筛选 |
| WarehouseIO | 手动出入库，可选是否计入财务系统 |
| WarehousePurchase | 采购管理（创建/确认/入库），自动同步库存和财务 |

**采购流程**：创建采购单 → 确认采购（写入财务流水） → 完成入库（更新库存）

### 👥 客户管理

| 页面 | 功能 |
|------|------|
| CustomerList | 客户列表、启用/禁用、删除 |
| CustomerCreate | 新建/编辑客户 |
| CustomerManage | 客户商品管理、协议价设置 |

### 🚚 供应商管理

| 页面 | 功能 |
|------|------|
| SupplierList | 供应商列表、启用/禁用、删除 |
| SupplierCreate | 新建/编辑供应商 |
| SupplierManage | 供应商商品报价管理 |

### 📋 订单管理

| 页面 | 功能 |
|------|------|
| OrderList | 订单列表、状态筛选、搜索 |
| OrderCreate | 新建订单、商品选择、实时计算、打印预览 |
| OrderEdit | 编辑订单 |
| OrderDetail | 订单详情、收款记录、状态更新 |

**订单字段可配置**：通过 [orderFieldSettings.ts](src/config/orderFieldSettings.ts) 可隐藏不需要的字段。

### 💰 财务管理

| 页面 | 功能 |
|------|------|
| FinanceOverview | 财务概览、收支趋势、TOP欠款 |
| FinanceFlow | 资金流水明细、增删改查 |
| FinanceReceivable | 应收账款汇总 |
| FinancePayable | 应付账款汇总 |
| FinanceProfit | 利润分析、趋势图 |
| FinanceReconciliation | 对账管理（日/周/月/自定义） |
| FinanceCustomerReconciliation | 客户对账（按客户+时间段） |

**财务流水自动触发点**：
- ✅ 订单收款 → 自动写入 `finance_flow`（收入）
- ✅ 采购确认 → 自动写入 `finance_flow`（支出）
- ✅ 手动出入库 → 可选是否计入财务系统

---

## 数据库

### 数据表清单（16 张）

| 表名 | 说明 |
|------|------|
| `warehouse` | 仓库主表 |
| `warehouse_inventory` | 库存表（按仓库+产品汇总） |
| `warehouse_io` | 出入库流水 |
| `purchase_order` | 采购单主表 |
| `purchase_item` | 采购单明细 |
| `customer` | 客户主表 |
| `customer_price` | 客户协议价 |
| `supplier_main` | 供应商主表 |
| `supplier_quote` | 供应商报价/商品 |
| `order_main` | 订单主表 |
| `order_item` | 订单明细 |
| `order_item_deduction` | 订单扣款明细 |
| `order_payment` | 订单收款记录 |
| `finance_flow` | 资金流水 |
| `finance_receivable` | 应收账款 |
| `finance_payable` | 应付账款 |
| `finance_reconciliation` | 对账记录 |

> 所有带 `number` 字段的表均有 `BEFORE INSERT` 触发器，自动生成基于时间戳的唯一编号。

### SQL 文件位置

全部 SQL 建表脚本位于 [src-net/v1/sql/](src-net/v1/sql/)，按模块分目录存放。

---

## API 规范

### 基础地址

```
http://your-domain/v1/api/
```

### 路由机制

使用 Mezon Router，在 [src-net/v1/api/index.php](src-net/v1/api/index.php) 中统一注册。

路由回调签名：
```php
function ($route, $params = []) {
    // $params 为路由参数数组
}
```

### 统一响应格式

```json
{
  "success": true,
  "message": "操作成功",
  "data": {},
  "total": 100
}
```

### 列表接口参数约定

| 参数 | 类型 | 说明 |
|------|------|------|
| `page` | int | 页码，从 1 开始 |
| `size` | int | 每页数量 |
| `keyword` | string | 搜索关键词 |

### 主要接口列表

| 模块 | 接口 | 方法 | 说明 |
|------|------|------|------|
| 仓库 | `warehouse/getList` | GET | 仓库列表 |
| | `warehouse/getForSelect` | GET | 仓库下拉选项 |
| | `warehouse/set` | POST | 新增/编辑仓库 |
| | `warehouse/delete` | POST | 删除仓库 |
| 库存 | `warehouse/inventory/getList` | GET | 库存列表 |
| | `warehouse/inventory/set` | POST | 新增/调整库存 |
| | `warehouse/inventory/searchProducts` | GET | 搜索商品（含客户价） |
| 出入库 | `warehouse/io/getList` | GET | 出入库记录 |
| | `warehouse/io/set` | POST | 新增出入库 |
| 采购 | `warehouse/purchase/getList` | GET | 采购单列表 |
| | `warehouse/purchase/set` | POST | 创建/确认/入库 |
| 客户 | `customer/getList` | GET | 客户列表 |
| | `customer/getForSelect` | GET | 客户下拉选项 |
| | `customer/set` | POST | 新增/编辑客户 |
| | `customer/price/get` | GET | 客户协议价列表 |
| | `customer/price/set` | POST | 设置协议价 |
| 供应商 | `supplier/getList` | GET | 供应商列表 |
| | `supplier/getForSelect` | GET | 供应商下拉选项 |
| | `supplier/set` | POST | 新增/编辑供应商 |
| | `supplier/quote/get` | GET | 供应商商品列表 |
| | `supplier/quote/set` | POST | 新增/编辑商品报价 |
| 订单 | `order/getList` | GET | 订单列表 |
| | `order/getDetail` | GET | 订单详情 |
| | `order/set` | POST | 创建订单 |
| | `order/update` | POST | 更新订单 |
| | `order/updateStatus` | POST | 更新订单状态 |
| | `order/payment/set` | POST | 订单收款 |
| 财务 | `finance/flow/getOverview` | GET | 财务概览 |
| | `finance/flow/get` | GET | 流水列表 |
| | `finance/flow/set` | POST | 新增流水 |
| | `finance/report/getProfit` | GET | 利润分析 |
| | `finance/receivable/getReconciliation` | GET | 客户对账 |
| | `finance/reconciliation/set` | POST | 创建对账 |

---

## 配置说明

### 后端配置

| 文件 | 说明 |
|------|------|
| [src-net/v1/api/config/pdo.php](src-net/v1/api/config/pdo.php) | 数据库连接配置 |
| [src-net/v1/api/config/image.config.php](src-net/v1/api/config/image.config.php) | 图片存储路径配置 |
| [src-net/v1/api/config/order.config.php](src-net/v1/api/config/order.config.php) | 订单相关配置 |

### 前端配置

| 文件 | 说明 |
|------|------|
| [src/config/net.ts](src/config/net.ts) | API 地址 |
| [src/config/seller.ts](src/config/seller.ts) | 商家信息（用于打印） |
| [src/config/confirmSettings.ts](src/config/confirmSettings.ts) | 确认弹窗全局开关 |
| [src/config/orderFieldSettings.ts](src/config/orderFieldSettings.ts) | 订单字段显隐配置 |

---

## 打包部署

### 前端打包

```bash
# 类型检查 + 构建
npm run build
```

产物输出到 `dist/` 目录，将其中所有文件部署到静态服务器即可。

> 默认已关闭 sourcemap（`vite.config.ts` 中 `sourcemap: false`），生产环境不暴露源码。

### 后端部署

将 `src-net/` 目录整体部署到 PHP 运行环境，确保：
- PHP >= 7.4
- PDO MySQL 扩展已开启
- 重写规则已配置（`.htaccess` 已自带）
- `v1/api/top/` 目录有写入权限（图片上传）

### 典型部署结构

```
www/
├── index.html        # 前端打包产物
├── assets/           # 前端静态资源
└── api/              # 后端
    ├── .htaccess
    ├── index.php
    ├── api.php       # 数据库初始化页面
    └── v1/
```

---

## 开发规范

### 前端
- 使用 `<script lang="ts" setup>` 语法
- 深色/浅色主题使用 CSS 变量，定义在 `global.scss`
- 列表页统一使用 `BaseTable` + `PagInation` 组件
- 所有金额使用 `number` 类型，显示时 `toFixed(2)`

### 后端
- 读操作使用 `DbGet` 基类，写操作使用 `DbSet` 基类
- 路由回调统一 `($route, $params = [])` 签名
- 所有接口返回 JSON，异常时设置 `success: false`
- 数据库表关联使用 `number` 字段（业务编号）而非 `id`（自增主键）

---

## License

Private
