import { http } from '@/utils/request'

export type WechatOAuthScene = 'mnp' | 'oa' | 'open_pc'

export interface OAuthMember {
  id: number
  sn: string
  nickname: string
  avatar: string
  mobile: string
}

export interface OAuthResult {
  completed: boolean
  token?: string
  member: OAuthMember
  completion_ticket?: string
  expires_in?: number
  need_profile?: boolean
  need_mobile?: boolean
  return_path?: string
}

export interface OAuthBeginResult {
  authorization_url: string
  expires_in: number
}

export function beginWechatOAuth(data: { scene: 'oa' | 'open_pc'; return_path: string }) {
  return http.post<OAuthBeginResult>('api/oauth/wechat/begin', data, false)
}

export function callbackWechatOAuth(data: {
  scene: 'oa' | 'open_pc'
  code: string
  state: string
}) {
  return http.post<OAuthResult>('api/oauth/wechat/callback', data, false)
}

export function loginWechatMiniProgram(code: string) {
  return http.post<OAuthResult>('api/oauth/wechat/mini-program', { code }, false)
}

export function completeWechatOAuth(data: {
  ticket: string
  nickname?: string
  avatar?: string
  mobile?: string
  verification_code?: string
}) {
  return http.post<OAuthResult>('api/oauth/wechat/complete', data, false)
}

export function bindWechatIdentity(scene: 'mnp' | 'oa', code: string) {
  return http.post('api/oauth/wechat/bind', { scene, code })
}
