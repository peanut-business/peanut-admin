import axios from 'axios';

// ─── 文章分类 ──────────────────────────────────────────────────────────────
export interface ArticleCateRecord {
  id: number;
  name: string;
  sort: number;
  is_show: number;
  article_count?: number;
  create_time?: number;
}

export interface ArticleCateOption {
  id: number;
  name: string;
}

export function getArticleCateList() {
  return axios.get<ArticleCateRecord[]>('/api/admin/article/cate/lists');
}

export function getArticleCateAll() {
  return axios.get<ArticleCateOption[]>('/api/admin/article/cate/all');
}

export function getArticleCateDetail(id: number) {
  return axios.get<ArticleCateRecord>('/api/admin/article/cate/detail', {
    params: { id },
  });
}

export function addArticleCate(data: Partial<ArticleCateRecord>) {
  return axios.post('/api/admin/article/cate/add', data);
}

export function editArticleCate(data: Partial<ArticleCateRecord>) {
  return axios.post('/api/admin/article/cate/edit', data);
}

export function deleteArticleCate(id: number) {
  return axios.post('/api/admin/article/cate/delete', { id });
}

export function updateArticleCateStatus(id: number, isShow: number) {
  return axios.post('/api/admin/article/cate/status', { id, is_show: isShow });
}

// ─── 文章 ─────────────────────────────────────────────────────────────────
export interface ArticleRecord {
  id: number;
  cate_id: number;
  cate_name?: string;
  title: string;
  intro: string;
  image: string;
  author: string;
  content?: string;
  click_num: number;
  sort: number;
  is_show: number;
  create_time?: number;
}

export interface ArticleListParams {
  title?: string;
  cate_id?: number | string;
  is_show?: number | string;
  pageNo?: number;
  pageSize?: number;
}

export interface ArticleListRes {
  lists: ArticleRecord[];
  count: number;
  pageNo: number;
  pageSize: number;
}

export function getArticleList(params: ArticleListParams) {
  return axios.get<ArticleListRes>('/api/admin/article/lists', { params });
}

export function getArticleDetail(id: number) {
  return axios.get<ArticleRecord>('/api/admin/article/detail', {
    params: { id },
  });
}

export function addArticle(data: Partial<ArticleRecord>) {
  return axios.post('/api/admin/article/add', data);
}

export function editArticle(data: Partial<ArticleRecord>) {
  return axios.post('/api/admin/article/edit', data);
}

export function deleteArticle(id: number) {
  return axios.post('/api/admin/article/delete', { id });
}

export function updateArticleStatus(id: number, isShow: number) {
  return axios.post('/api/admin/article/status', { id, is_show: isShow });
}
