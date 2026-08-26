<template>
  <div class="container">
    <Breadcrumb :items="['开发工具', '模块治理']" />

    <el-card v-if="!authenticated" class="general-card auth-card">
      <template #header>Platform 身份验证</template>
      <el-alert
        type="info"
        :closable="false"
        title="实例模块治理使用独立 Platform 会话，不复用当前租户管理员身份。"
      />
      <el-form label-position="top" @submit.prevent="login">
        <el-form-item label="Platform 邮箱">
          <el-input v-model="credentials.email" autocomplete="username" />
        </el-form-item>
        <el-form-item label="密码">
          <el-input
            v-model="credentials.password"
            type="password"
            show-password
            autocomplete="current-password"
          />
        </el-form-item>
        <el-button type="primary" :loading="busy" @click="login">
          验证并进入
        </el-button>
      </el-form>
    </el-card>

    <el-card v-else class="general-card">
      <template #header>
        <div class="card-header">
          <span>实例模块治理</span>
          <el-space>
            <el-button :loading="busy" @click="syncAll">同步开发树</el-button>
            <el-button type="primary" @click="uploadVisible = true">
              安装 .tar 包
            </el-button>
          </el-space>
        </div>
      </template>

      <el-alert
        v-if="error"
        type="error"
        :title="error"
        show-icon
        closable
        @close="error = ''"
      />
      <el-table :data="rows" :loading="busy" row-key="module_key" border>
        <el-table-column prop="module_key" label="Module key" min-width="210" />
        <el-table-column prop="name" label="名称" min-width="150" />
        <el-table-column label="版本 / Package" min-width="220">
          <template #default="{ row }">
            <div>{{ row.version }} · {{ row.status }}</div>
            <small>{{ row.package_key }}@{{ row.package_version }}</small>
          </template>
        </el-table-column>
        <el-table-column label="依赖" min-width="210">
          <template #default="{ row }">
            <el-tag
              v-for="dependency in row.dependencies"
              :key="dependency.module_key"
              size="small"
            >
              {{ dependency.module_key }} {{ dependency.version }}
            </el-tag>
            <span v-if="row.dependencies.length === 0">—</span>
          </template>
        </el-table-column>
        <el-table-column
          prop="tenant_enabled_count"
          label="已开通租户"
          width="110"
        />
        <el-table-column label="操作" width="300" fixed="right">
          <template #default="{ row }">
            <el-space wrap>
              <el-button link @click="syncOne(row.module_key)">同步</el-button>
              <el-button link @click="disable(row.module_key)">停用</el-button>
              <el-button
                link
                type="warning"
                @click="uninstall(row.module_key, false)"
              >
                退役
              </el-button>
              <el-button
                link
                type="danger"
                @click="uninstall(row.module_key, true)"
              >
                Purge
              </el-button>
            </el-space>
          </template>
        </el-table-column>
      </el-table>
      <el-pagination
        v-model:current-page="page"
        :page-size="pageSize"
        :total="total"
        layout="total, prev, pager, next"
        class="pagination"
        @current-change="load"
      />
    </el-card>

    <el-dialog v-model="uploadVisible" title="安装自包含模块包" width="560px">
      <el-form label-position="top">
        <el-form-item label=".tar 文件">
          <input
            type="file"
            accept=".tar,application/x-tar"
            @change="selectPackage"
          />
        </el-form-item>
        <el-form-item label="期望 SHA-256">
          <el-input v-model="upload.sha256" maxlength="64" />
        </el-form-item>
        <el-form-item label="签名 key id（可选）">
          <el-input v-model="upload.signatureKeyId" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="uploadVisible = false">取消</el-button>
        <el-button type="primary" :loading="busy" @click="install"
          >校验并安装</el-button
        >
      </template>
    </el-dialog>
  </div>
</template>

