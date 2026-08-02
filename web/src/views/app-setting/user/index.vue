<template>
  <div class="container">
    <Breadcrumb :items="['menu.appSetting', 'menu.appSetting.user']" />
    <a-spin :loading="loading" style="width: 100%">
      <a-card class="general-card" title="用户默认资料">
        <a-form :model="user" layout="vertical" class="form-width">
          <a-form-item label="默认头像"><FilePicker :type="10" :limit="1" button-text="选择头像" @select="(urls) => user.default_avatar = urls[0] || ''" /><img v-if="user.default_avatar" :src="user.default_avatar" class="avatar" /></a-form-item>
          <a-button v-permission="['config/user/save']" type="primary" :loading="savingUser" @click="submitUser">保存用户配置</a-button>
        </a-form>
      </a-card>
      <a-card class="general-card" title="登录配置" style="margin-top: 16px">
        <a-form :model="login" layout="vertical" class="form-width">
          <a-form-item label="登录方式"><a-checkbox-group v-model="login.login_way"><a-checkbox :value="1">账号密码</a-checkbox><a-checkbox :value="2">手机验证码</a-checkbox></a-checkbox-group></a-form-item>
          <a-form-item label="登录协议"><a-switch v-model="login.login_agreement" :checked-value="1" :unchecked-value="0" /></a-form-item>
          <a-form-item label="第三方登录总开关"><a-switch v-model="login.third_auth" :checked-value="1" :unchecked-value="0" /></a-form-item>
          <a-form-item label="微信登录"><a-switch v-model="login.wechat_auth" :checked-value="1" :unchecked-value="0" /></a-form-item>
          <a-form-item label="微信首次登录强制绑定手机"><a-switch v-model="login.coerce_mobile" :checked-value="1" :unchecked-value="0" /></a-form-item>
          <a-button v-permission="['config/login/save']" type="primary" :loading="savingLogin" @click="submitLogin">保存登录配置</a-button>
        </a-form>
      </a-card>
    </a-spin>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue';
import { Message } from '@arco-design/web-vue';
import FilePicker from '@/components/file-picker/index.vue';
import useLoading from '@/hooks/loading';
import { getLoginSetting, getUserSetting, saveLoginSetting, saveUserSetting, type LoginSetting, type UserSetting } from '@/api/system-settings';

const { loading, setLoading } = useLoading(true);
const savingUser = ref(false);
const savingLogin = ref(false);
const user = reactive<UserSetting>({ default_avatar: '' });
const login = reactive<LoginSetting>({ login_way: [1, 2], coerce_mobile: 0, login_agreement: 0, third_auth: 0, wechat_auth: 0 });
async function load() {
  setLoading(true);
  try { const [u, l] = await Promise.all([getUserSetting(), getLoginSetting()]); Object.assign(user, u.data); Object.assign(login, l.data); }
  finally { setLoading(false); }
}
load();
async function submitUser() { savingUser.value = true; try { await saveUserSetting({ ...user }); Message.success('用户配置已保存'); } finally { savingUser.value = false; } }
async function submitLogin() { savingLogin.value = true; try { await saveLoginSetting({ ...login }); Message.success('登录配置已保存'); } finally { savingLogin.value = false; } }
</script>

<style scoped lang="less">
.container { padding: 0 20px 20px; }
.form-width { max-width: 620px; }
.avatar { width: 72px; height: 72px; margin-left: 12px; border-radius: 50%; object-fit: cover; }
</style>
