<script lang="ts" setup>
import { reactive, ref, onMounted, inject } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { API_URL } from '@config/net';

// 注入父组件提供的刷新函数，用于创建/修改后更新左侧菜单仓库列表
const refreshWarehouses = inject<() => Promise<void>>('refreshWarehouses');

interface WarehouseForm {
  id?: number;
  name: string;
  address: string;
  contact: string;
  phone_number: string;
  status: number;
  remark: string;
}

const statusOptions = [
  { label: '启用', value: 0 },
  { label: '禁用', value: 1 },
  { label: '异常', value: 2 },
];

const form = reactive<WarehouseForm>({
  name: '',
  address: '',
  contact: '',
  phone_number: '',
  status: 0,
  remark: '',
});

const errors = reactive<Record<keyof WarehouseForm, string>>({
  id: '',
  name: '',
  address: '',
  contact: '',
  phone_number: '',
  status: '',
  remark: '',
});

const submitting = ref(false);
const submitSuccess = ref(false);
const submitError = ref('');
const isEdit = ref(false);

const router = useRouter();
const route = useRoute();

onMounted(() => {
  if (route.query.id) {
    isEdit.value = true;
    form.id = Number(route.query.id);
    form.name = (route.query.name as string) || '';
    form.address = (route.query.address as string) || '';
    form.contact = (route.query.contact as string) || '';
    form.phone_number = (route.query.phone_number as string) || '';
    form.status = Number(route.query.status) || 0;
    form.remark = (route.query.remark as string) || '';
  }
});

const validate = (): boolean => {
  let valid = true;
  Object.keys(errors).forEach(key => errors[key as keyof WarehouseForm] = '');

  if (!form.name.trim()) {
    errors.name = '仓库名称不能为空';
    valid = false;
  } else if (form.name.length > 20) {
    errors.name = '名称不能超过20个字符';
    valid = false;
  }

  if (form.address.trim() && form.address.length > 80) {
    errors.address = '地址不能超过80个字符';
    valid = false;
  }
  if (form.contact.trim() && form.contact.length > 10) {
    errors.contact = '联系人不能超过10个字符';
    valid = false;
  }
  if (form.phone_number.trim() && !/^1\d{10}$/.test(form.phone_number)) {
    errors.phone_number = '请输入11位有效手机号';
    valid = false;
  }
  if (form.remark.trim() && form.remark.length > 150) {
    errors.remark = '备注不能超过150个字符';
    valid = false;
  }
  return valid;
};

