<template>
  <div class="container">
    <a-spin :loading="loading" tip="Loading...">
      <a-grid :cols="24" :col-gap="16" :row-gap="16">
        <a-grid-item :span="{ xs: 24, lg: 7 }">
          <a-card
            class="general-card full-height"
            :title="$t('workplace.version.title')"
          >
            <a-descriptions :column="1" bordered size="large">
              <a-descriptions-item :label="$t('workplace.version.platform')">
                {{ workbench.version.name || 'Peanut Admin' }}
              </a-descriptions-item>
              <a-descriptions-item :label="$t('workplace.version.current')">
                {{ workbench.version.version }}
              </a-descriptions-item>
              <a-descriptions-item :label="$t('workplace.version.based')">
                {{ workbench.version.based }}
              </a-descriptions-item>
              <a-descriptions-item
                v-if="workbench.version.website"
                :label="$t('workplace.version.website')"
              >
                {{ workbench.version.website }}
              </a-descriptions-item>
              <a-descriptions-item :label="$t('workplace.version.channel')">
                <a-space v-if="hasChannelLinks">
                  <a-link
                    v-if="workbench.version.channel.website"
                    :href="workbench.version.channel.website"
                    target="_blank"
                  >
                    {{ $t('workplace.version.official') }}
                  </a-link>
                  <a-link
                    v-if="workbench.version.channel.gitee"
                    :href="workbench.version.channel.gitee"
                    target="_blank"
                  >
                    Gitee
                  </a-link>
                </a-space>
                <span v-else>--</span>
              </a-descriptions-item>
            </a-descriptions>
          </a-card>
        </a-grid-item>

        <a-grid-item :span="{ xs: 24, lg: 17 }">
          <a-card class="general-card full-height">
            <template #title>
              <a-space>
                <span>{{ $t('workplace.today.title') }}</span>
                <span class="updated-at">
                  {{ $t('workplace.today.updatedAt')
                  }}{{ workbench.today.time }}
                </span>
              </a-space>
            </template>
            <a-grid :cols="24" :col-gap="16" :row-gap="24">
              <a-grid-item
                v-for="metric in metrics"
                :key="metric.key"
                :span="{ xs: 12, sm: 6 }"
              >
                <a-statistic :title="$t(metric.label)" :value="metric.value">
                  <template #suffix>
                    <span class="metric-unit">{{ $t(metric.unit) }}</span>
                  </template>
                </a-statistic>
                <div class="metric-total">
                  {{ $t('workplace.today.total') }}{{ metric.total }}
                </div>
              </a-grid-item>
            </a-grid>
          </a-card>
        </a-grid-item>

        <a-grid-item :span="24">
          <a-card class="general-card" :title="$t('workplace.shortcuts.title')">
            <a-grid :cols="24" :col-gap="12" :row-gap="20">
              <a-grid-item
                v-for="item in workbench.menu"
                :key="item.url"
                :span="{ xs: 6, sm: 3 }"
              >
                <router-link :to="item.url" class="shortcut">
                  <a-avatar :size="48" shape="square" :image-url="item.image">
                    <icon-apps />
                  </a-avatar>
                  <span>{{ item.name }}</span>
                </router-link>
              </a-grid-item>
            </a-grid>
          </a-card>
        </a-grid-item>

        <a-grid-item :span="{ xs: 24, lg: 16 }">
          <a-card class="general-card" :title="$t('workplace.visitor.title')">
            <Chart height="320px" :option="visitorOption" />
          </a-card>
        </a-grid-item>
        <a-grid-item :span="{ xs: 24, lg: 8 }">
          <a-card class="general-card" :title="$t('workplace.sale.title')">
            <Chart height="320px" :option="saleOption" />
          </a-card>
        </a-grid-item>

        <a-grid-item :span="24">
          <a-card class="general-card" :title="$t('workplace.support.title')">
            <a-grid :cols="24" :col-gap="16" :row-gap="16">
              <a-grid-item
                v-for="item in workbench.support"
                :key="item.title"
                :span="{ xs: 24, sm: 12 }"
              >
                <div class="support-item">
                  <a-avatar :size="48" :image-url="item.image">
                    <icon-question-circle />
                  </a-avatar>
                  <div>
                    <div class="support-title">{{ item.title }}</div>
                    <div class="support-desc">{{ item.desc }}</div>
                  </div>
                </div>
              </a-grid-item>
            </a-grid>
          </a-card>
        </a-grid-item>
      </a-grid>
    </a-spin>
  </div>
