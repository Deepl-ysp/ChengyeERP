<script lang="ts" setup>
import { ref, onMounted, provide } from 'vue';
import { RouterView, RouterLink } from 'vue-router';
import { API_URL } from '@config/net';

interface WarehouseItem {
  id: number;
  number: number;
  name: string;
  contact: string;
}

const warehouses = ref<WarehouseItem[]>([]);

const fetchWarehouses = async () => {
  try {
    const res = await fetch(`${API_URL}v1/api/warehouse/getForSelect?keyword=&limit=100`);
    const data = await res.json();
    warehouses.value = (data.list || []).map((w: WarehouseItem) => ({
      id: Number(w.id),
      number: Number(w.number),
      name: w.name,
      contact: w.contact,
    }));
  } catch (err) {
    console.error('获取仓库列表失败', err);
  }
};

// 提供给子页面调用的刷新函数，用于创建/修改/删除/状态切换后更新左侧菜单
provide('refreshWarehouses', fetchWarehouses);

onMounted(() => {
  fetchWarehouses();
});
</script>
<template>
  <div class="MainBox">
    <div class="viewContent">
      <div class="viewMenu">
        <RouterLink to="/warehouse/list" class="viewNavLink" active-class="viewActive">仓库列表</RouterLink>
        <RouterLink to="/warehouse/create" class="viewNavLink" active-class="viewActive">创建仓库</RouterLink>
        <RouterLink to="/warehouse/inventory" class="viewNavLink" active-class="viewActive">产品管理</RouterLink>
        <RouterLink to="/warehouse/io" class="viewNavLink" active-class="viewActive">进出库</RouterLink>
        <div v-if="warehouses.length > 0" class="menu-divider"></div>
        <RouterLink
          v-for="w in warehouses"
          :key="w.number"
          :to="`/warehouse/manage/${w.number}`"
          class="viewNavLink"
          active-class="viewActive"
        >
          {{ w.name }}
        </RouterLink>
      </div>
      <div class="box">
        <RouterView />
      </div>
    </div>
  </div>
</template>
<style lang="scss" scoped>
.box {
  width: 100%;
  height: 100%;
  overflow: hidden;
}

.menu-divider {
  height: 1px;
  background: var(--border-color);
  margin: 0.5rem 1rem;
}
</style>
