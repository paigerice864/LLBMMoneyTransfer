import db from './db';

export function runMigrations(): void {
  // ── Core user sessions ──────────────────────────────────────────────────────
  db.exec(`
    CREATE TABLE IF NOT EXISTS bot_users (
      chat_id           TEXT PRIMARY KEY,
      tg_username       TEXT,
      tg_first_name     TEXT,
      ll_username       TEXT,
      ll_session_cookie TEXT,
      ll_user_id        TEXT,
      ll_name           TEXT,
      is_authenticated  INTEGER DEFAULT 0,
      authenticated_at  DATETIME,
      updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
      created_at        DATETIME DEFAULT CURRENT_TIMESTAMP
    );
  `);

  // ── Transfer history ────────────────────────────────────────────────────────
  db.exec(`
    CREATE TABLE IF NOT EXISTS transfers (
      id                  INTEGER PRIMARY KEY AUTOINCREMENT,
      chat_id             TEXT NOT NULL,
      sender_ll_username  TEXT NOT NULL,
      recipient_username  TEXT NOT NULL,
      recipient_player_id TEXT,
      amount              INTEGER NOT NULL,
      note                TEXT,
      status              TEXT DEFAULT 'pending',
      error_reason        TEXT,
      tx_response         TEXT,
      created_at          DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_transfers_chat   ON transfers(chat_id);
    CREATE INDEX IF NOT EXISTS idx_transfers_sender ON transfers(sender_ll_username);
    CREATE INDEX IF NOT EXISTS idx_transfers_status ON transfers(status);
  `);

  // ── Pending send wizard state ────────────────────────────────────────────────
  db.exec(`
    CREATE TABLE IF NOT EXISTS pending_transfers (
      chat_id             TEXT PRIMARY KEY,
      mode                TEXT DEFAULT 'single',
      recipient_username  TEXT,
      recipient_player_id TEXT,
      amount              INTEGER,
      note                TEXT,
      step                TEXT DEFAULT 'recipient',
      data                TEXT,
      created_at          DATETIME DEFAULT CURRENT_TIMESTAMP
    );
  `);

  // Safe column additions for existing DBs
  try { db.exec(`ALTER TABLE pending_transfers ADD COLUMN mode TEXT DEFAULT 'single';`); } catch {}
  try { db.exec(`ALTER TABLE pending_transfers ADD COLUMN data TEXT;`); } catch {}

  // ── Batch jobs ───────────────────────────────────────────────────────────────
  db.exec(`
    CREATE TABLE IF NOT EXISTS batch_jobs (
      id                  TEXT PRIMARY KEY,
      chat_id             TEXT NOT NULL,
      sender_ll_username  TEXT NOT NULL,
      mode                TEXT DEFAULT 'single',
      status              TEXT DEFAULT 'running',
      total_chunks        INTEGER DEFAULT 0,
      completed_chunks    INTEGER DEFAULT 0,
      failed_chunks       INTEGER DEFAULT 0,
      total_amount        INTEGER DEFAULT 0,
      sent_amount         INTEGER DEFAULT 0,
      progress_msg_id     INTEGER,
      created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS batch_chunks (
      id                  INTEGER PRIMARY KEY AUTOINCREMENT,
      job_id              TEXT NOT NULL,
      chunk_index         INTEGER NOT NULL,
      recipient_username  TEXT NOT NULL,
      recipient_player_id TEXT,
      amount              INTEGER NOT NULL,
      note                TEXT,
      status              TEXT DEFAULT 'pending',
      error_reason        TEXT,
      sent_at             DATETIME,
      created_at          DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE INDEX IF NOT EXISTS idx_batch_jobs_chat    ON batch_jobs(chat_id);
    CREATE INDEX IF NOT EXISTS idx_batch_chunks_job   ON batch_chunks(job_id);
    CREATE INDEX IF NOT EXISTS idx_batch_chunks_status ON batch_chunks(status);
  `);

  // ── Quilox Picker additions ────────────────────────────────────────────────
  try { db.exec(`ALTER TABLE bot_users ADD COLUMN quilox_auto_pick INTEGER DEFAULT 0;`); } catch {}
  try { db.exec(`ALTER TABLE bot_users ADD COLUMN quilox_total_picked INTEGER DEFAULT 0;`); } catch {}

  db.exec(`
    CREATE TABLE IF NOT EXISTS quilox_picks (
      id                  INTEGER PRIMARY KEY AUTOINCREMENT,
      chat_id             TEXT NOT NULL,
      ll_username         TEXT NOT NULL,
      amount              INTEGER NOT NULL,
      balance_after       INTEGER,
      venue               TEXT DEFAULT 'clubEko',
      picked_at           DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_quilox_picks_chat ON quilox_picks(chat_id);
  `);

  console.log('[DB] Migrations applied successfully.');
}
