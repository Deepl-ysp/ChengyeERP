import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'Home',
    component: () => import('@pages/HomeView.vue'),
  },
  {
    path: '/order',
    component: () => import('@pages/OrderView.vue'),
    children: [
      {
        path: 'list',
        name: 'CerataOrderList',
        component: () => import('@pages/order/OrderList.vue'),
      },
      {
        path: 'detail/:id',
        name: 'CerataOrderDetail',
        component: () => import('@pages/order/OrderDetail.vue'),
      },
      {
        path: 'create',
        name: 'CerataOrderCreate',
        component: () => import('@pages/order/OrderCreate.vue'),
      }
    ],
  },
  {
    path: '/Warehouse',
    name: 'Warehouse',
    component: () => import('@pages/WarehouseView.vue'),
  },
  {
    path: '/Product',
    name: 'Product',
    component: () => import('@pages/ProductView.vue'),
  },
  {
    path: '/Customer',
    name: 'Customer',
    component: () => import('@pages/CustomerView.vue'),
  },
  {
    path: '/Supplier',
    name: 'Supplier',
    component: () => import('@pages/SupplierView.vue'),
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

export default router