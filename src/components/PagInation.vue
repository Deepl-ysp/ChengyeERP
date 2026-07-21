<script lang="ts" setup>
import { computed } from 'vue';

const props = defineProps<{
  total: number;
  currentPage: number;
  pageSize: number;
}>();

const emit = defineEmits<{
  (e: 'update:currentPage', value: number): void;
  (e: 'update:pageSize', value: number): void;
}>();

const totalPages = computed(() => Math.max(1, Math.ceil(props.total / props.pageSize)));

const pages = computed(() => {
  const total = totalPages.value;
  const current = props.currentPage;
  const result: (number | '...')[] = [];

  if (total <= 7) {
    for (let i = 1; i <= total; i++) result.push(i);
    return result;
  }

  if (current <= 4) {
    result.push(1, 2, 3, 4, 5, '...', total);
  } else if (current >= total - 3) {
    result.push(1, '...', total - 4, total - 3, total - 2, total - 1, total);
  } else {
    result.push(1, '...', current - 1, current, current + 1, '...', total);
  }

  return result;
});

const handlePrev = () => {
  if (props.currentPage > 1) {
    emit('update:currentPage', props.currentPage - 1);
  }
};

const handleNext = () => {
  if (props.currentPage < totalPages.value) {
    emit('update:currentPage', props.currentPage + 1);
  }
};

const handlePage = (page: number) => {
  if (page !== props.currentPage) {
    emit('update:currentPage', page);
  }
};

const handleSizeChange = (event: Event) => {
  const target = event.target as HTMLSelectElement;
  const size = parseInt(target.value, 10);
  emit('update:pageSize', size);
  emit('update:currentPage', 1);
};

const pageSizes = [10, 20, 50, 100];

const startNum = computed(() => (props.currentPage - 1) * props.pageSize + 1);
const endNum = computed(() => Math.min(props.currentPage * props.pageSize, props.total));
</script>

<template>
  <div class="-wrapper">
    <div class="-info">
      <span>共 {{ total }} 条记录</span>
      <span>{{ startNum }} - {{ endNum }} 条</span>
    </div>
    <div class="-container">
      <button class="-btn -prev" :disabled="currentPage <= 1" @click="handlePrev">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <button v-for="page in pages" :key="page" class="-btn -page" :class="{ active: page === currentPage }"
        :disabled="page === '...'" @click="typeof page === 'number' && handlePage(page)">
        {{ page }}
      </button>
      <button class="-btn -next" :disabled="currentPage >= totalPages" @click="handleNext">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 5l7 7-7 7" />
        </svg>
      </button>
      <select class="-select" @change="handleSizeChange">
        <option v-for="size in pageSizes" :key="size" :value="size">
          {{ size }} 条/页
        </option>
      </select>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.-wrapper {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  background: var(--bg-card);
  border-top: 1px solid var(--border-color);

  .-info {
    display: flex;
    gap: 1rem;
    font-size: 0.85rem;
    color: var(--text-muted);

    span {
      display: flex;
      align-items: center;
    }
  }

  .-container {
    display: flex;
    align-items: center;
    gap: 0.3rem;
  }

  .-btn {
    min-width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: var(--bg-body);
    color: var(--text-primary);
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;

    &:hover:not(:disabled) {
      border-color: var(--link-color);
      color: var(--link-color);
    }

    &:disabled {
      opacity: 0.4;
      cursor: not-allowed;
    }

    &.-page {
      width: 32px;

      &.active {
        background: var(--link-color);
        border-color: var(--link-color);
        color: white;
      }
    }

    &.-prev,
    &.-next {
      padding: 0 0.5rem;
    }
  }

  .-select {
    margin-left: 0.5rem;
    padding: 0.4rem 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: var(--bg-body);
    color: var(--text-primary);
    font-size: 0.85rem;
    cursor: pointer;

    &:focus {
      outline: none;
      border-color: var(--link-color);
    }
  }
}
</style>
