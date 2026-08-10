<template>
  <div class="navbar">
    <div class="left-side">
      <el-space class="left-content" :size="12" alignment="center">
        <img
          alt="logo"
          :src="brandStore.website.web_logo"
          style="width: 28px; height: 28px"
        />
        <h5 class="brand-title">{{ brandStore.website.name }}</h5>
        <el-button
          v-if="!topMenu && appStore.device === 'mobile'"
          class="mobile-menu-button"
          text
          circle
          @click="toggleDrawerMenu"
        >
          <el-icon><Menu /></el-icon>
        </el-button>
      </el-space>
    </div>
    <div class="center-side">
      <MenuComponent v-if="topMenu" />
    </div>
    <ul class="right-side">
      <li>
        <el-tooltip :content="$t('settings.search')">
          <el-button class="nav-btn" plain circle>
            <el-icon><Search /></el-icon>
          </el-button>
        </el-tooltip>
      </li>
      <li>
        <el-tooltip :content="$t('settings.language')">
          <el-dropdown trigger="click" @command="handleLocaleChange">
            <el-button class="nav-btn" plain circle>
              <el-icon><Platform /></el-icon>
            </el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item
                  v-for="item in locales"
                  :key="item.value"
                  :command="item.value"
                >
                  <el-icon v-if="item.value === currentLocale"
                    ><Check
                  /></el-icon>
                  <span v-else class="dropdown-icon-placeholder" />
                  {{ item.label }}
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </el-tooltip>
      </li>
      <li>
        <el-tooltip
          :content="
            theme === 'light'
              ? $t('settings.navbar.theme.toDark')
              : $t('settings.navbar.theme.toLight')
          "
        >
          <el-button class="nav-btn" plain circle @click="handleToggleTheme">
            <el-icon><Moon v-if="theme === 'dark'" /><Sunny v-else /></el-icon>
          </el-button>
        </el-tooltip>
      </li>
      <li>
        <el-tooltip
          :content="
            isFullscreen
              ? $t('settings.navbar.screen.toExit')
              : $t('settings.navbar.screen.toFull')
          "
        >
          <el-button class="nav-btn" plain circle @click="toggleFullScreen">
            <el-icon>
              <ScaleToOriginal v-if="isFullscreen" />
              <FullScreen v-else />
            </el-icon>
          </el-button>
        </el-tooltip>
      </li>
      <li>
        <el-tooltip :content="$t('settings.title')">
          <el-button class="nav-btn" plain circle @click="setVisible">
            <el-icon><Setting /></el-icon>
          </el-button>
        </el-tooltip>
      </li>
      <li>
        <el-dropdown trigger="click" @command="handleUserCommand">
          <el-avatar
            :size="32"
            :style="{ marginRight: '8px', cursor: 'pointer' }"
          >
            <img alt="avatar" :src="avatar" />
          </el-avatar>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="settings">
                <el-icon><Setting /></el-icon>
                <span>{{ $t('navbar.userSettings') }}</span>
              </el-dropdown-item>
              <el-dropdown-item command="logout">
                <el-icon><SwitchButton /></el-icon>
                <span>{{ $t('navbar.logout') }}</span>
              </el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </li>
    </ul>
  </div>
</template>

<script lang="ts" setup>
  import { computed, inject } from 'vue';
  import { useDark, useToggle, useFullscreen } from '@vueuse/core';
  import { useRouter } from 'vue-router';
  import {
    Check,
    FullScreen,
    Menu,
    Moon,
    Platform,
    ScaleToOriginal,
    Search,
    Setting,
    Sunny,
    SwitchButton,
  } from '@element-plus/icons-vue';
  import { useAppStore, useBrandStore, useUserStore } from '@/store';
  import { LOCALE_OPTIONS } from '@/locale';
  import useLocale from '@/hooks/locale';
  import useUser from '@/hooks/user';
  import MenuComponent from '@/components/menu/index.vue';

  const appStore = useAppStore();
  const brandStore = useBrandStore();
  const userStore = useUserStore();
  const router = useRouter();
  const { logout } = useUser();
  const { changeLocale, currentLocale } = useLocale();
  const { isFullscreen, toggle: toggleFullScreen } = useFullscreen();
  const locales = [...LOCALE_OPTIONS];
  const avatar = computed(() => {
    return userStore.avatar;
  });
  const theme = computed(() => {
    return appStore.theme;
  });
  const topMenu = computed(() => appStore.topMenu && appStore.menu);
  const isDark = useDark({
    selector: 'html',
    attribute: 'class',
    valueDark: 'dark',
    valueLight: '',
    storageKey: 'element-theme',
    onChanged(dark: boolean) {
      // Keep the application store and Element Plus theme class in sync.
      appStore.toggleTheme(dark);
    },
  });
  const toggleTheme = useToggle(isDark);
  const handleToggleTheme = () => {
    toggleTheme();
  };
  const setVisible = () => {
    appStore.updateSettings({ globalSettings: true });
  };
  const handleLocaleChange = (value: string) => {
    changeLocale(value);
  };
  const handleUserCommand = (command: string) => {
    if (command === 'settings') {
      router.push({ name: 'Setting' });
      return;
    }
    if (command === 'logout') {
      logout();
    }
  };
  const toggleDrawerMenu = inject('toggleDrawerMenu') as () => void;
</script>

<style scoped lang="less">
  .navbar {
    display: flex;
    justify-content: space-between;
    height: 100%;
    background-color: var(--el-bg-color);
    border-bottom: 1px solid var(--el-border-color);
  }

  .left-side {
    display: flex;
    align-items: center;
    padding-left: 20px;
  }

  .left-content {
    display: inline-flex;
  }

  .brand-title {
    margin: 0;
    color: var(--el-text-color-primary);
    font-size: 18px;
    font-weight: 600;
  }

  .mobile-menu-button {
    font-size: 22px;
    cursor: pointer;
  }

  .center-side {
    flex: 1;
  }

  .right-side {
    display: flex;
    padding-right: 20px;
    list-style: none;
    li {
      display: flex;
      align-items: center;
      padding: 0 10px;
    }

    .nav-btn {
      border-color: var(--el-border-color);
      color: var(--el-text-color-regular);
      font-size: 16px;
    }

    .dropdown-icon-placeholder {
      display: inline-block;
      width: 14px;
      height: 14px;
    }
  }
</style>
