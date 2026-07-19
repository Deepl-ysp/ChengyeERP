<script lang="ts" setup>
import { ref, onMounted, provide } from 'vue';
import { RouterView, RouterLink } from 'vue-router';
import { API_URL } from '@config/net';

interface SupplierItem {
  id: number;
  number: number;
  name: string;
  contact: string;
}

const suppliers = ref<SupplierItem[]>([]);

const fetchSuppliers = async () => {
  try {
    const res = await fetch(`${API_URL}v1/api/supplier/getForSelect?keyword=&limit=100`);
    const data = await res.json();
    suppliers.value = (data.list || []).map((s: SupplierItem) => ({
      id: Number(s.id),
      number: Number(s.number),
      name: s.name,
      contact: s.contact,
    }));
  } catch (err) {
    console.error('获取供应商列表失败', err);
  }
};

// 提供给子页面调用的刷新函数，用于创建/修改/删除/状态切换后更新左侧菜单供应商列表
provide('refreshSuppliers', fetchSuppliers);

onMounted(() => {
  fetchSuppliers();
});
</script>
<template>
  <div class="MainBox">
    <div class="viewContent">
      <div class="viewMenu">
        <RouterLink to="/supplier/list" class="viewNavLink" active-class="viewActive">供应商列表</RouterLink>
        <RouterLink to="/supplier/create" class="viewNavLink" active-class="viewActive">创建供应商</RouterLink>
        <div v-if="suppliers.length > 0" class="menu-divider"></div>
        <RouterLink
          v-for="s in suppliers"
          :key="s.number"
          :to="`/supplier/manage/${s.number}`"
          class="viewNavLink"
          active-class="viewActive"
        >
          {{ s.name }}
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
