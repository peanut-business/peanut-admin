<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.maintenance']" />
    <a-spin :loading="loading" style="width: 100%">
      <a-row :gutter="16">
        <a-col :span="12">
          <a-card
            class="general-card"
            :title="$t('systemMaintenance.server.title')"
          >
            <a-descriptions :column="1" bordered>
              <a-descriptions-item
                v-for="item in info.server"
                :key="item.param"
                :label="item.param"
              >
                {{ item.value }}
              </a-descriptions-item>
            </a-descriptions>
          </a-card>
        </a-col>
        <a-col :span="12">
          <a-card
            class="general-card"
            :title="$t('systemMaintenance.env.title')"
          >
            <a-table
              :data="info.env"
              :pagination="false"
              :bordered="{ cell: true }"
              row-key="option"
            >
              <template #columns>
                <a-table-column
                  :title="$t('systemMaintenance.env.option')"
                  data-index="option"
                />
                <a-table-column
                  :title="$t('systemMaintenance.env.require')"
                  data-index="require"
                />
                <a-table-column :title="$t('systemMaintenance.env.status')">
                  <template #cell="{ record }">
                    <a-tag :color="record.status ? 'green' : 'red'">
                      {{
                        record.status
                          ? $t('systemMaintenance.status.ok')
                          : $t('systemMaintenance.status.fail')
                      }}
                    </a-tag>
                  </template>
                </a-table-column>
              </template>
            </a-table>
          </a-card>
          <a-card
            class="general-card"
            :title="$t('systemMaintenance.auth.title')"
            style="margin-top: 16px"
          >
            <a-table
              :data="info.auth"
              :pagination="false"
              :bordered="{ cell: true }"
              row-key="dir"
            >
              <template #columns>
                <a-table-column
                  :title="$t('systemMaintenance.auth.dir')"
                  data-index="dir"
                />
                <a-table-column
                  :title="$t('systemMaintenance.auth.require')"
                  data-index="require"
                />
                <a-table-column :title="$t('systemMaintenance.env.status')">
                  <template #cell="{ record }">
                    <a-tag :color="record.status ? 'green' : 'red'">
                      {{
                        record.status
                          ? $t('systemMaintenance.status.writable')
                          : $t('systemMaintenance.status.readonly')
                      }}
                    </a-tag>
                  </template>
                </a-table-column>
              </template>
            </a-table>
          </a-card>
          <a-card
            class="general-card"
            :title="$t('systemMaintenance.cache.title')"
            style="margin-top: 16px"
          >
            <a-space direction="vertical" fill>
              <a-typography-text type="secondary">
                {{ $t('systemMaintenance.cache.desc') }}
              </a-typography-text>
              <a-popconfirm
                :content="$t('systemMaintenance.cache.confirm')"
                @ok="handleClearCache"
              >
                <a-button
                  v-permission="['system/clearcache']"
                  type="primary"
                  status="warning"
                  :loading="clearing"
                >
                  <template #icon><icon-delete /></template>
                  {{ $t('systemMaintenance.cache.clear') }}
                </a-button>
              </a-popconfirm>
            </a-space>
          </a-card>
        </a-col>
      </a-row>
    </a-spin>
  </div>
</template>

<script lang="ts" setup>
  import { reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import useLoading from '@/hooks/loading';
  import {
    getSystemInfo,
    clearSystemCache,
    type SystemInfo,
  } from '@/api/system/system';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const clearing = ref(false);

  const info = reactive<SystemInfo>({
    server: [],
    env: [],
    auth: [],
  });

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getSystemInfo();
      info.server = data.server;
      info.env = data.env;
      info.auth = data.auth;
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const handleClearCache = async () => {
    clearing.value = true;
    try {
      await clearSystemCache();
      Message.success(t('systemMaintenance.cache.success'));
    } finally {
      clearing.value = false;
    }
  };
</script>

<script lang="ts">
  export default {
    name: 'SystemMaintenance',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
