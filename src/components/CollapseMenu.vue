<script lang="ts" setup>
import { ref, onMounted } from 'vue';

const isShow = ref<boolean>(true);
const isLeft = ref<boolean>(true);
const isDark = ref<boolean>(false);

// 主题切换
const toggleTheme = () => {
  isDark.value = !isDark.value;
  document.documentElement.setAttribute('data-theme', isDark.value ? 'dark' : 'light');
  localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
};

// 恢复主题偏好
onMounted(() => {
  const saved = localStorage.getItem('theme');
  if (saved === 'dark') {
    isDark.value = true;
    document.documentElement.setAttribute('data-theme', 'dark');
  }
});

// 折叠切换
const Close = () => {
  if (isShow.value) {
    // 隐藏菜单
    document.documentElement.style.setProperty('--menuWidth', '0');
    setTimeout(() => {
      document.documentElement.style.setProperty('--menuDisplay', 'none');
    }, 300);
    isLeft.value = false;
    isShow.value = false;
  } else {
    // 显示菜单
    document.documentElement.style.setProperty('--menuDisplay', 'flex');
    setTimeout(() => {
      document.documentElement.style.setProperty('--menuWidth', '220px');
    }, 10);
    isLeft.value = true;
    isShow.value = true;
  }
};

defineExpose({ isShow, Close });
</script>

<template>
  <div class="MainBox">
    <div class="capsule">
      <!-- 折叠按钮 -->
      <button class="btn collapse-btn" @click="Close">
        <svg width="28" height="28" viewBox="0 0 30 30" :style="{ transform: isLeft ? 'scaleX(-1)' : 'none' }">
          <polyline points="0,15 20,15 15,10 20,15 15,20" fill="none" stroke="var(--collapse-icon)" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>

      <!-- 主题切换按钮 -->
      <button class="btn theme-btn" @click="toggleTheme">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--collapse-icon)" stroke-width="2">
          <!-- 月亮（深色模式） / 太阳（浅色模式） -->
          <path v-if="!isDark" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
          <circle v-else cx="12" cy="12" r="5" />
          <g v-if="isDark">
            <line x1="12" y1="1" x2="12" y2="3" />
            <line x1="12" y1="21" x2="12" y2="23" />
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
            <line x1="1" y1="12" x2="3" y2="12" />
            <line x1="21" y1="12" x2="23" y2="12" />
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
          </g>
        </svg>
      </button>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.capsule {
  position: fixed;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 52px;                /* 胶囊宽度 */
  height: 110px;              /* 胶囊高度 */
  background: var(--collapse-bg);
  border: 1px solid var(--collapse-border);
  border-radius: 30px;        /* 大圆角形成竖向胶囊 */
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  display: flex;
  flex-direction: column;     /* 竖向排列 */
  align-items: center;
  justify-content: space-around;
  padding: 8px 0;
  transition: background 0.3s, border-color 0.3s;
  z-index: 999;

  &:hover {
    border-color: var(--link-color);
  }

  .btn {
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0.2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
    width: 40px;
    height: 40px;

    &:hover {
      background: var(--bg-button);
    }

    svg {
      display: block;
      stroke: var(--collapse-icon);
      transition: stroke 0.2s;
    }

    &:hover svg {
      stroke: var(--link-color);
    }
  }

  // 折叠按钮图标不需要额外样式
  // 主题按钮图标不需要额外样式
}
</style>
