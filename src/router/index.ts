import { createRouter, createWebHistory, type RouteRecordRaw } from "vue-router";

const routes: RouteRecordRaw[] = [
  {
    path: "/",
    name: "Home",
    component: () => import("@pages/HomeView.vue"),
  },
  {
    path: "/order",
    component: () => import("@pages/OrderView.vue"),
    children: [
      {
        path: "",
        redirect: "/order/list",
      },
      {
        path: "list",
        name: "OrderList",
        component: () => import("@pages/order/OrderList.vue"),
      },
      {
        path: "detail/:id?",
        name: "OrderDetail",
        component: () => import("@pages/order/OrderDetail.vue"),
      },
      {
        path: "create",
        name: "OrderCreate",
        component: () => import("@pages/order/OrderCreate.vue"),
      },
    ],
  },
  {
    path: "/warehouse",
    component: () => import("@pages/WarehouseView.vue"),
    children: [
      {
        path: "",
        name: "Warehouse",
        redirect: "/warehouse/list",
      },
      {
        path: "list",
        name: "WarehouseList",
        component: () => import("@pages/warehouse/WarehouseList.vue"),
      },
      {
        path: "create",
        name: "WarehouseCreate",
        component: () => import("@pages/warehouse/WarehouseCreate.vue"),
      },
      {
        path: "inventory",
        name: "WarehouseInventory",
        component: () => import("@pages/warehouse/WarehouseInventory.vue"),
      },
      {
        path: "io",
        name: "WarehouseIO",
        component: () => import("@pages/warehouse/WarehouseIO.vue"),
      },
      {
        path: "manage/:number",
        name: "WarehouseManage",
        component: () => import("@pages/warehouse/WarehouseManage.vue"),
      },
    ],
  },
  {
    path: "/customer",
    component: () => import("@pages/CustomerView.vue"),
    children: [
      {
        path: "",
        name: "Customer",
        redirect: "/customer/list",
      },
      {
        path: "list",
        name: "CustomerList",
        component: () => import("@pages/customer/CustomerList.vue"),
      },
      {
        path: "create",
        name: "CustomerCreate",
        component: () => import("@pages/customer/CustomerCreate.vue"),
      },
      {
        path: "manage/:number",
        name: "CustomerManage",
        component: () => import("@pages/customer/CustomerManage.vue"),
      },
    ],
  },
  {
    path: "/supplier",
    component: () => import("@pages/SupplierView.vue"),
    children: [
      {
        path:"",
        name: "Supplier",
        redirect:"/supplier/list"
      },
      {
        path: "list",
        name: "SupplierList",
        component: () => import("@pages/supplier/SupplierList.vue"),
      },
      {
        path: "create",
        name: "SupplierCreate",
        component: () => import("@pages/supplier/SupplierCreate.vue")
      },
      {
        path: "manage/:number",
        name: "SupplierManage",
        component: () => import("@pages/supplier/SupplierManage.vue")
      }
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
