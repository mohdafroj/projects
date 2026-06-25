import express from 'express'
import { Server } from '@hocuspocus/server'
import { configFromEnv } from './config.js'
import { tokenFromRequest, verifyRealtimeToken, type RealtimeUser } from './auth.js'
import { assertValidDocumentName } from './documentNames.js'
import { PostgresPersistence } from './postgresPersistence.js'
import { postRealtimeAudit } from './audit.js'

export async function createServer() {
  const cfg = configFromEnv()
  const persistence = new PostgresPersistence(cfg.databaseUrl)

  const hocuspocus = new Server({
    name: 'vani-setu-realtime',
    port: cfg.port,

    async onRequest(data: any) {
      if (data.request.url === '/health') {
        data.response.writeHead(200, { 'content-type': 'application/json' })
        data.response.end(JSON.stringify({ status: 'ok' }))
        throw null
      }
    },

    async onAuthenticate(data: any) {
      assertValidDocumentName(data.documentName)

      const token = data.token || tokenFromRequest(data.request)
      if (!token) {
        throw new Error('Missing realtime token')
      }

      const user = await verifyRealtimeToken(cfg, token, data.documentName)
      return {
        user,
        permissions: user.permissions,
      }
    },

    async onLoadDocument(data: any) {
      return persistence.load(data.documentName)
    },

    async onStoreDocument(data: any) {
      const user = (data.context?.user ?? null) as RealtimeUser | null
      await persistence.save(data.documentName, data.document, {
        saved_by_user_id: user?.user_id ?? null,
        permissions: data.context?.permissions ?? [],
      })
      await postRealtimeAudit(cfg, 'realtime.doc.snapshot', data.documentName, user, {
        bytes: data.document.store.clients.size,
      })
    },

    async onAwarenessUpdate(data: any) {
      const user = (data.context?.user ?? null) as RealtimeUser | null
      const added = data.added ?? []
      const removed = data.removed ?? []

      await Promise.all([
        ...added.map((clientId: number) => postRealtimeAudit(cfg, 'realtime.doc.join', data.documentName, user, { client_id: clientId })),
        ...removed.map((clientId: number) => postRealtimeAudit(cfg, 'realtime.doc.leave', data.documentName, user, { client_id: clientId })),
      ])
    },
  })

  const app = express()
  app.get('/health', (_request, response) => response.json({ status: 'ok' }))

  return { app, hocuspocus, persistence, cfg }
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const { hocuspocus, cfg } = await createServer()
  await hocuspocus.listen()
  console.log(`Realtime sidecar listening on :${cfg.port}`)
}
