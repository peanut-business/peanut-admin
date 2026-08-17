const PLATFORM_TOKEN_KEY = 'platform_token';

export const getPlatformToken = () =>
  localStorage.getItem(PLATFORM_TOKEN_KEY);

export const setPlatformToken = (token: string) =>
  localStorage.setItem(PLATFORM_TOKEN_KEY, token);

export const clearPlatformToken = () =>
  localStorage.removeItem(PLATFORM_TOKEN_KEY);

export const hasPlatformSession = () => !!getPlatformToken();
