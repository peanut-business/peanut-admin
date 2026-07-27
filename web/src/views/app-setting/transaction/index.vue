<template>
  <div class="container">
    <a-card :bordered="false">
      <a-form
        :model="form"
        layout="vertical"
        style="max-width: 560px"
        @submit.prevent="handleSave"
      >
        <!-- 自动取消未付款订单 -->
        <a-form-item>
          <template #label>
            <span>{{ $t('transaction.cancelUnpaid') }}</span>
          </template>
          <div style="display: flex; align-items: center; gap: 12px">
            <a-switch
              v-model="form.cancel_unpaid_orders"
              :checked-value="1"
              :unchecked-value="0"
            />
            <a-typography-text type="secondary" style="font-size: 12px">
              {{ $t('transaction.cancelUnpaid.enable') }}
            </a-typography-text>
          </div>
        </a-form-item>

        <a-form-item
          v-if="form.cancel_unpaid_orders === 1"
          :label="$t('transaction.cancelUnpaidTimes')"
        >
          <a-input-number
            v-model="form.cancel_unpaid_orders_times"
            :min="1"
            :precision="0"
            style="width: 200px"
          >
            <template #suffix>分钟</template>
          </a-input-number>
        </a-form-item>

        <a-divider style="margin: 8px 0 20px" />

        <!-- 自动核销订单 -->
        <a-form-item>
          <template #label>
            <span>{{ $t('transaction.verificationOrders') }}</span>
          </template>
          <div style="display: flex; align-items: center; gap: 12px">
            <a-switch
              v-model="form.verification_orders"
              :checked-value="1"
              :unchecked-value="0"
            />
            <a-typography-text type="secondary" style="font-size: 12px">
              {{ $t('transaction.verificationOrders.enable') }}
            </a-typography-text>
          </div>
        </a-form-item>

        <a-form-item
          v-if="form.verification_orders === 1"
          :label="$t('transaction.verificationOrdersTimes')"
        >
          <a-input-number
            v-model="form.verification_orders_times"
            :min="1"
            :precision="0"
            style="width: 200px"
          >
            <template #suffix>小时</template>
          </a-input-number>
        </a-form-item>

        <a-form-item>
          <a-button type="primary" html-type="submit" :loading="saving">
            {{ $t('transaction.save') }}
          </a-button>
        </a-form-item>
      </a-form>
    </a-card>
  </div>
</template>

<script lang="ts" setup>
  import { reactive, ref, onMounted } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import {
    getTransactionConfig,
    saveTransactionConfig,
    TransactionConfig,
  } from '@/api/app';

  const { t } = useI18n();

  const saving = ref(false);

  const form = reactive<TransactionConfig>({
    cancel_unpaid_orders: 1,
    cancel_unpaid_orders_times: 30,
    verification_orders: 1,
    verification_orders_times: 24,
  });

  const fetchConfig = async () => {
    const res = await getTransactionConfig();
    const data = (res.data as any).data as TransactionConfig;
    Object.assign(form, data);
  };

  const handleSave = async () => {
    saving.value = true;
    try {
      await saveTransactionConfig({ ...form });
      Message.success(t('transaction.tip.success'));
    } finally {
      saving.value = false;
    }
  };

  onMounted(fetchConfig);
</script>
