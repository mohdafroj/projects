import type { Config } from './config.js'

export interface RealtimeUser {
  user_id: number
  roles: string[]
  permissions: string[]
}

export function tokenFromRequest(request: Request | any): string | null {
  const header = (name: string): string | undefined => {
    if (typeof request.headers?.get === 'function') {
      return request.headers.get(name) ?? undefined
    }

    const value = request.headers?.[name.toLowerCase()]
    return Array.isArray(value) ? value[0] : value
  }

  const protocol = header('sec-websocket-protocol')
  const bearer = header('authorization')?.replace(/^Bearer\s+/i, '')
  return bearer
    ?? protocol?.split(',').map(value => value.trim()).find(value => value.startsWith('token.'))?.slice(6)
    ?? null
}

export async function verifyRealtimeToken(cfg: Config, token: string, documentName: string): Promise<RealtimeUser> {
  const response = await fetch(`${cfg.laravelBaseUrl}/api/auth/verify-realtime`, {
    method: 'POST',
    headers: {
      accept: 'application/json',
      'content-type': 'application/json',
    },
    body: JSON.stringify({ token, document: documentName }),
  })

  if (!response.ok) {
    throw new Error(`Realtime authentication failed with ${response.status}`)
  }

  return await response.json() as RealtimeUser
}
