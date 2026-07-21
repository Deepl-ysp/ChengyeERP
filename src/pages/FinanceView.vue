<script lang="ts" setup>
import { RouterView, RouterLink } from 'vue-router';
</script>
<template>
  <div class="MainBox">
    <div class="viewContent">
      <div class="viewMenu">
        <RouterLink to="/finance/overview" class="viewNavLink" active-class="viewActive">资金概览</RouterLink>
        <RouterLink to="/finance/flow" class="viewNavLink" active-class="viewActive">资金流水</RouterLink>
        <RouterLink to="/finance/receivable" class="viewNavLink" active-class="viewActive">应收账款</RouterLink>
        <RouterLink to="/finance/payable" class="viewNavLink" active-class="viewActive">应付账款</RouterLink>
        <RouterLink to="/finance/reconciliation" class="viewNavLink" active-class="viewActive">分时对账</RouterLink>
        <RouterLink to="/finance/customer-reconciliation" class="viewNavLink" active-class="viewActive">客户对账</RouterLink>
        <RouterLink to="/finance/profit" class="viewNavLink" active-class="viewActive">利润分析</RouterLink>
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
