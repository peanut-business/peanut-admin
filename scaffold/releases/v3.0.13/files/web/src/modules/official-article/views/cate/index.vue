<template>
  <div class="container">
    <Breadcrumb :items="['menu.article', 'menu.article.cate']" />
    <el-card class="general-card">
      <template #header>{{ $t('menu.article.cate') }}</template>
      <el-alert type="warning" :closable="false" style="margin-bottom: 16px">
        {{ $t('articleCate.alert') }}
      </el-alert>
      <el-button
        v-permission="['official.article.category.add']"
        type="primary"
        :icon="Plus"
        style="margin-bottom: 16px"
        @click="openAdd"
      >
        {{ $t('articleCate.button.add') }}
      </el-button>
      <el-table v-loading="loading" row-key="id" :data="renderData" border>
        <el-table-column prop="name" :label="$t('articleCate.columns.name')" />
        <el-table-column
          prop="article_count"
          :label="$t('articleCate.columns.articleCount')"
          width="120"
        />
        <el-table-column :label="$t('articleCate.columns.isShow')" width="100">
          <template #default="{ row }">
            <el-switch
              v-permission="['official.article.category.update-status']"
              :model-value="row.is_show === 1"
              @change="(value: boolean) => onStatusChange(row, value)"
            />
          </template>
        </el-table-column>
        <el-table-column
          prop="sort"
          :label="$t('articleCate.columns.sort')"
          width="100"
        />
        <el-table-column
          :label="$t('articleCate.columns.operations')"
          width="160"
          fixed="right"
        >
          <template #default="{ row }">
            <el-space>
              <el-button
                v-permission="['official.article.category.edit']"
                link
                type="primary"
                size="small"
                @click="openEdit(row)"
              >
                {{ $t('articleCate.button.edit') }}
              </el-button>
              <el-popconfirm
                :title="$t('articleCate.confirm.delete')"
                @confirm="onDelete(row)"
              >
                <template #reference>
                  <el-button
                    v-permission="['official.article.category.delete']"
                    link
                    type="danger"
                    size="small"
                  >
                    {{ $t('articleCate.button.delete') }}
                  </el-button>
                </template>
              </el-popconfirm>
            </el-space>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrapper">
        <el-pagination
          :current-page="pagination.current"
          :page-size="pagination.pageSize"
          :total="pagination.total"
          layout="total, prev, pager, next"
          @current-change="onPageChange"
        />
      </div>

      <el-dialog
        v-model="modalVisible"
        :title="
          form.id ? $t('articleCate.modal.edit') : $t('articleCate.modal.add')
        "
        width="520px"
      >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="auto">
          <el-form-item prop="name" :label="$t('articleCate.form.name')">
            <el-input
              v-model="form.name"
              :placeholder="$t('articleCate.form.name.placeholder')"
              :maxlength="90"
              show-word-limit
            />
          </el-form-item>
          <el-form-item prop="sort" :label="$t('articleCate.form.sort')">
            <div>
              <el-input-number
                v-model="form.sort"
                :min="0"
                :max="9999"
                style="width: 160px"
              />
              <div class="form-tip">
                {{ $t('articleCate.form.sort.tip') }}
              </div>
            </div>
          </el-form-item>
          <el-form-item prop="is_show" :label="$t('articleCate.form.isShow')">
            <el-switch
              v-model="form.is_show"
              :active-value="1"
              :inactive-value="0"
            />
          </el-form-item>
        </el-form>
        <template #footer>
          <el-button @click="modalVisible = false">取消</el-button>
          <el-button type="primary" @click="onSubmit">确定</el-button>
        </template>
      </el-dialog>
    </el-card>
  </div>
</template>

<script lang="ts" setup>
  import { nextTick, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { ElMessage, type FormInstance, type FormRules } from 'element-plus';
  import { Plus } from '@element-plus/icons-vue';
  import useLoading from '@/hooks/loading';
  import {
    getArticleCateList,
    getArticleCateDetail,
    addArticleCate,
    editArticleCate,
    deleteArticleCate,
    updateArticleCateStatus,
    type ArticleCateRecord,
  } from '@/modules/official-article/api';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<ArticleCateRecord[]>([]);

  const pagination = reactive({
    current: 1,
    pageSize: 25,
    total: 0,
    showTotal: true,
  });

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const { data } = await getArticleCateList({
        page_no: page,
        page_size: pagination.pageSize,
      });
      renderData.value = data.lists;
      pagination.current = data.page_no;
      pagination.pageSize = data.page_size;
      pagination.total = data.count;
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const onPageChange = (current: number) => fetchData(current);

  const formRef = ref<FormInstance>();
  const modalVisible = ref(false);
  const generateForm = () => ({ id: 0, name: '', sort: 0, is_show: 1 });
  const form = ref(generateForm());

  const rules: FormRules = {
    name: [
      { required: true, message: t('articleCate.form.name.required') },
      {
        min: 1,
        max: 90,
        message: t('articleCate.form.name.length'),
      },
    ],
    sort: [
      {
        validator: (
          _rule: unknown,
          value: number,
          callback: (error?: Error) => void
        ) => {
          if (value == null || Number(value) < 0) {
            callback(new Error(t('articleCate.form.sort.min')));
            return;
          }
          callback();
        },
      },
    ],
  };

  const openAdd = async () => {
    form.value = generateForm();
    modalVisible.value = true;
    await nextTick();
    formRef.value?.clearValidate();
  };

  const openEdit = async (record: ArticleCateRecord) => {
    const { data } = await getArticleCateDetail(record.id);
    form.value = {
      id: data.id,
      name: data.name,
      sort: data.sort,
      is_show: data.is_show,
    };
    modalVisible.value = true;
    await nextTick();
    formRef.value?.clearValidate();
  };

  const onSubmit = async () => {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;
    if (form.value.id) {
      await editArticleCate(form.value);
    } else {
      await addArticleCate(form.value);
    }
    ElMessage.success(t('articleCate.message.success'));
    modalVisible.value = false;
    fetchData(pagination.current);
  };

  const onDelete = async (record: ArticleCateRecord) => {
    await deleteArticleCate(record.id);
    ElMessage.success(t('articleCate.message.success'));
    fetchData(pagination.current);
  };

  const onStatusChange = async (record: ArticleCateRecord, val: unknown) => {
    const previousStatus = record.is_show;
    const nextStatus = val ? 1 : 0;
    try {
      await updateArticleCateStatus(record.id, nextStatus);
      record.is_show = nextStatus;
      ElMessage.success(t('articleCate.message.success'));
    } catch {
      record.is_show = previousStatus;
    }
  };
</script>

<script lang="ts">
  export default {
    name: 'ArticleCate',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }

  .form-tip {
    margin-top: 4px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 20px;
  }

  .pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
  }
</style>
