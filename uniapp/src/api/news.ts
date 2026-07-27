import { http } from '@/utils/request'
import type { Article } from './index'

export type { Article }

export interface ArticleCate {
  id: number
  name: string
  image: string
  sort: number
}

export interface ArticleDetail extends Article {
  content: string
  collect: boolean
}

export interface ArticleListResult {
  lists: Article[]
  count: number
  page_no: number
  page_size: number
}

export interface ArticleListParams {
  cate_id?: number
  keyword?: string
  page_no?: number
  page_size?: number
}

export interface CollectListResult {
  lists: Array<{ id: number; article_id: number; create_time: string } & Article>
  count: number
  page_no: number
  page_size: number
}

/** GET api/article/cate */
export function getArticleCate() {
  return http.get<ArticleCate[]>('api/article/cate', undefined, false)
}

/** GET api/article/lists */
export function getArticleLists(params: ArticleListParams = {}) {
  return http.get<ArticleListResult>('api/article/lists', params as Record<string, unknown>, false)
}

/** GET api/article/detail?id=xxx */
export function getArticleDetail(id: number) {
  return http.get<ArticleDetail>('api/article/detail', { id }, false)
}

/** POST api/article/addCollect */
export function addCollect(article_id: number) {
  return http.post('api/article/addCollect', { article_id })
}

/** POST api/article/cancelCollect */
export function cancelCollect(article_id: number) {
  return http.post('api/article/cancelCollect', { article_id })
}

/** GET api/article/collect */
export function getCollectLists(params: { page_no?: number; page_size?: number } = {}) {
  return http.get<CollectListResult>('api/article/collect', params as Record<string, unknown>)
}

/** GET api/search/hotLists */
export function getHotSearch() {
  return http.get<{ status: number; data: Array<{ id: number; name: string }> }>(
    'api/search/hotLists',
    undefined,
    false
  )
}
