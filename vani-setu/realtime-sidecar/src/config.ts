export interface Config {
  port: number
  databaseUrl: string
  laravelBaseUrl: string
  auditSecret: string
}

function auditSecretFromEnv(env: NodeJS.ProcessEnv): string {
  const secret = env.REALTIME_AUDIT_SECRET ?? env.ASR_INGEST_SECRET
  if (secret) {
    return secret
  }

  const runtime = env.NODE_ENV ?? env.APP_ENV
  if (runtime === 'test' || runtime === 'development' || runtime === 'local') {
    return 'local-asr-secret'
  }

  throw new Error('REALTIME_AUDIT_SECRET is required outside local/test runtimes')
}

export function configFromEnv(env: NodeJS.ProcessEnv = process.env): Config {
  return {
    port: Number(env.PORT ?? 1234),
    databaseUrl: env.DATABASE_URL ?? 'postgres://vanisetu@postgres:5432/vanisetu',
    laravelBaseUrl: (env.LARAVEL_BASE_URL ?? 'http://app').replace(/\/+$/, ''),
    auditSecret: auditSecretFromEnv(env),
  }
}
