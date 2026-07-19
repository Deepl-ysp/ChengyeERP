<script lang="ts" setup>
import { RouterView, RouterLink } from 'vue-router';

</script>
<template>
  <div class="MainBox">
    <div class="viewContent">
      <div class="viewMenu">
        <RouterLink to="/order/list" class="viewNavLink" active-class="viewActive">订单列表</RouterLink>
        <RouterLink to="/order/create" class="viewNavLink" active-class="viewActive">创建订单</RouterLink>
        <RouterLink to="/order/detail" class="viewNavLink" active-class="viewActive" exact>最近订单</RouterLink>
      </div>
      <div class="box">
        <RouterView />
      </div>
    </div>
  </div>
</template>
