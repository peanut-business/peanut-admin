import axios from 'axios';

// ─── 文章分类 ──────────────────────────────────────────────────────────────
export interface ArticleCateRecord {
  id: number;
  name: string;
  sort: number;
  is_show: number;
  article_count?: number;
  create_time?: string | number;
  update_time?: string | number | null;
  delete_time?: string | number | null;
}

export interface ArticleCateOption {
  id: number;
  name: string;
}

export interface ArticleCateListParams {
  page_no?: number;
  page_size?: number;
  page_type?: 0 | 1;
  field?: 'create_time' | 'id';
  order_by?: 'asc' | 'desc';
  export?: 1 | 2;
}

export interface ArticleCateListRes {
  lists: ArticleCateRecord[];
  count: number;
  page_no: number;
  page_size: number;
  extend: [];
}

export function getArticleCateList(params: ArticleCateListParams = {}) {
  return axios.get<ArticleCateListRes>(
    '/api/admin/article.articleCate/lists',
    { params }
  );
}

export function getArticleCateAll() {
  return axios.get<ArticleCateOption[]>('/api/admin/article.articleCate/all');
}

export function getArticleCateDetail(id: number) {
  return axios.get<ArticleCateRecord>(
    '/api/admin/article.articleCate/detail',
    { params: { id } }
  );
}

export function addArticleCate(data: Partial<ArticleCateRecord>) {
  return axios.post('/api/admin/article.articleCate/add', data);
}

export function editArticleCate(data: Partial<ArticleCateRecord>) {
  return axios.post('/api/admin/article.articleCate/edit', data);
}

export function deleteArticleCate(id: number) {
  return axios.post('/api/admin/article.articleCate/delete', { id });
}

export function updateArticleCateStatus(id: number, isShow: number) {
  return axios.post('/api/admin/article.articleCate/updateStatus', {
    id,
    is_show: isShow,
  });
}

// ─── 文章 ─────────────────────────────────────────────────────────────────
export interface ArticleRecord {
  id: number;
  cid: number;
  cate_name?: string;
  title: string;
  desc: string;
  abstract: string;
  image: string;
  author: string;
  content: string;
  click_virtual: number;
  click_actual: number;
  click: number;
  sort: number;
  is_show: number;
  create_time?: string | number;
  update_time?: string | number | null;
}

export interface ArticleListParams {
  page_no?: number;
  page_size?: number;
  page_type?: 0 | 1;
  title?: string;
  cid?: number | string;
  is_show?: number | string;
  field?: 'create_time' | 'id';
  order_by?: 'asc' | 'desc';
}

export interface ArticleListRes {
  lists: ArticleRecord[];
  count: number;
  page_no: number;
  page_size: number;
  extend: [];
}

export function getArticleList(params: ArticleListParams = {}) {
  return axios.get<ArticleListRes>('/api/admin/article.article/lists', {
    params,
  });
}

export function getArticleDetail(id: number) {
  return axios.get<ArticleRecord>('/api/admin/article.article/detail', {
    params: { id },
  });
}

export function addArticle(data: Partial<ArticleRecord>) {
  return axios.post('/api/admin/article.article/add', data);
}

export function editArticle(data: Partial<ArticleRecord>) {
  return axios.post('/api/admin/article.article/edit', data);
}

export function deleteArticle(id: number) {
  return axios.post('/api/admin/article.article/delete', { id });
}

export function updateArticleStatus(id: number, isShow: number) {
  return axios.post('/api/admin/article.article/updateStatus', {
    id,
    is_show: isShow,
  });
}