</template>

<script lang="ts" setup>
  import { computed, onMounted, reactive, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import { useI18n } from 'vue-i18n';
  import Chart from '@/components/chart/index.vue';
  import { getWorkbench, type WorkbenchData } from '@/api/workbench';

  const { t } = useI18n();
  const loading = ref(true);
  const workbench = reactive<WorkbenchData>({
    version: {
      version: '',
      website: '',
      name: '',
      based: '',
      channel: { website: '', gitee: '' },
    },
    today: {
      time: '',
      today_sales: 0,
      total_sales: 0,
      today_visitor: 0,
      total_visitor: 0,
      today_new_user: 0,
      total_new_user: 0,
      order_num: 0,
      order_sum: 0,
    },
    menu: [],
    visitor: { date: [], list: [] },
    support: [],
    sale: { date: [], list: [] },
  });

  const hasChannelLinks = computed(
    () =>
      Boolean(workbench.version.channel.website) ||
      Boolean(workbench.version.channel.gitee)
  );

  const metrics = computed(() => [
    {
      key: 'sales',
      label: 'workplace.today.sales',
      unit: 'workplace.unit.yuan',
      value: workbench.today.today_sales,
      total: workbench.today.total_sales,
    },
    {
      key: 'orders',
      label: 'workplace.today.orders',
      unit: 'workplace.unit.order',
      value: workbench.today.order_num,
      total: workbench.today.order_sum,
    },
    {
      key: 'users',
      label: 'workplace.today.users',
      unit: 'workplace.unit.person',
      value: workbench.today.today_new_user,
      total: workbench.today.total_new_user,
    },
    {
      key: 'visitors',
      label: 'workplace.today.visitors',
      unit: 'workplace.unit.visit',
      value: workbench.today.today_visitor,
      total: workbench.today.total_visitor,
    },
  ]);

  const visitorOption = computed(() => ({
    tooltip: { trigger: 'axis' },
    grid: { left: 48, right: 24, top: 32, bottom: 32 },
    xAxis: {
      type: 'category',
      boundaryGap: false,
      data: [...workbench.visitor.date].reverse(),
    },
    yAxis: { type: 'value' },
    series: [
      {
        name: workbench.visitor.list[0]?.name || '访客数',
        type: 'line',
        smooth: true,
        showSymbol: false,
        areaStyle: { opacity: 0.12 },
        data: workbench.visitor.list[0]?.data || [],
      },
    ],
  }));

  const saleOption = computed(() => ({
    tooltip: { trigger: 'axis' },
    grid: { left: 48, right: 24, top: 32, bottom: 32 },
    xAxis: {
      type: 'category',
      data: [...workbench.sale.date].reverse(),
    },
    yAxis: { type: 'value' },
    series: [
      {
        name: workbench.sale.list[0]?.name || '销售量',
        type: 'bar',
        barMaxWidth: 32,
        itemStyle: { borderRadius: [6, 6, 0, 0] },
        data: workbench.sale.list[0]?.data || [],
      },
    ],
  }));

  const fetchWorkbench = async () => {
    loading.value = true;
    try {
      const { data } = await getWorkbench();
      Object.assign(workbench, data);
    } catch (error) {
      Message.error(t('workplace.loadFailed'));
    } finally {
      loading.value = false;
    }
  };

  onMounted(fetchWorkbench);
</script>

<script lang="ts">
  export default {
    name: 'Workplace',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 20px;
  }

  .full-height {
    height: 100%;
  }

  .updated-at,
  .metric-total,
  .support-desc {
    color: var(--color-text-3);
    font-size: 12px;
  }

  .metric-unit {
    margin-left: 4px;
    color: var(--color-text-3);
    font-size: 13px;
  }

  .metric-total {
    margin-top: 8px;
  }

  .shortcut {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: center;
    color: var(--color-text-2);
    text-decoration: none;
  }

  .shortcut:hover {
    color: rgb(var(--primary-6));
  }

  .support-item {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 16px;
    background: var(--color-fill-1);
    border-radius: var(--border-radius-medium);
  }

  .support-title {
    margin-bottom: 6px;
    color: var(--color-text-1);
    font-weight: 500;
  }
</style>
