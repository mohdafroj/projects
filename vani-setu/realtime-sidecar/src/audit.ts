import type { Config } from './config.js'
import type { RealtimeUser } from './auth.js'
import { createHmac } from 'node:crypto'

export async function postRealtimeAudit(
  cfg: Config,
  action: 'realtime.doc.join' | 'realtime.doc.leave' | 'realtime.doc.snapshot',
  document: string,
  user: RealtimeUser | null,
  metadata: Record<string, unknown> = {},
): Promise<void> {
  try {
    const body = JSON.stringify({
      action,
      document,
      user_id: user?.user_id ?? null,
      metadata,
    })
    const signature = createHmac('sha256', cfg.auditSecret).update(body).digest('hex')

    await fetch(`${cfg.laravelBaseUrl}/api/realtime/audit`, {
      method: 'POST',
      headers: {
        accept: 'application/json',
        'content-type': 'application/json',
        'x-signature': signature,
      },
      body,
    })
  } catch (error) {
    console.warn('Failed to post realtime audit event', error)
  }
}
