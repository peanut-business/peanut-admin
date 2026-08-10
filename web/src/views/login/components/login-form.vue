<template>
  <div class="login-form-wrapper">
    <div class="login-form-title">{{ $t('login.form.title') }}</div>
    <div class="login-form-sub-title">{{ $t('login.form.title') }}</div>
    <div class="login-form-error-msg">{{ errorMessage }}</div>
    <el-form
      ref="loginForm"
      :model="userInfo"
      class="login-form"
      label-position="top"
      @submit.prevent="handleSubmit"
    >
      <el-form-item
        prop="username"
        :rules="[{ required: true, message: $t('login.form.userName.errMsg') }]"
      >
        <el-input
          v-model="userInfo.username"
          :placeholder="$t('login.form.userName.placeholder')"
        >
          <template #prefix>
            <el-icon><User /></el-icon>
          </template>
        </el-input>
      </el-form-item>
      <el-form-item
        prop="password"
        :rules="[{ required: true, message: $t('login.form.password.errMsg') }]"
      >
        <el-input
          v-model="userInfo.password"
          :placeholder="$t('login.form.password.placeholder')"
          type="password"
          clearable
          show-password
        >
          <template #prefix>
            <el-icon><Lock /></el-icon>
          </template>
        </el-input>
      </el-form-item>
      <div class="login-form-actions">
        <div class="login-form-password-actions">
          <el-checkbox v-model="loginConfig.rememberPassword">
            {{ $t('login.form.rememberPassword') }}
          </el-checkbox>
          <el-link type="primary">{{
            $t('login.form.forgetPassword')
          }}</el-link>
        </div>
        <el-button
          class="login-form-button"
          type="primary"
          native-type="submit"
          :loading="loading"
        >
          {{ $t('login.form.login') }}
        </el-button>
        <el-button text class="login-form-button login-form-register-btn">
          {{ $t('login.form.register') }}
        </el-button>
      </div>
    </el-form>
  </div>
</template>

<script lang="ts" setup>
  import { ref, reactive } from 'vue';
  import { useRouter } from 'vue-router';
  import { ElMessage, type FormInstance } from 'element-plus';
  import { Lock, User } from '@element-plus/icons-vue';
  import { useI18n } from 'vue-i18n';
  import { useStorage } from '@vueuse/core';
  import { useUserStore } from '@/store';
  import useLoading from '@/hooks/loading';
  import type { LoginData } from '@/api/user';

  const router = useRouter();
  const { t } = useI18n();
  const errorMessage = ref('');
  const { loading, setLoading } = useLoading();
  const userStore = useUserStore();
  const loginForm = ref<FormInstance>();

  const loginConfig = useStorage('login-config-v2', {
    rememberPassword: true,
    username: 'admin', // 演示默认值
    password: 'admin123456', // demo default value
  });
  const userInfo = reactive({
    username: loginConfig.value.username,
    password: loginConfig.value.password,
  });

  const handleSubmit = async () => {
    if (loading.value) return;
    const valid = await loginForm.value?.validate().catch(() => false);
    if (!valid) return;
    setLoading(true);
    try {
      await userStore.login({ ...userInfo } as LoginData);
      const { redirect, ...othersQuery } = router.currentRoute.value.query;
      const redirectRoute =
        typeof redirect === 'string' &&
        redirect !== 'login' &&
        router.hasRoute(redirect)
          ? redirect
          : 'Workplace';
      router.push({
        name: redirectRoute,
        query: {
          ...othersQuery,
        },
      });
      ElMessage.success(t('login.form.login.success'));
      const { rememberPassword } = loginConfig.value;
      const { username, password } = userInfo;
      // 实际生产环境需要进行加密存储。
      // The actual production environment requires encrypted storage.
      loginConfig.value.username = rememberPassword ? username : '';
      loginConfig.value.password = rememberPassword ? password : '';
    } catch (err) {
      errorMessage.value = (err as Error).message;
    } finally {
      setLoading(false);
    }
  };
</script>

<style lang="less" scoped>
  .login-form {
    &-wrapper {
      width: 320px;
    }

    &-title {
      color: var(--el-text-color-primary);
      font-weight: 500;
      font-size: 24px;
      line-height: 32px;
    }

    &-sub-title {
      color: var(--el-text-color-secondary);
      font-size: 16px;
      line-height: 24px;
    }

    &-error-msg {
      height: 32px;
      color: var(--el-color-danger);
      line-height: 32px;
    }

    &-password-actions {
      display: flex;
      justify-content: space-between;
    }

    &-actions {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    &-button {
      width: 100%;
      margin-left: 0;
    }

    &-register-btn {
      color: var(--el-text-color-secondary) !important;
    }
  }
</style>
