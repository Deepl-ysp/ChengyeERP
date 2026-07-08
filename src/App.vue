<script setup lang="ts">
import { RouterView, RouterLink } from 'vue-router';



</script>

<template>
  <div class="MaxBox">
    <header class="Nav">
      <RouterLink to="/" class="NavLnk" active-class="active">首页</RouterLink>
      <RouterLink to="/order/list" class="NavLnk" active-class="active">订单管理</RouterLink>
      <RouterLink to="/Warehouse" class="NavLnk" active-class="active">仓库管理</RouterLink>
      <RouterLink to="/Product" class="NavLnk" active-class="active">产品管理</RouterLink>
      <RouterLink to="/Customer" class="NavLnk" active-class="active">客户管理</RouterLink>
      <RouterLink to="/Supplier" class="NavLnk" active-class="active">供应商管理</RouterLink>
    </header>
    <main class="Main">
      <RouterView />
    </main>
    <footer class="Footer"> </footer>
  </div>
</template>
<style lang="scss" scoped>
.MaxBox {
  width: 100vw;
  height: 100vh;
  display: grid;
  grid-template-rows: 1fr 12fr 1fr;
}

.Nav {
  background-color: #56565631;
  padding: 0.3rem;
  display: flex;
  justify-content: space-evenly;
  align-items: center;
  gap: 1rem;
}

.Main {
  background-color: #c4c5c9;
  padding: 0.3rem;
}

.Footer {
  background-color: #9e9895;
}

.active {
  color: #0d47bb;
  transform: scale(1.2);
  text-decoration: underline;
}
</style>