const handleSubmit = async () => {
  if (!validate()) return;

  submitting.value = true;
  submitSuccess.value = false;
  submitError.value = '';

  try {
    const payload: Partial<WarehouseForm> = {
      name: form.name,
      status: form.status,
    };
    if (isEdit.value && form.id) {
      payload.id = form.id;
    }
    if (form.address.trim()) payload.address = form.address;
    if (form.contact.trim()) payload.contact = form.contact;
    if (form.phone_number.trim()) payload.phone_number = form.phone_number;
    if (form.remark.trim()) payload.remark = form.remark;

    const response = await fetch(`${API_URL}v1/api/warehouse/set`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    const result = await response.json();

    if (response.ok && result.success) {
      submitSuccess.value = true;
      // 刷新左侧菜单仓库列表
      refreshWarehouses?.();
      setTimeout(() => router.push('/warehouse/list'), 1500);
    } else {
      submitError.value = result.message || (isEdit.value ? '更新失败' : '创建失败');
    }
  } catch (err: unknown) {
    console.error('提交失败', err);
    submitError.value = err instanceof Error ? err.message : '提交失败，请重试';
  } finally {
    submitting.value = false;
  }
};

const resetForm = () => {
  form.name = '';
  form.address = '';
  form.contact = '';
  form.phone_number = '';
  form.status = 0;
  form.remark = '';
  Object.keys(errors).forEach(key => errors[key as keyof WarehouseForm] = '');
  submitSuccess.value = false;
  submitError.value = '';
};
</script>

<template>
  <div class="MainBox">
    <div class="page-header">
      <h2>{{ isEdit ? '编辑仓库' : '新增仓库' }}</h2>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="resetForm">重置</button>
        <button class="btn btn-primary" @click="handleSubmit" :disabled="submitting">
          {{ submitting ? '提交中...' : (isEdit ? '确认修改' : '确认添加') }}
        </button>
      </div>
    </div>

    <div class="form-container">
      <form @submit.prevent="handleSubmit">
        <div class="form-grid">
          <div class="form-group">
            <label for="name">仓库名称 <span class="required">*</span></label>
            <input id="name" v-model="form.name" type="text" placeholder="请输入名称（不超过20字符）" maxlength="20"
              :class="{ 'is-error': errors.name }" />
            <span v-if="errors.name" class="error-msg">{{ errors.name }}</span>
          </div>

          <div class="form-group">
            <label for="address">地址</label>
            <input id="address" v-model="form.address" type="text" placeholder="未填写（不超过80字符）" maxlength="80"
              :class="{ 'is-error': errors.address }" />
            <span v-if="errors.address" class="error-msg">{{ errors.address }}</span>
          </div>

          <div class="form-group">
            <label for="contact">联系人</label>
            <input id="contact" v-model="form.contact" type="text" placeholder="未填写（不超过10字符）" maxlength="10"
              :class="{ 'is-error': errors.contact }" />
            <span v-if="errors.contact" class="error-msg">{{ errors.contact }}</span>
          </div>

          <div class="form-group">
            <label for="phone_number">手机号</label>
            <input id="phone_number" v-model="form.phone_number" type="tel" placeholder="未填写（11位手机号）" maxlength="11"
              :class="{ 'is-error': errors.phone_number }" />
            <span v-if="errors.phone_number" class="error-msg">{{ errors.phone_number }}</span>
          </div>

          <div class="form-group">
            <label for="status">状态</label>
            <select id="status" v-model="form.status">
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>

          <div class="form-group full-width">
            <label for="remark">备注</label>
            <textarea id="remark" v-model="form.remark" placeholder="未填写（不超过150字符）" maxlength="150" rows="4"
              :class="{ 'is-error': errors.remark }"></textarea>
            <span v-if="errors.remark" class="error-msg">{{ errors.remark }}</span>
          </div>
        </div>

        <div v-if="submitSuccess" class="alert alert-success">
          ✅ {{ isEdit ? '仓库更新成功！' : '仓库添加成功！' }}即将跳转列表页...
        </div>
        <div v-if="submitError" class="alert alert-danger">
          ❌ {{ submitError }}
        </div>
      </form>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.MainBox {
  padding: 1.5rem;
  background-color: var(--bg-body);
  height: 100%;
  overflow-y: auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;

  h2 {
    font-size: 1.5rem;
    color: var(--text-primary);
    margin: 0;
  }

  .header-actions {
    display: flex;
    gap: 0.8rem;
  }
}

.btn {
  padding: 0.5rem 1.5rem;
  border-radius: 4px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  border: none;

  &.btn-primary {
    background: var(--btn-primary-bg);
    color: var(--btn-primary-text);

    &:hover:not(:disabled) {
      background: var(--btn-primary-hover);
      box-shadow: 0 2px 8px rgba(74, 144, 217, 0.3);
    }

    &:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  }

  &.btn-secondary {
    background: var(--bg-button);
    color: var(--text-primary);

    &:hover {
      background: var(--bg-button-hover);
    }
  }
}

.form-container {
  background: var(--bg-card);
  border-radius: 8px;
  box-shadow: 0 2px 8px var(--shadow-color);
  padding: 2rem;

  form {
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem 2rem;

      .full-width {
        grid-column: 1 / -1;
      }
    }

    .form-group {
      display: flex;
      flex-direction: column;

      label {
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.3rem;
        font-size: 0.9rem;

        .required {
          color: #e53e3e;
          margin-left: 2px;
        }
      }

      input,
      select,
      textarea {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        font-size: 0.95rem;
        background: var(--bg-body);
        color: var(--text-primary);
        transition: border-color 0.2s;

        &:focus {
          outline: none;
          border-color: var(--link-color);
          box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.15);
        }

        &.is-error {
          border-color: #e53e3e;
        }
      }

      textarea {
        resize: vertical;
        min-height: 80px;
        font-family: inherit;
      }

      .error-msg {
        color: #e53e3e;
        font-size: 0.8rem;
        margin-top: 0.2rem;
      }
    }
  }
}

.alert {
  margin-top: 1.5rem;
  padding: 0.8rem 1rem;
  border-radius: 4px;
  font-weight: 500;

  &.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
  }

  &.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
  }
}
</style>
