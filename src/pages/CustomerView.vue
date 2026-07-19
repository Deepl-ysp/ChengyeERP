<script lang="ts" setup>
import { ref, onMounted, provide } from 'vue';
import { RouterView, RouterLink } from 'vue-router';
import { API_URL } from '@config/net';

interface CustomerItem {
  id: number;
  number: number;
  name: string;
  contact: string;
}

const customers = ref<CustomerItem[]>([]);

const fetchCustomers = async () => {
  try {
    const res = await fetch(`${API_URL}v1/api/customer/getForSelect?keyword=&limit=100`);
    const data = await res.json();
    customers.value = (data.list || []).map((c: CustomerItem) => ({
      id: Number(c.id),
      number: Number(c.number),
      name: c.name,
      contact: c.contact,
    }));
  } catch (err) {
    console.error('获取客户列表失败', err);
  }
};

// 提供给子页面调用的刷新函数，用于创建/修改/删除/状态切换后更新左侧菜单客户列表
provide('refreshCustomers', fetchCustomers);

onMounted(() => {
  fetchCustomers();
});
</script>
<template>
  <div class="MainBox">
    <div class="viewContent">
      <div class="viewMenu">
        <RouterLink to="/customer/list" class="viewNavLink" active-class="viewActive">客户列表</RouterLink>
        <RouterLink to="/customer/create" class="viewNavLink" active-class="viewActive">创建客户</RouterLink>
        <div v-if="customers.length > 0" class="menu-divider"></div>
        <RouterLink
          v-for="c in customers"
          :key="c.number"
          :to="`/customer/manage/${c.number}`"
          class="viewNavLink"
          active-class="viewActive"
        >
          {{ c.name }}
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