<script lang="ts" setup>
  import { onMounted, reactive, ref } from 'vue';
  import { ElMessage, ElMessageBox } from 'element-plus';
  import {
    disableModule,
    executeUninstall,
    hasPlatformSession,
    installPackage,
    listModules,
    loginPlatform,
    previewUninstall,
    syncModules,
    type ModuleRuntimeRow,
  } from '@/api/dev-tools/modules';

  const authenticated = ref(hasPlatformSession());
  const busy = ref(false);
  const error = ref('');
  const rows = ref<ModuleRuntimeRow[]>([]);
  const page = ref(1);
  const pageSize = 20;
  const total = ref(0);
  const uploadVisible = ref(false);
  const credentials = reactive({ email: '', password: '' });
  const upload = reactive<{
    file: File | null;
    sha256: string;
    signatureKeyId: string;
  }>({
    file: null,
    sha256: '',
    signatureKeyId: '',
  });

  async function perform<T>(operation: () => Promise<T>, success?: string) {
    busy.value = true;
    error.value = '';
    try {
      const result = await operation();
      if (success) ElMessage.success(success);
      return result;
    } catch (cause) {
      error.value = cause instanceof Error ? cause.message : '模块治理请求失败';
      if (!hasPlatformSession()) authenticated.value = false;
      throw cause;
    } finally {
      busy.value = false;
    }
  }

  async function load(nextPage = page.value) {
    page.value = nextPage;
    try {
      const result = await perform(() =>
        listModules({ page: page.value, page_size: pageSize })
      );
      if (!result) return;
      rows.value = result.lists;
      total.value = result.count;
    } catch {
      // The visible error is set by perform.
    }
  }

  async function login() {
    try {
      await perform(() =>
        loginPlatform(credentials.email, credentials.password)
      );
      credentials.password = '';
      authenticated.value = true;
      await load(1);
    } catch {
      // The visible error is set by perform.
    }
  }

  function selectPackage(event: Event) {
    upload.file = (event.target as HTMLInputElement).files?.[0] || null;
  }

  async function install() {
    if (!upload.file || !/^[a-f0-9]{64}$/.test(upload.sha256)) {
      error.value = '请选择 .tar 文件并填写 64 位小写 SHA-256';
      return;
    }
    const form = new FormData();
    form.append('package', upload.file);
    form.append('expected_sha256', upload.sha256);
    if (upload.signatureKeyId)
      form.append('signature_key_id', upload.signatureKeyId);
    try {
      await perform(() => installPackage(form), '模块包安装完成');
      uploadVisible.value = false;
      await load(1);
    } catch {
      // The visible error is set by perform.
    }
  }

  async function syncAll() {
    try {
      await perform(() => syncModules(), '模块目录同步完成');
      await load();
    } catch {
      // The visible error is set by perform.
    }
  }

  async function syncOne(moduleKey: string) {
    try {
      await perform(() => syncModules(moduleKey), '模块目录同步完成');
      await load();
    } catch {
      // The visible error is set by perform.
    }
  }

  async function disable(moduleKey: string) {
    try {
      const { value } = await ElMessageBox.prompt(
        '请输入停用原因（至少 3 个字符）',
        '停用模块'
      );
      await perform(() => disableModule(moduleKey, value), '模块已停用');
      await load();
    } catch (cause) {
      if (cause instanceof Error) error.value = cause.message;
    }
  }

  async function uninstall(moduleKey: string, purge: boolean) {
    try {
      const preview = await perform(() => previewUninstall(moduleKey, purge));
      if (!preview) return;
      if (preview.blockers.length > 0) {
        error.value = preview.blockers.map((item) => item.code).join(', ');
        return;
      }
      const summary = preview.removed
        .map((entry) => `${entry.table}: ${entry.action} ${entry.count}`)
        .join('\n');
      const { value } = await ElMessageBox.prompt(
        `${
          purge
            ? 'Purge 将物理删除数据与显式 RBAC 绑定。'
            : '默认退役保留数据与绑定。'
        }\n${summary}\n请输入变更原因：`,
        purge ? '确认 Purge' : '确认退役',
        { type: purge ? 'error' : 'warning' }
      );
      await perform(
        () => executeUninstall(moduleKey, purge, preview, value),
        purge ? '模块已清除' : '模块已退役'
      );
      await load(1);
    } catch (cause) {
      if (cause instanceof Error) error.value = cause.message;
    }
  }

  onMounted(() => {
    if (authenticated.value) load(1);
  });
</script>

<style scoped>
  .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .auth-card {
    max-width: 520px;
  }
  .auth-card .el-alert {
    margin-bottom: 20px;
  }
  .pagination {
    justify-content: flex-end;
    margin-top: 16px;
  }
  small {
    color: var(--el-text-color-secondary);
  }
</style>
