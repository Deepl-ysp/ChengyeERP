<script lang="ts" setup>
// 不再需要导入 PropType，直接定义类型即可
export interface Column {
  key: string;
  title: string;
  width?: string;
  align?: 'left' | 'center' | 'right';
  // render 返回 HTML 字符串，通过 v-html 渲染
  render?: (value: unknown, row: Record<string, unknown>) => string;
}

// 使用 withDefaults 定义 props，不赋值给变量（因为模板中不需要访问 props 对象）
withDefaults(
  defineProps<{
    columns: Column[];
    data: Record<string, unknown>[];
    loading?: boolean;
    rowKey?: string;
  }>(),
  {
    loading: false,
    rowKey: 'id',
  }
);

const emit = defineEmits<{
  (e: 'row-click', row: Record<string, unknown>): void;
}>();

// 对齐类名辅助
const getAlignClass = (align?: string) => {
  if (align === 'left') return 'text-left';
  if (align === 'right') return 'text-right';
  return 'text-center';
};
</script>

<template>
  <div class="base-table-wrapper">
    <div v-if="$slots.toolbar" class="table-toolbar">
      <slot name="toolbar" />
    </div>

    <div class="table-container">
      <div class="table-header">
        <div
          v-for="col in columns"
          :key="col.key"
          class="header-cell"
          :style="{ width: col.width || 'auto' }"
          :class="getAlignClass(col.align)"
        >
          {{ col.title }}
        </div>
      </div>

      <div class="table-body">
        <div
          v-for="(row, index) in data"
          :key="String(row[rowKey]) || index"
          class="table-row"
          @click="emit('row-click', row)"
        >
          <div
            v-for="col in columns"
            :key="col.key"
            class="body-cell"
            :style="{ width: col.width || 'auto' }"
            :class="getAlignClass(col.align)"
          >
            <template v-if="col.render">
              <span v-html="col.render(row[col.key], row)"></span>
            </template>
            <template v-else>
              {{ row[col.key] }}
            </template>
          </div>
        </div>

        <div v-if="!data.length" class="empty-state">
          <slot name="empty">暂无数据</slot>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
/* 样式与之前完全一致，此处省略以保持代码紧凑 */
.base-table-wrapper {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 4px 16px rgba(0, 0, 0, 0.04);
  padding: 1.25rem 1.5rem 0.5rem 1.5rem;
  width: 100%;
  box-sizing: border-box;
}
.table-toolbar {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
  margin-bottom: 1.25rem;
}
.table-container {
  border: 1px solid #e9edf2;
  border-radius: 10px;
  overflow: hidden;
}
.table-header {
  display: flex;
  align-items: center;
  background: #f1f5f9;
  padding: 0.6rem 0.8rem;
  border-bottom: 1px solid #e2e8f0;
  .header-cell {
    flex: 1 1 0;
    min-width: 0;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #475569;
    padding: 0.1rem 0.2rem;
    white-space: nowrap;
    &.text-left { text-align: left; }
    &.text-center { text-align: center; }
    &.text-right { text-align: right; }
  }
}
.table-body {
  display: flex;
  flex-direction: column;
}
.table-row {
  display: flex;
  align-items: center;
  padding: 0.55rem 0.8rem;
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.15s ease;
  cursor: default;
  &:nth-child(even) { background: #fafcff; }
  &:hover { background: #f1f7fe; }
  &:last-child { border-bottom: none; }
  .body-cell {
    flex: 1 1 0;
    min-width: 0;
    font-size: 0.85rem;
    color: #1e293b;
    padding: 0.1rem 0.2rem;
    word-break: break-word;
    line-height: 1.4;
    &.text-left { text-align: left; }
    &.text-center { text-align: center; }
    &.text-right { text-align: right; }
  }
}
.empty-state {
  padding: 2rem;
  text-align: center;
  color: #94a3b8;
  font-size: 0.9rem;
}
</style>
