import { Pool } from 'pg'
import * as Y from 'yjs'

export interface PersistedDocument {
  name: string
  state: Uint8Array
  context: Record<string, unknown> | null
}

export class PostgresPersistence {
  private readonly pool: Pool

  constructor(databaseUrl: string) {
    this.pool = new Pool({ connectionString: databaseUrl })
  }

  async load(name: string): Promise<Y.Doc> {
    await this.ensureTable()

    const result = await this.pool.query('select state from collaborative_docs where name = $1', [name])
    const doc = new Y.Doc()

    if (result.rowCount) {
      Y.applyUpdate(doc, new Uint8Array(result.rows[0].state))
    }

    return doc
  }

  async save(name: string, doc: Y.Doc, context: Record<string, unknown> = {}): Promise<void> {
    await this.ensureTable()

    const state = Buffer.from(Y.encodeStateAsUpdate(doc))
    await this.pool.query(
      `insert into collaborative_docs (name, state, context, last_saved_at, created_at, updated_at)
       values ($1, $2, $3, now(), now(), now())
       on conflict (name)
       do update set state = excluded.state, context = excluded.context, last_saved_at = now(), updated_at = now()`,
      [name, state, JSON.stringify(context)],
    )
  }

  async close(): Promise<void> {
    await this.pool.end()
  }

  private async ensureTable(): Promise<void> {
    await this.pool.query(`
      create table if not exists collaborative_docs (
        id bigserial primary key,
        name varchar(255) unique not null,
        state bytea not null,
        context jsonb,
        last_saved_at timestamptz,
        created_at timestamptz,
        updated_at timestamptz
      )
    `)
  }
}
