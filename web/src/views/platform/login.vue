<template>
  <main class="platform-login">
    <el-card class="platform-login-card">
      <h1>Instance Platform</h1>
      <p>PlatformOperator credentials are separate from Tenant administrators.</p>
      <el-alert v-if="error" :title="error" type="error" :closable="false" />
      <el-form label-position="top" @submit.prevent="submit">
        <el-form-item label="Email">
          <el-input v-model="email" autocomplete="username" />
        </el-form-item>
        <el-form-item label="Password">
          <el-input v-model="password" type="password" show-password autocomplete="current-password" />
        </el-form-item>
        <el-button type="primary" native-type="submit" :loading="loading" style="width: 100%">
          Sign in to platform
        </el-button>
      </el-form>
    </el-card>
  </main>
</template>

<script setup lang="ts">
  import { ref } from 'vue';
  import { useRouter } from 'vue-router';
  import { platformLogin } from '@/api/platform';

  const router = useRouter();
  const email = ref('');
  const password = ref('');
  const loading = ref(false);
  const error = ref('');

  const submit = async () => {
    if (!email.value || !password.value || loading.value) return;
    loading.value = true;
    error.value = '';
    try {
      await platformLogin(email.value.trim(), password.value);
      await router.replace({ name: 'PlatformTenants' });
    } catch (reason) {
      error.value = (reason as Error).message;
    } finally {
      loading.value = false;
    }
  };
</script>

<style scoped lang="less">
  .platform-login {
    display: grid;
    min-height: 100vh;
    place-items: center;
    background: var(--el-fill-color-light);
  }
  .platform-login-card { width: min(420px, calc(100vw - 32px)); }
</style>
