import { useUserStore } from '@/store/user'

const BASE_URL = import.meta.env.VITE_APP_BASE_URL
  || ''

interface RequestOptions {
  url: string
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE'
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  data?: Record<string, any>
  header?: Record<string, string>
  /** skip auth check — for login/register/public routes */
  auth?: boolean
}

interface ApiResponse<T = unknown> {
  code: number
  msg: string
  data: T
}

function request<T = unknown>(options: RequestOptions): Promise<T> {
  return new Promise((resolve, reject) => {
    const userStore = useUserStore()

    const header: Record<string, string> = {
      'Content-Type': 'application/json',
      ...options.header,
    }

    if (options.auth !== false && userStore.token) {
      header['Authorization'] = `Bearer ${userStore.token}`
    }

    uni.request({
      url: `${BASE_URL}/${options.url}`,
      method: options.method || 'GET',
      data: options.data,
      header,
      success(res) {
        const resp = res.data as ApiResponse<T>

        if (resp.code === 40100) {
          // token expired / not logged in → clear and redirect
          userStore.logout()
          uni.reLaunch({ url: '/pages/login/login' })
          reject(new Error(resp.msg || '请先登录'))
          return
        }

        if (resp.code !== 20000) {
          uni.showToast({ title: resp.msg || '请求失败', icon: 'none' })
          reject(new Error(resp.msg || '请求失败'))
          return
        }

        resolve(resp.data)
      },
      fail(err) {
        uni.showToast({ title: '网络错误，请稍后重试', icon: 'none' })
        reject(err)
      },
    })
  })
}

export const http = {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  get<T = unknown>(url: string, data?: Record<string, any>, auth = true) {
    return request<T>({ url, method: 'GET', data, auth })
  },
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  post<T = unknown>(url: string, data?: Record<string, any>, auth = true) {
    return request<T>({ url, method: 'POST', data, auth })
  },
}
