<template>
  <div class="container">
    <Breadcrumb :items="['menu.user', 'menu.user.setting']" />
    <a-card class="general-card" :title="$t('menu.user.setting')">
      <a-tabs default-active-key="1" type="rounded">
        <a-tab-pane key="1" :title="$t('userSetting.tab.basicInformation')">
          <a-spin :loading="loading" style="width: 100%">
            <a-form
              ref="basicRef"
              :model="basicForm"
              :rules="basicRules"
              layout="vertical"
              style="max-width: 480px; margin-top: 8px"
            >
              <a-form-item
                field="avatar"
                :label="$t('userSetting.label.avatar')"
              >
                <a-upload
                  :action="uploadAction"
                  :headers="uploadHeaders"
                  :show-file-list="false"
                  list-type="picture-card"
                  accept="image/*"
                  @success="onAvatarSuccess"
                  @error="onAvatarError"
                >
                  <template #upload-button>
                    <a-avatar :size="88" shape="square">
                      <img
                        v-if="basicForm.avatar"
                        :src="avatarUrl"
                        alt="avatar"
                      />
                      <icon-plus v-else />
                    </a-avatar>
                  </template>
                </a-upload>
              </a-form-item>
              <a-form-item
                field="username"
                :label="$t('userSetting.label.name')"
              >
                <a-input v-model="username" disabled />
              </a-form-item>
              <a-form-item
                field="nickname"
                :label="$t('userSetting.basicInfo.form.label.nickname')"
              >
                <a-input
                  v-model="basicForm.nickname"
                  :placeholder="
                    $t('userSetting.basicInfo.placeholder.nickname')
                  "
                />
              </a-form-item>
              <a-form-item>
                <a-button
                  type="primary"
                  :loading="basicLoading"
                  @click="saveBasic"
                >
                  {{ $t('userSetting.save') }}
                </a-button>
              </a-form-item>
            </a-form>
          </a-spin>
        </a-tab-pane>
        <a-tab-pane key="2" :title="$t('userSetting.tab.securitySettings')">
          <a-form
            ref="pwdRef"
            :model="pwdForm"
            :rules="pwdRules"
            layout="vertical"
            style="max-width: 480px; margin-top: 8px"
          >
            <a-form-item
              field="password_old"
              :label="$t('userSetting.security.oldPassword')"
            >
              <a-input-password
                v-model="pwdForm.password_old"
                :placeholder="
                  $t('userSetting.security.oldPassword.placeholder')
                "
              />
            </a-form-item>
            <a-form-item
              field="password"
              :label="$t('userSetting.security.newPassword')"
            >
              <a-input-password
                v-model="pwdForm.password"
                :placeholder="
                  $t('userSetting.security.newPassword.placeholder')
                "
              />
            </a-form-item>
            <a-form-item
              field="password_confirm"
              :label="$t('userSetting.security.confirmPassword')"
            >
              <a-input-password
                v-model="pwdForm.password_confirm"
                :placeholder="
                  $t('userSetting.security.confirmPassword.placeholder')
                "
              />
            </a-form-item>
            <a-form-item>
              <a-button
                type="primary"
                :loading="pwdLoading"
                @click="savePassword"
              >
                {{ $t('userSetting.save') }}
              </a-button>
            </a-form-item>
          </a-form>
        </a-tab-pane>
      </a-tabs>
    </a-card>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import type { FileItem } from '@arco-design/web-vue/es/upload/interfaces';
  import useLoading from '@/hooks/loading';
  import { useUserStore } from '@/store';
  import { getToken } from '@/utils/auth';
  import {
    getAdminSelf,
    editAdminSelf,
    type EditSelfForm,
  } from '@/api/system/admin';

  const { t } = useI18n();
  const userStore = useUserStore();
  const { loading, setLoading } = useLoading(true);

  const uploadAction = '/api/admin/upload/image';
  const uploadHeaders = computed(() => {
    const token = getToken();
    const headers: Record<string, string> = {};
    if (token) headers.Authorization = `Bearer ${token}`;
    return headers;
  });

  const username = ref('');
  const basicRef = ref<FormInstance>();
  const basicLoading = ref(false);
  const basicForm = reactive<{
    nickname: string;
    avatar: string;
    avatarUrl: string;
  }>({ nickname: '', avatar: '', avatarUrl: '' });
  const avatarUrl = computed(() => basicForm.avatarUrl || basicForm.avatar);

  const basicRules = {
    nickname: [
      {
        required: true,
        message: t('userSetting.form.error.nickname.required'),
      },
    ],
  };

  const pwdRef = ref<FormInstance>();
  const pwdLoading = ref(false);
  const pwdForm = reactive({
    password_old: '',
    password: '',
    password_confirm: '',
  });
  const pwdRules = {
    password_old: [
      { required: true, message: t('userSetting.security.error.oldRequired') },
    ],
    password: [
      { required: true, message: t('userSetting.security.error.newRequired') },
      {
        minLength: 6,
        maxLength: 32,
        message: t('userSetting.security.error.length'),
      },
    ],
    password_confirm: [
      {
        required: true,
        message: t('userSetting.security.error.confirmRequired'),
      },
      {
        validator: (value: string, cb: (msg?: string) => void) => {
          if (value !== pwdForm.password) {
            cb(t('userSetting.security.error.mismatch'));
          } else {
            cb();
          }
        },
      },
    ],
  };

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getAdminSelf();
      username.value = data.username;
      basicForm.nickname = data.nickname;
      basicForm.avatar = data.avatar;
      basicForm.avatarUrl = data.avatar;
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const onAvatarSuccess = (fileItem: FileItem) => {
    const res = fileItem.response;
    if (!res || res.code !== 20000) {
      Message.error(res?.msg || t('userSetting.avatar.uploadFail'));
      return;
    }
    basicForm.avatar = res.data.uri;
    basicForm.avatarUrl = res.data.url;
    Message.success(t('userSetting.avatar.uploadSuccess'));
  };
  const onAvatarError = () => {
    Message.error(t('userSetting.avatar.uploadFail'));
  };

  const saveBasic = async () => {
    const err = await basicRef.value?.validate();
    if (err) return;
    basicLoading.value = true;
    try {
      const payload: EditSelfForm = {
        nickname: basicForm.nickname,
        avatar: basicForm.avatar,
      };
      await editAdminSelf(payload);
      userStore.setInfo({
        name: basicForm.nickname,
        avatar: basicForm.avatarUrl,
      });
      Message.success(t('userSetting.saveSuccess'));
    } finally {
      basicLoading.value = false;
    }
  };

  const savePassword = async () => {
    const err = await pwdRef.value?.validate();
    if (err) return;
    pwdLoading.value = true;
    try {
      const payload: EditSelfForm = {
        nickname: basicForm.nickname,
        avatar: basicForm.avatar,
        password: pwdForm.password,
        password_confirm: pwdForm.password_confirm,
        password_old: pwdForm.password_old,
      };
      await editAdminSelf(payload);
      Message.success(t('userSetting.security.success'));
      pwdRef.value?.resetFields();
    } finally {
      pwdLoading.value = false;
    }
  };
</script>

<script lang="ts">
  export default {
    name: 'Setting',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
