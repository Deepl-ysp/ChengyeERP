<script setup lang="ts">
import { RouterView, RouterLink } from 'vue-router';
import CollapseMenu from '@components/CollapseMenu.vue';
</script>

<template>
  <div class="MaxBox">
    <header class="Nav">
      <RouterLink to="/" class="NavLnk" active-class="active">首页</RouterLink>
      <RouterLink to="/order" class="NavLnk" active-class="active">订单管理</RouterLink>
      <RouterLink to="/Warehouse" class="NavLnk" active-class="active">仓库管理</RouterLink>
      <RouterLink to="/Customer" class="NavLnk" active-class="active">客户管理</RouterLink>
      <RouterLink to="/Supplier" class="NavLnk" active-class="active">供应商管理</RouterLink>
    </header>
    <main class="Main">
      <RouterView />
    </main>
    <footer class="Footer">
      <span>© 2026 企业管理系统</span>
    </footer>
    <CollapseMenu />
  </div>
</template>

<style lang="scss" scoped>
.MaxBox {
  width: 100vw;
  height: 100vh;
  display: grid;
  grid-template-rows: 64px 1fr 48px; /* 固定高度更商务 */
}

/* 导航栏 – 深蓝商务色 */
.Nav {
  background-color: #1a2a3a;
  display: flex;
  justify-content: space-evenly;
  align-items: stretch;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
  z-index: 10;

  .NavLnk {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 1.2rem;
    color: #cfddee;
    font-weight: 500;
    font-size: 0.95rem;
    letter-spacing: 0.3px;
    text-decoration: none;
    background-color: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;

    &:hover {
      color: #ffffff;
      background-color: rgba(255, 255, 255, 0.06);
      border-bottom-color: #4a90d9;
    }

    &.active {
      color: #ffffff;
      border-bottom-color: #4a90d9;
      background-color: rgba(255, 255, 255, 0.08);
    }
  }
}

/* 主内容区 – 浅灰背景，干净 */
.Main {
  background-color: #f2f5f9;
  padding: 1.5rem;
  overflow-y: auto;
}

/* 页脚 – 深色稳重 */
.Footer {
  background-color: #1a2a3a;
  color: #9aaec2;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  letter-spacing: 0.5px;
  border-top: 1px solid #2a3a4a;
}
</style>
