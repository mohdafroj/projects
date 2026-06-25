import { describe, expect, it } from 'vitest'
import { tokenFromRequest } from '../src/auth.js'

describe('tokenFromRequest', () => {
  it('reads bearer tokens', () => {
    const request = new Request('http://localhost/chief:1:en', {
      headers: { authorization: 'Bearer plain-token' },
    })

    expect(tokenFromRequest(request)).toBe('plain-token')
  })

  it('rejects query tokens', () => {
    expect(tokenFromRequest(new Request('http://localhost/js:2?token=query-token'))).toBeNull()
  })

  it('supports websocket protocol token shims', () => {
    const request = new Request('http://localhost/js:2', {
      headers: { 'sec-websocket-protocol': 'foo, token.protocol-token' },
    })

    expect(tokenFromRequest(request)).toBe('protocol-token')
  })
})
