<template>
  <main class="platform-tenants">
    <header>
      <div><h1>Tenant lifecycle</h1><p>Instance-local platform control plane</p></div>
      <div><el-button type="primary" @click="provisionVisible = true">Provision Tenant</el-button><el-button @click="logout">Logout</el-button></div>
    </header>
    <el-alert v-if="error" :title="error" type="error" show-icon />
    <el-table v-loading="loading" :data="tenants" row-key="id">
      <el-table-column prop="id" label="ID" width="90" />
      <el-table-column prop="code" label="Code" />
      <el-table-column prop="display_name" label="Tenant" />
      <el-table-column prop="status" label="Status" width="130" />
      <el-table-column prop="revision" label="Revision" width="100" />
      <el-table-column label="Lifecycle" width="300">
        <template #default="{ row }">
          <el-button v-if="row.status === 'provisioning' || row.status === 'suspended'" size="small" @click="transition(row, 'activate')">Activate</el-button>
          <el-button v-if="row.status === 'active'" size="small" type="warning" @click="transition(row, 'suspend')">Suspend</el-button>
          <el-button v-if="row.status !== 'closed'" size="small" type="danger" @click="transition(row, 'close')">Close</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="provisionVisible" title="Provision Tenant" width="560px">
      <el-form label-position="top">
        <el-form-item label="Tenant code"><el-input v-model="form.tenant_code" /></el-form-item>
        <el-form-item label="Tenant name"><el-input v-model="form.tenant_name" /></el-form-item>
        <el-form-item label="Owner email"><el-input v-model="form.owner_email" /></el-form-item>
        <el-form-item label="Owner display name"><el-input v-model="form.owner_display_name" /></el-form-item>
        <el-form-item label="Initial password"><el-input v-model="form.initial_password" type="password" show-password /></el-form-item>
      </el-form>
      <template #footer><el-button @click="provisionVisible = false">Cancel</el-button><el-button type="primary" :loading="saving" @click="provision">Provision</el-button></template>
    </el-dialog>
  </main>
</template>

<script setup lang="ts">
  import { onMounted, reactive, ref } from 'vue';
  import { useRouter } from 'vue-router';
  import { ElMessageBox } from 'element-plus';
  import { platformLogout, platformTenants, provisionTenant, transitionTenant, type PlatformTenant } from '@/api/platform';

  const router = useRouter();
  const tenants = ref<PlatformTenant[]>([]);
  const loading = ref(false);
  const saving = ref(false);
  const error = ref('');
  const provisionVisible = ref(false);
  const form = reactive({ tenant_code: '', tenant_name: '', owner_email: '', owner_display_name: '', initial_password: '' });

  const load = async () => {
    loading.value = true; error.value = '';
    try { tenants.value = (await platformTenants()).lists; }
    catch (reason) { error.value = (reason as Error).message; }
    finally { loading.value = false; }
  };
  const provision = async () => {
    saving.value = true;
    try { await provisionTenant({ ...form }); provisionVisible.value = false; await load(); }
    catch (reason) { error.value = (reason as Error).message; }
    finally { saving.value = false; }
  };
  const transition = async (tenant: PlatformTenant, action: 'activate' | 'suspend' | 'close') => {
    const { value } = await ElMessageBox.prompt(`Reason for ${action}`, `${action} ${tenant.display_name}`, { inputPattern: /\S+/, inputErrorMessage: 'A reason is required' });
    await transitionTenant(action, tenant, value); await load();
  };
  const logout = async () => { await platformLogout(); await router.replace({ name: 'PlatformLogin' }); };
  onMounted(load);
</script>

<style scoped lang="less">
  .platform-tenants { max-width: 1200px; min-height: 100vh; margin: 0 auto; padding: 32px; }
  header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
  h1 { margin: 0; } p { color: var(--el-text-color-secondary); }
</style>
