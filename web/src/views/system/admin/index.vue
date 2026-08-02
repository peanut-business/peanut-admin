<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.admin']" />

    <a-card class="general-card search-card">
      <a-form :model="queryParams" layout="inline">
        <a-form-item field="account" :label="$t('systemAdmin.search.account')">
          <a-input
            v-model="queryParams.account"
            allow-clear
            :placeholder="$t('systemAdmin.search.account.placeholder')"
            @press-enter="search"
          />
        </a-form-item>
        <a-form-item field="name" :label="$t('systemAdmin.search.name')">
          <a-input
            v-model="queryParams.name"
            allow-clear
            :placeholder="$t('systemAdmin.search.name.placeholder')"
            @press-enter="search"
          />
        </a-form-item>
        <a-form-item field="role_id" :label="$t('systemAdmin.search.role')">
          <a-select
            v-model="queryParams.role_id"
            allow-clear
            :placeholder="$t('systemAdmin.search.role.all')"
            :options="roleOptions"
            style="width: 180px"
          />
        </a-form-item>
        <a-form-item>
          <a-space>
            <a-button type="primary" @click="search">
              <template #icon><icon-search /></template>
              {{ $t('systemAdmin.operation.search') }}
            </a-button>
            <a-button @click="resetQuery">
              <template #icon><icon-refresh /></template>
              {{ $t('systemAdmin.operation.reset') }}
            </a-button>
            <a-button @click="openExport">
              <template #icon><icon-export /></template>
              {{ $t('systemAdmin.operation.export') }}
            </a-button>
          </a-space>
        </a-form-item>
      </a-form>
    </a-card>

    <a-card class="general-card list-card" :title="$t('menu.system.admin')">
      <a-row style="margin-bottom: 16px">
        <a-col :span="12">
          <a-button
            v-permission="['admin/add']"
            type="primary"
            @click="handleAdd"
          >
            <template #icon><icon-plus /></template>
            {{ $t('systemAdmin.operation.create') }}
          </a-button>
        </a-col>
      </a-row>

      <a-table
        row-key="id"
        :loading="loading"
        :columns="columns"
        :data="renderData"
        :pagination="pagination"
        :bordered="{ cell: true }"
        :scroll="{ x: 1500 }"
        @page-change="onPageChange"
        @page-size-change="onPageSizeChange"
      >
        <template #avatar="{ record }">
          <a-avatar :size="42" :image-url="record.avatar">
            {{ record.name?.slice(0, 1) }}
          </a-avatar>
        </template>
        <template #status="{ record }">
          <a-switch
            v-permission="['admin/edit']"
            :model-value="record.disable === 0"
            :disabled="isProtected(record)"
            @change="(value) => handleStatus(record, value as boolean)"
          />
          <a-tag
            v-if="record.root === 1"
            color="gold"
            size="small"
            style="margin-left: 6px"
          >
            {{ $t('systemAdmin.root.yes') }}
          </a-tag>
        </template>
        <template #multipoint="{ record }">
          <a-tag :color="record.multipoint_login === 1 ? 'green' : 'gray'">
            {{
              record.multipoint_login === 1
                ? $t('systemAdmin.common.yes')
                : $t('systemAdmin.common.no')
            }}
          </a-tag>
        </template>
        <template #login_time="{ record }">
          {{ record.login_time || '-' }}
        </template>
        <template #login_ip="{ record }">
          {{ record.login_ip || '-' }}
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button
              v-permission="['admin/edit']"
              type="text"
              size="small"
              @click="handleEdit(record)"
            >
              {{ $t('systemAdmin.operation.edit') }}
            </a-button>
            <a-popconfirm
              v-if="!isProtected(record)"
              :content="$t('systemAdmin.delete.confirm')"
              @ok="handleDelete(record)"
            >
              <a-button
                v-permission="['admin/delete']"
                type="text"
                status="danger"
                size="small"
              >
                {{ $t('systemAdmin.operation.delete') }}
              </a-button>
            </a-popconfirm>
          </a-space>
        </template>
      </a-table>
    </a-card>

    <a-modal
      v-model:visible="modalVisible"
      :title="
        isEdit
          ? $t('systemAdmin.modal.editTitle')
          : $t('systemAdmin.modal.addTitle')
      "
      :ok-loading="submitLoading"
      :mask-closable="false"
      width="620px"
      @before-ok="handleSubmit"
      @cancel="modalVisible = false"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <a-form-item field="account" :label="$t('systemAdmin.field.account')">
          <a-input
            v-model="form.account"
            :disabled="form.root === 1"
            :max-length="32"
            :placeholder="$t('systemAdmin.field.account.placeholder')"
          />
        </a-form-item>

        <a-form-item field="avatar" :label="$t('systemAdmin.field.avatar')">
          <a-space align="start">
            <a-avatar :size="72" :image-url="avatarUrl">
              {{ form.name?.slice(0, 1) }}
            </a-avatar>
            <div>
              <a-upload
                :action="uploadAction"
                :headers="uploadHeaders"
                :show-file-list="false"
                accept="image/jpeg,image/png"
                @success="onAvatarSuccess"
                @error="onAvatarError"
              >
                <template #upload-button>
                  <a-button>
                    <template #icon><icon-upload /></template>
                    {{ $t('systemAdmin.field.avatar.upload') }}
                  </a-button>
                </template>
              </a-upload>
              <div class="form-tip">{{
                $t('systemAdmin.field.avatar.tip')
              }}</div>
            </div>
          </a-space>
        </a-form-item>

        <a-form-item field="name" :label="$t('systemAdmin.field.name')">
          <a-input
            v-model="form.name"
            :max-length="16"
            :placeholder="$t('systemAdmin.field.name.placeholder')"
          />
        </a-form-item>

        <a-form-item field="dept_id" :label="$t('systemAdmin.field.dept')">
          <a-tree-select
            v-model="form.dept_id"
            :data="deptOptions"
            :field-names="{ key: 'id', title: 'name', children: 'children' }"
            multiple
            allow-clear
            :placeholder="$t('systemAdmin.field.dept.placeholder')"
          />
        </a-form-item>

        <a-form-item field="jobs_id" :label="$t('systemAdmin.field.jobs')">
          <a-select
            v-model="form.jobs_id"
            :options="jobsOptions"
            multiple
            allow-clear
            :placeholder="$t('systemAdmin.field.jobs.placeholder')"
          />
        </a-form-item>

        <a-form-item
          v-if="form.root !== 1"
          field="role_id"
          :label="$t('systemAdmin.field.roles')"
        >
          <a-select
            v-model="form.role_id"
            :options="roleOptions"
            multiple
            allow-clear
            :placeholder="$t('systemAdmin.field.roles.placeholder')"
          />
        </a-form-item>

        <a-form-item field="password" :label="$t('systemAdmin.field.password')">
          <a-input-password
            v-model="form.password"
            allow-clear
            :placeholder="
              isEdit
                ? $t('systemAdmin.field.password.editPlaceholder')
                : $t('systemAdmin.field.password.addPlaceholder')
            "
          />
        </a-form-item>

        <a-form-item
          field="password_confirm"
          :label="$t('systemAdmin.field.passwordConfirm')"
        >
          <a-input-password
            v-model="form.password_confirm"
            allow-clear
            :placeholder="$t('systemAdmin.field.passwordConfirm.placeholder')"
          />
        </a-form-item>

        <a-form-item
          v-if="form.root !== 1"
          field="disable"
          :label="$t('systemAdmin.field.status')"
        >
          <a-switch
            v-model="form.disable"
            :checked-value="0"
            :unchecked-value="1"
          />
        </a-form-item>

        <a-form-item
          field="multipoint_login"
          :label="$t('systemAdmin.field.multipoint')"
        >
          <div>
            <a-switch
              v-model="form.multipoint_login"
              :checked-value="1"
              :unchecked-value="0"
            />
            <div class="form-tip">
              {{ $t('systemAdmin.field.multipoint.tip') }}
            </div>
          </div>
        </a-form-item>
      </a-form>
    </a-modal>

    <a-modal
      v-model:visible="exportVisible"
      :title="$t('systemAdmin.export.title')"
      :ok-text="$t('systemAdmin.export.confirm')"
      :ok-loading="exportLoading"
      :mask-closable="false"
      width="540px"
      @before-ok="handleExport"
    >
      <a-spin :loading="exportInfoLoading" style="width: 100%">
        <a-form ref="exportFormRef" :model="exportForm" layout="vertical">
          <a-alert type="info" style="margin-bottom: 16px">
            {{
              $t('systemAdmin.export.summary', {
                count: exportInfo.count,
                pages: exportInfo.sum_page,
                size: exportInfo.page_size,
              })
            }}
            <br />
            {{
              $t('systemAdmin.export.limit', {
                pages: exportInfo.max_page,
                count: exportInfo.all_max_size,
              })
            }}
          </a-alert>
          <a-form-item
            field="page_type"
            :label="$t('systemAdmin.export.range')"
          >
            <a-radio-group v-model="exportForm.page_type">
              <a-radio :value="0">{{ $t('systemAdmin.export.all') }}</a-radio>
              <a-radio :value="1">{{ $t('systemAdmin.export.pages') }}</a-radio>
            </a-radio-group>
          </a-form-item>
          <a-form-item
            v-if="exportForm.page_type === 1"
            :label="$t('systemAdmin.export.pageRange')"
          >
            <a-space>
              <a-input-number
                v-model="exportForm.page_start"
                :min="1"
                :max="exportInfo.sum_page || 1"
              />
              <span>{{ $t('systemAdmin.export.to') }}</span>
              <a-input-number
                v-model="exportForm.page_end"
                :min="exportForm.page_start"
                :max="exportInfo.sum_page || 1"
              />
            </a-space>
          </a-form-item>
          <a-form-item
            field="file_name"
            :label="$t('systemAdmin.export.fileName')"
          >
            <a-input v-model="exportForm.file_name" :max-length="100" />
          </a-form-item>
        </a-form>
      </a-spin>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, nextTick, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { FileItem } from '@arco-design/web-vue/es/upload/interfaces';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import type { SelectOptionData } from '@arco-design/web-vue/es/select/interface';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import useLoading from '@/hooks/loading';
  import { useUserStore } from '@/store';
  import { getToken } from '@/utils/auth';
  import { getRoleAll } from '@/api/system/role';
  import { getDeptAll, type DeptRecord } from '@/api/system/dept';
  import { getJobsAll } from '@/api/system/jobs';
  import {
    addAdmin,
    deleteAdmin,
    editAdmin,
    exportAdmins,
    getAdminDetail,
    getAdminExportInfo,
    getAdminList,
    updateAdminStatus,
    type AdminExportInfo,
    type AdminForm,
    type AdminListParams,
    type AdminRecord,
  } from '@/api/system/admin';

  const { t } = useI18n();
  const userStore = useUserStore();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<AdminRecord[]>([]);
  const roleOptions = ref<SelectOptionData[]>([]);
  const jobsOptions = ref<SelectOptionData[]>([]);
  const deptOptions = ref<DeptRecord[]>([]);

  const queryParams = reactive({
    account: '',
    name: '',
    role_id: '' as number | '',
  });

  const pagination = reactive({
    current: 1,
    pageSize: 15,
    total: 0,
    showTotal: true,
    showPageSize: true,
  });

  const columns = computed<TableColumnData[]>(() => [
    { title: t('systemAdmin.columns.id'), dataIndex: 'id', width: 70 },
    { title: t('systemAdmin.columns.avatar'), slotName: 'avatar', width: 80 },
    {
      title: t('systemAdmin.columns.account'),
      dataIndex: 'account',
      width: 130,
    },
    { title: t('systemAdmin.columns.name'), dataIndex: 'name', width: 130 },
    {
      title: t('systemAdmin.columns.roles'),
      dataIndex: 'role_name',
      width: 150,
      ellipsis: true,
      tooltip: true,
    },
    {
      title: t('systemAdmin.columns.dept'),
      dataIndex: 'dept_name',
      width: 150,
      ellipsis: true,
      tooltip: true,
    },
    {
      title: t('systemAdmin.columns.createTime'),
      dataIndex: 'create_time',
      width: 180,
    },
    {
      title: t('systemAdmin.columns.loginTime'),
      dataIndex: 'login_time',
      slotName: 'login_time',
      width: 180,
    },
    {
      title: t('systemAdmin.columns.loginIp'),
      dataIndex: 'login_ip',
      slotName: 'login_ip',
      width: 150,
    },
    { title: t('systemAdmin.columns.status'), slotName: 'status', width: 130 },
    {
      title: t('systemAdmin.columns.multipoint'),
      slotName: 'multipoint',
      width: 120,
    },
    {
      title: t('systemAdmin.columns.operations'),
      slotName: 'operations',
      width: 150,
      fixed: 'right',
    },
  ]);

  const listParams = (page = pagination.current): AdminListParams => ({
    account: queryParams.account || undefined,
    name: queryParams.name || undefined,
    role_id: queryParams.role_id === '' ? undefined : queryParams.role_id,
    page_no: page,
    page_size: pagination.pageSize,
  });

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const { data } = await getAdminList(listParams(page));
      renderData.value = data.lists;
      pagination.current = data.pageNo;
      pagination.total = data.count;
    } finally {
      setLoading(false);
    }
  };

  const loadOptions = async () => {
    const [roles, departments, jobs] = await Promise.all([
      getRoleAll(),
      getDeptAll(),
      getJobsAll(),
    ]);
    roleOptions.value = roles.data.map((item) => ({
      value: item.id,
      label: item.name,
    }));
    deptOptions.value = departments.data;
    jobsOptions.value = jobs.data.map((item) => ({
      value: item.id,
      label: item.name,
    }));
  };

  const search = () => fetchData(1);
  const resetQuery = () => {
    queryParams.account = '';
    queryParams.name = '';
    queryParams.role_id = '';
    fetchData(1);
  };
  const onPageChange = (current: number) => fetchData(current);
  const onPageSizeChange = (pageSize: number) => {
    pagination.pageSize = pageSize;
    fetchData(1);
  };

  const isProtected = (record: AdminRecord) =>
    record.root === 1 || record.id === userStore.id;

  // ---- 新增 / 编辑 ----
  const modalVisible = ref(false);
  const isEdit = ref(false);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();
  const avatarPreview = ref('');
  const uploadAction = '/api/admin/upload/image';
  const uploadHeaders = computed(() => {
    const token = getToken();
    const headers: Record<string, string> = {};
    if (token) headers.Authorization = `Bearer ${token}`;
    return headers;
  });

  const defaultForm = (): AdminForm => ({
    id: undefined,
    account: '',
    name: '',
    avatar: '',
    dept_id: [],
    jobs_id: [],
    role_id: [],
    password: '',
    password_confirm: '',
    disable: 0,
    multipoint_login: 1,
    root: 0,
  });
  const form = reactive<AdminForm>(defaultForm());
  const avatarUrl = computed(() => avatarPreview.value || form.avatar);

  const rules = computed(() => ({
    account: [
      { required: true, message: t('systemAdmin.field.account.required') },
      {
        minLength: 1,
        maxLength: 32,
        message: t('systemAdmin.field.account.length'),
      },
    ],
    name: [
      { required: true, message: t('systemAdmin.field.name.required') },
      {
        minLength: 1,
        maxLength: 16,
        message: t('systemAdmin.field.name.length'),
      },
    ],
    role_id: [
      {
        validator: (value: number[], callback: (message?: string) => void) => {
          if (form.root !== 1 && (!value || value.length === 0)) {
            callback(t('systemAdmin.field.roles.required'));
            return;
          }
          callback();
        },
      },
    ],
    password: isEdit.value
      ? [
          {
            validator: (
              value: string,
              callback: (message?: string) => void
            ) => {
              if (value && (value.length < 6 || value.length > 32)) {
                callback(t('systemAdmin.field.password.length'));
                return;
              }
              callback();
            },
          },
        ]
      : [
          { required: true, message: t('systemAdmin.field.password.required') },
          {
            minLength: 6,
            maxLength: 32,
            message: t('systemAdmin.field.password.length'),
          },
        ],
    password_confirm: [
      {
        validator: (value: string, callback: (message?: string) => void) => {
          if (form.password && !value) {
            callback(t('systemAdmin.field.passwordConfirm.required'));
            return;
          }
          if ((value || '') !== (form.password || '')) {
            callback(t('systemAdmin.field.passwordConfirm.mismatch'));
            return;
          }
          callback();
        },
      },
    ],
  }));

  const resetForm = (patch: Partial<AdminForm> = {}) => {
    Object.assign(form, defaultForm(), patch);
    avatarPreview.value = patch.avatar || '';
    nextTick(() => formRef.value?.clearValidate());
  };

  const handleAdd = () => {
    isEdit.value = false;
    resetForm();
    modalVisible.value = true;
  };

  const handleEdit = async (record: AdminRecord) => {
    isEdit.value = true;
    const { data } = await getAdminDetail(record.id);
    resetForm({
      id: data.id,
      account: data.account,
      name: data.name,
      avatar: data.avatar,
      dept_id: data.dept_id || [],
      jobs_id: data.jobs_id || [],
      role_id: data.role_id || [],
      password: '',
      password_confirm: '',
      disable: data.disable,
      multipoint_login: data.multipoint_login,
      root: data.root,
    });
    modalVisible.value = true;
  };

  const handleSubmit = async () => {
    const errors = await formRef.value?.validate();
    if (errors) return false;
    submitLoading.value = true;
    try {
      const payload: AdminForm = {
        ...form,
        dept_id: [...form.dept_id],
        jobs_id: [...form.jobs_id],
        role_id: [...form.role_id],
      };
      if (isEdit.value) await editAdmin(payload);
      else await addAdmin(payload);
      Message.success(t('systemAdmin.tip.success'));
      await fetchData(isEdit.value ? pagination.current : 1);
      return true;
    } finally {
      submitLoading.value = false;
    }
  };

  const handleDelete = async (record: AdminRecord) => {
    await deleteAdmin(record.id);
    Message.success(t('systemAdmin.tip.success'));
    const targetPage = renderData.value.length === 1 ? 1 : pagination.current;
    await fetchData(targetPage);
  };

  const handleStatus = async (record: AdminRecord, enabled: boolean) => {
    const disable = enabled ? 0 : 1;
    await updateAdminStatus(record.id, disable);
    record.disable = disable;
    record.disable_desc =
      disable === 1
        ? t('systemAdmin.status.disabled')
        : t('systemAdmin.status.normal');
    Message.success(t('systemAdmin.tip.success'));
  };

  const onAvatarSuccess = (fileItem: FileItem) => {
    const { response } = fileItem;
    if (!response || response.code !== 20000) {
      Message.error(response?.msg || t('systemAdmin.field.avatar.uploadFail'));
      return;
    }
    form.avatar = response.data.uri;
    avatarPreview.value = response.data.url;
    Message.success(t('systemAdmin.field.avatar.uploadSuccess'));
  };
  const onAvatarError = () => {
    Message.error(t('systemAdmin.field.avatar.uploadFail'));
  };

  // ---- 两阶段导出 ----
  const emptyExportInfo = (): AdminExportInfo => ({
    count: 0,
    page_size: pagination.pageSize,
    sum_page: 0,
    max_page: 0,
    all_max_size: 0,
    page_start: 1,
    page_end: 1,
    file_name: '',
  });
  const exportVisible = ref(false);
  const exportInfoLoading = ref(false);
  const exportLoading = ref(false);
  const exportInfo = reactive<AdminExportInfo>(emptyExportInfo());
  const exportFormRef = ref<FormInstance>();
  const exportForm = reactive({
    page_type: 0 as 0 | 1,
    page_start: 1,
    page_end: 1,
    file_name: '',
  });

  const openExport = async () => {
    exportVisible.value = true;
    exportInfoLoading.value = true;
    try {
      const { data } = await getAdminExportInfo(listParams(1));
      Object.assign(exportInfo, data);
      exportForm.page_type = 0;
      exportForm.page_start = data.page_start;
      exportForm.page_end = data.page_end;
      exportForm.file_name = data.file_name;
    } finally {
      exportInfoLoading.value = false;
    }
  };

  const handleExport = async () => {
    if (exportInfoLoading.value) return false;
    if (
      exportForm.page_type === 1 &&
      exportForm.page_end < exportForm.page_start
    ) {
      Message.error(t('systemAdmin.export.invalidRange'));
      return false;
    }
    exportLoading.value = true;
    try {
      const { data } = await exportAdmins({
        ...listParams(1),
        ...exportForm,
      });
      const link = document.createElement('a');
      link.href = data.url;
      link.download = data.file_name;
      link.rel = 'noopener';
      document.body.appendChild(link);
      link.click();
      link.remove();
      Message.success(t('systemAdmin.export.success'));
      return true;
    } finally {
      exportLoading.value = false;
    }
  };

  Promise.all([fetchData(1), loadOptions()]);
</script>

<script lang="ts">
  export default {
    name: 'SystemAdmin',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px;
  }

  .search-card {
    margin-bottom: 16px;
  }

  .form-tip {
    margin-top: 8px;
    color: var(--color-text-3);
    font-size: 12px;
  }
</style>
