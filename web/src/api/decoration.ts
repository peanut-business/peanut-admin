import axios from 'axios';

/** The business link value object shared by all decoration surfaces. */
export type DecorationLinkType = 'shop' | 'article' | 'custom' | 'mini_program';

export interface DecorationLink {
  target_type: DecorationLinkType;
  target: string;
  query?: {
    app_id?: string;
    env_version?: 'develop' | 'trial' | 'release';
    [key: string]: unknown;
  };
}

export interface DecorationItem {
  image: string;
  name: string;
  link: DecorationLink;
  is_show?: 0 | 1;
  bg?: string;
  [key: string]: unknown;
}

export interface DecorationComponent {
  title: string;
  name: string;
  disabled?: 0 | 1;
  content: Record<string, unknown>;
  styles: Record<string, string | number>;
}

export interface DecorationPage {
  id: number;
  type: number;
  name: string;
  data: DecorationComponent[] | Record<string, unknown>;
  meta: DecorationComponent[] | Record<string, unknown>;
  update_time?: number | string;
}

export interface DecorationPageSummary {
  id: number;
  type: number;
  name: string;
  update_time?: number | string;
}

export interface DecorationSavePayload {
  id: number;
  type: number;
  data: DecorationComponent[] | Record<string, unknown>;
  meta?: DecorationComponent[] | Record<string, unknown>;
}

export interface DecorationTabbarItem {
  id?: number;
  position?: number;
  name: string;
  selected: string;
  unselected: string;
  link: DecorationLink;
  is_show: 0 | 1;
}

export interface DecorationTabbar {
  style: {
    default_color: string;
    selected_color: string;
    [key: string]: unknown;
  };
  list: DecorationTabbarItem[];
}

export function getMobileDecorationLists() {
  return axios.get<DecorationPageSummary[]>(
    '/api/admin/decoration/mobile/page/lists'
  );
}

export function getMobileDecorationDetail(id: number) {
  return axios.get<DecorationPage>('/api/admin/decoration/mobile/page/detail', {
    params: { id },
  });
}

export function saveMobileDecoration(data: DecorationSavePayload) {
  return axios.post('/api/admin/decoration/mobile/page/save', data);
}

export function getDecorationArticleOptions(limit = 50) {
  return axios.get<Array<{ id: number; title: string; image: string }>>(
    '/api/admin/decoration/mobile/article',
    { params: { limit } }
  );
}

export function getDecorationTabbar() {
  return axios.get<DecorationTabbar>('/api/admin/decoration/tabbar/detail');
}

export function saveDecorationTabbar(data: DecorationTabbar) {
  return axios.post('/api/admin/decoration/tabbar/save', data);
}

export function getPcDecorationLists() {
  return axios.get<DecorationPageSummary[]>('/api/admin/decoration/pc/page/lists');
}

export function getPcDecorationDetail(id: number) {
  return axios.get<DecorationPage>('/api/admin/decoration/pc/page/detail', {
    params: { id },
  });
}

export function savePcDecoration(data: DecorationSavePayload) {
  return axios.post('/api/admin/decoration/pc/page/save', data);
}
