import { describe, expect, it } from 'vitest'
import { assertValidDocumentName } from '../src/documentNames.js'

describe('document name validation', () => {
  it.each(['chief:12:en', 'chief:12:hi', 'js:45'])('accepts %s', name => {
    expect(() => assertValidDocumentName(name)).not.toThrow()
  })

  it.each(['chief:abc:en', 'chief:12:ta', 'js:window:1', 'director:1'])('rejects %s', name => {
    expect(() => assertValidDocumentName(name)).toThrow('Invalid realtime document name')
  })
})
