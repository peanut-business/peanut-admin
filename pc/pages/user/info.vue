<template>
  <div class="bg-white rounded-xl shadow-sm p-8">
    <h2 class="text-xl font-bold text-gray-800 mb-6">个人资料</h2>

    <el-form :model="form" label-width="80px" size="large" @submit.prevent="handleSave">
      <el-form-item label="头像">
        <el-avatar :size="80" :src="form.avatar" />
      </el-form-item>
      <el-form-item label="昵称">
        <el-input v-model="form.nickname" placeholder="请输入昵称" />
      </el-form-item>
      <el-form-item label="性别">
        <el-radio-group v-model="form.sex">
          <el-radio :label="0">未知</el-radio>
          <el-radio :label="1">男</el-radio>
          <el-radio :label="2">女</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="生日">
        <el-date-picker v-model="form.birthday" type="date" placeholder="选择生日" value-format="YYYY-MM-DD" />
      </el-form-item>
      <el-form-item label="邮箱">
        <el-input v-model="form.email" type="email" placeholder="请输入邮箱" />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" native-type="submit" :loading="loading">保存修改</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'user', middleware: 'auth' })

const userStore = useUserStore()
const apiBase = useRuntimeConfig().public.apiBase
const loading = ref(false)

interface UserInfo {
  id: number; nickname: string; avatar: string; sex: number
  birthday: string; email: string; mobile: string
}

const { data } = await useFetch<{ code: number; data: UserInfo }>(
  `${apiBase}/api/user/info`,
  { headers: { Authorization: `Bearer ${userStore.token}` } }
)

const form = ref({
  nickname: data.value?.data?.nickname || '',
  avatar: data.value?.data?.avatar || '',
  sex: data.value?.data?.sex || 0,
  birthday: data.value?.data?.birthday || '',
  email: data.value?.data?.email || '',
})

async function handleSave() {
  loading.value = true
  try {
    await $fetch(`${apiBase}/api/user/setInfo`, {
      method: 'POST',
      body: { ...form.value },
      headers: { Authorization: `Bearer ${userStore.token}` },
    })
    userStore.setUserInfo({ nickname: form.value.nickname, avatar: form.value.avatar })
    ElMessage.success('保存成功')
  } catch {
    ElMessage.error('保存失败')
  } finally {
    loading.value = false
  }
}
</script>
