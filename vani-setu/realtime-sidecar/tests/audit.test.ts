import { createHmac } from 'node:crypto'
import { describe, expect, it, vi } from 'vitest'
import { postRealtimeAudit } from '../src/audit.js'
import type { Config } from '../src/config.js'

describe('postRealtimeAudit', () => {
  it('posts HMAC signed realtime audit payloads', async () => {
    const fetchMock = vi.fn(async (_url: string, init: RequestInit) => {
      const body = String(init.body)
      const expected = createHmac('sha256', 'test-secret').update(body).digest('hex')
      expect((init.headers as Record<string, string>)['x-signature']).toBe(expected)
      expect(JSON.parse(body).action).toBe('realtime.doc.join')
      return new Response('{}', { status: 200 })
    })
    vi.stubGlobal('fetch', fetchMock)

    const cfg: Config = {
      port: 1234,
      databaseUrl: 'postgres://example',
      laravelBaseUrl: 'http://app',
      auditSecret: 'test-secret',
    }

    await postRealtimeAudit(cfg, 'realtime.doc.join', 'chief:1:en', {
      user_id: 7,
      roles: ['chief'],
      permissions: ['editorial.edit'],
    }, { client_id: 10 })

    expect(fetchMock).toHaveBeenCalledWith('http://app/api/realtime/audit', expect.any(Object))
    vi.unstubAllGlobals()
  })
})
