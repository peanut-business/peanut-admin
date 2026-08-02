import { http } from '@/utils/request'
import type { DecorationPage, DecorationTabbar, DecorationTheme } from '@/utils/decoration'

export interface ConfigData {
  domain: string
  website: { shop_name: string; shop_logo: string }
  login: { login_way: number[] }
  web_page: {
    status: 0 | 1
    page_status: 0 | 1
    page_url: string
  }
  tabbar: DecorationTabbar
  theme: DecorationPage & { data: DecorationTheme }
  version: string
}

export interface PolicyData {
  title: string
  content: string
}

export interface Article {
  id: number
  cate_id: number
  cate_name: string
  title: string
  image: string
  desc: string
  author: string
  click_num: number
  click?: number
  collect_num: number
  create_time: string
}

export interface IndexData {
  article: Article[]
  decorate: DecorationPage
}

/** GET api/index/config — app global config */
export function getConfig() {
  return http.get<ConfigData>('api/index/config', undefined, false)
}

/** GET api/index/policy?type=privacy|service */
export function getPolicy(type: 'privacy' | 'service') {
  return http.get<PolicyData>('api/index/policy', { type }, false)
}

/** GET api/index/index — home page data */
export function getIndexData() {
  return http.get<IndexData>('api/index/index', undefined, false)
}

/** GET api/decoration/tabbar — public visible tabbar configuration */
export function getDecorationTabbar() {
  return http.get<DecorationTabbar>('api/decoration/tabbar', undefined, false)
}
