import db from './db';

// ────────────────────────────────────────────────────────────────────────────
// Simple XOR-based obfuscation for stored session cookies.
// Not military-grade, but prevents plain-text exposure in the DB file.
// ────────────────────────────────────────────────────────────────────────────
const OBF_KEY = (process.env.TELEGRAM_BOT_TOKEN || 'llbm_key').slice(0, 16).padEnd(16, '0');

export function obfuscate(plain: string): string {
  const buf = Buffer.from(plain, 'utf8');
  const key = Buffer.from(OBF_KEY, 'utf8');
  for (let i = 0; i < buf.length; i++) {
    buf[i] ^= key[i % key.length];
  }
  return buf.toString('base64');
}

export function deobfuscate(encoded: string): string {
  const buf = Buffer.from(encoded, 'base64');
  const key = Buffer.from(OBF_KEY, 'utf8');
  for (let i = 0; i < buf.length; i++) {
    buf[i] ^= key[i % key.length];
  }
  return buf.toString('utf8');
}

// ────────────────────────────────────────────────────────────────────────────
// Session Manager: reads/writes only the calling user's row
// ────────────────────────────────────────────────────────────────────────────

export interface BotUser {
  chat_id: string;
  tg_username?: string;
  tg_first_name?: string;
  ll_username: string;
  ll_session_cookie?: string;
  ll_user_id?: string;
  ll_name?: string;
  is_authenticated: boolean;
  authenticated_at?: string;
  quilox_auto_pick?: number;
  quilox_total_picked?: number;
}

/**
 * Returns the authenticated user record for the given Telegram chat_id,
 * or null if the user is not logged in.
 */
export function getUser(chatId: string | number): BotUser | null {
  const row = db.prepare(`
    SELECT chat_id, tg_username, tg_first_name,
           ll_username, ll_session_cookie, ll_user_id, ll_name,
           is_authenticated, authenticated_at, quilox_auto_pick, quilox_total_picked
    FROM bot_users
    WHERE chat_id = ? AND is_authenticated = 1 AND ll_session_cookie IS NOT NULL
  `).get(String(chatId)) as any;

  if (!row) return null;

  return {
    chat_id: row.chat_id,
    tg_username: row.tg_username || undefined,
    tg_first_name: row.tg_first_name || undefined,
    ll_username: row.ll_username,
    ll_session_cookie: deobfuscate(row.ll_session_cookie),
    ll_user_id: row.ll_user_id || undefined,
    ll_name: row.ll_name || undefined,
    is_authenticated: Boolean(row.is_authenticated),
    authenticated_at: row.authenticated_at || undefined,
    quilox_auto_pick: Number(row.quilox_auto_pick || 0),
    quilox_total_picked: Number(row.quilox_total_picked || 0),
  };
}

/**
 * Saves or updates the user's LagosLife session after a successful login.
 */
export function saveSession(
  chatId: string | number,
  tgUsername: string | undefined,
  tgFirstName: string | undefined,
  llUsername: string,
  sessionCookie: string,
  llUserId: string,
  llName: string
): void {
  const encoded = obfuscate(sessionCookie);
  db.prepare(`
    INSERT INTO bot_users (
      chat_id, tg_username, tg_first_name,
      ll_username, ll_session_cookie, ll_user_id, ll_name,
      is_authenticated, authenticated_at, updated_at
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ON CONFLICT(chat_id) DO UPDATE SET
      tg_username       = excluded.tg_username,
      tg_first_name     = excluded.tg_first_name,
      ll_username       = excluded.ll_username,
      ll_session_cookie = excluded.ll_session_cookie,
      ll_user_id        = excluded.ll_user_id,
      ll_name           = excluded.ll_name,
      is_authenticated  = 1,
      authenticated_at  = CURRENT_TIMESTAMP,
      updated_at        = CURRENT_TIMESTAMP
  `).run(
    String(chatId),
    tgUsername || '',
    tgFirstName || '',
    llUsername,
    encoded,
    llUserId,
    llName
  );
}

/**
 * Update the user's Player ID & Display Name if it was previously missing or updated.
 */
export function updateUserId(chatId: string | number, llUserId: string, llName?: string): void {
  db.prepare(`
    UPDATE bot_users
    SET ll_user_id = ?,
        ll_name = COALESCE(?, ll_name),
        updated_at = CURRENT_TIMESTAMP
    WHERE chat_id = ?
  `).run(llUserId, llName || null, String(chatId));
}

/**
 * Clears the session for the given user (logout).
 */
export function clearSession(chatId: string | number): void {
  db.prepare(`
    UPDATE bot_users
    SET is_authenticated = 0, ll_session_cookie = NULL, updated_at = CURRENT_TIMESTAMP
    WHERE chat_id = ?
  `).run(String(chatId));
}

/**
 * Returns true if the given chat_id has an active authenticated session.
 */
export function isAuthenticated(chatId: string | number): boolean {
  return getUser(chatId) !== null;
}
