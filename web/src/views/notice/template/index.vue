<template>
  <div class="container">
    <a-card :bordered="false">
      <a-table
        :data="tableData"
        :loading="loading"
        :pagination="false"
        row-key="id"
      >
        <template #columns>
          <a-table-column
            :title="$t('noticeScene.columns.name')"
            data-index="name"
            :width="180"
          />
          <a-table-column
            :title="$t('noticeScene.columns.description')"
            data-index="description"
          />
          <a-table-column
            :title="$t('noticeScene.columns.recipient')"
            data-index="recipient"
            :width="100"
          />
          <a-table-column
            :title="$t('noticeScene.columns.sms')"
            data-index="sms_status"
            :width="100"
          >
            <template #cell="{ record }">
              <a-tag :color="record.sms_status === 1 ? 'green' : 'red'">
                {{
                  record.sms_status === 1
                    ? $t('noticeScene.status.enabled')
                    : $t('noticeScene.status.disabled')
                }}
              </a-tag>
            </template>
          </a-table-column>
          <a-table-column
            :title="$t('noticeScene.columns.content')"
            data-index="sms_content"
            :ellipsis="true"
            :tooltip="true"
          />
          <a-table-column
            :title="$t('noticeScene.columns.operations')"
            align="center"
            :width="100"
          >
            <template #cell="{ record }">
              <a-button
                v-permission="['notice/scene/detail', 'notice/scene/save']"
                type="text"
                size="small"
                @click="openSettings(record)"
              >
                {{ $t('noticeScene.operation.settings') }}
              </a-button>
            </template>
          </a-table-column>
        </template>
      </a-table>
    </a-card>

    <a-modal
      v-model:visible="modalVisible"
      :title="currentScene?.name || $t('noticeScene.operation.settings')"
      :width="620"
      @before-ok="saveSettings"
      @cancel="closeModal"
    >
      <a-form ref="formRef" :model="form" layout="vertical">
        <a-form-item :label="$t('noticeScene.field.variables')">
          <a-space>
            <a-tag v-for="item in currentScene?.variables || []" :key="item">
              {{ '$' + '{' + item + '}' }}
            </a-tag>
          </a-space>
        </a-form-item>
        <a-form-item
          field="sms_template_id"
          :label="$t('noticeScene.field.templateId')"
          :rules="[{ maxLength: 100 }]"
        >
          <a-input v-model="form.sms_template_id" allow-clear />
        </a-form-item>
        <a-form-item
          field="sms_content"
          :label="$t('noticeScene.field.content')"
          :extra="$t('noticeScene.field.contentTip') + '{code}'"
          :rules="[{ maxLength: 500 }]"
        >
          <a-textarea
            v-model="form.sms_content"
            :auto-size="{ minRows: 4, maxRows: 8 }"
          />
        </a-form-item>
        <a-form-item :label="$t('noticeScene.field.enabled')">
          <a-switch
            v-model="form.sms_status"
            :checked-value="1"
            :unchecked-value="0"
          />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { onMounted, reactive, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import { useI18n } from 'vue-i18n';
  import {
    getNoticeSceneDetail,
    getNoticeSceneList,
    saveNoticeScene,
    type NoticeSceneRecord,
  } from '@/api/notice';

  const { t } = useI18n();
  const loading = ref(false);
  const tableData = ref<NoticeSceneRecord[]>([]);
  const modalVisible = ref(false);
  const currentScene = ref<NoticeSceneRecord>();
  const formRef = ref<FormInstance>();
  const form = reactive({
    id: 0,
    sms_template_id: '',
    sms_content: '',
    sms_status: 0,
  });

  const fetchData = async () => {
    loading.value = true;
    try {
      const { data } = await getNoticeSceneList();
      tableData.value = data.list;
    } finally {
      loading.value = false;
    }
  };

  const openSettings = async (record: NoticeSceneRecord) => {
    const { data } = await getNoticeSceneDetail(record.id);
    currentScene.value = data;
    Object.assign(form, {
      id: data.id,
      sms_template_id: data.sms_template_id || '',
      sms_content: data.sms_content || '',
      sms_status: data.sms_status,
    });
    modalVisible.value = true;
  };

  const closeModal = () => {
    modalVisible.value = false;
    currentScene.value = undefined;
    formRef.value?.clearValidate();
  };

  const saveSettings = async () => {
    const error = await formRef.value?.validate();
    if (error) return false;
    await saveNoticeScene({ ...form });
    Message.success(t('noticeScene.tip.success'));
    closeModal();
    await fetchData();
    return true;
  };

  onMounted(fetchData);
</script>
