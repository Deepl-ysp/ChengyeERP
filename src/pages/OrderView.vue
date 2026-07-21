<script lang="ts" setup>
import { RouterView, RouterLink } from 'vue-router';
</script>
<template>
  <div class="MainBox">
    <div class="viewContent">
      <div class="viewMenu">
        <RouterLink to="/order/list" class="viewNavLink" active-class="viewActive">订单列表</RouterLink>
        <RouterLink to="/order/create" class="viewNavLink" active-class="viewActive">创建订单</RouterLink>
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
</style>
