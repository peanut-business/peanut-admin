const BASE_URL = useRuntimeConfig().public.apiBase

interface ApiResponse<T = unknown> {
  code: number
  msg: string
  data: T
}

export function useRequest() {
  const userStore = useUserStore()

  async function request<T = unknown>(
    url: string,
    options: Parameters<typeof $fetch>[1] & { auth?: boolean } = {}
  ): Promise<T> {
    const { auth = true, headers: extraHeaders, ...rest } = options

    const headers: Record<string, string> = { ...(extraHeaders as Record<string, string>) }
    if (auth && userStore.token) {
      headers['Authorization'] = `Bearer ${userStore.token}`
    }

    const resp = await $fetch<ApiResponse<T>>(`${BASE_URL}/${url}`, {
      headers,
      ...rest,
    })

    if (resp.code === 40100) {
      userStore.logout()
      await navigateTo('/login')
      throw new Error(resp.msg || '请先登录')
    }

    if (resp.code !== 20000) {
      ElMessage.error(resp.msg || '请求失败')
      throw new Error(resp.msg || '请求失败')
    }

    return resp.data
  }

  return {
    get: <T = unknown>(url: string, params?: Record<string, unknown>, auth = true) =>
      request<T>(url, { method: 'GET', params, auth }),
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    post: <T = unknown>(url: string, body?: Record<string, any> | null, auth = true) =>
      request<T>(url, { method: 'POST', body, auth }),
  }
}
