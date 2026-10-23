import { getUser, saveSession } from './session';

const GAME_URL = (process.env.GAME_URL || 'https://lagoslife.app').replace(/\/+$/, '');

// ────────────────────────────────────────────────────────────────────────────
// LagosLife API helpers
// All requests are made on behalf of the CALLING user's session only.
// ────────────────────────────────────────────────────────────────────────────

const COMMON_HEADERS: Record<string, string> = {
  'Accept': 'application/json',
  'Content-Type': 'application/json',
  'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
};

/**
 * Login with LagosLife credentials.
 * Automatically queries /api/auth/me to fetch player ID and display name.
 */
export async function llLogin(
  username: string,
  password: string
): Promise<{ ok: true; cookie: string; userId: string; name: string } | { ok: false; error: string }> {
  try {
    const res = await fetch(`${GAME_URL}/api/auth/login`, {
      method: 'POST',
      headers: COMMON_HEADERS,
      body: JSON.stringify({ username: username.trim(), password: password.trim() }),
    });

    // Extract session cookie
    const rawCookies: string[] =
      typeof (res.headers as any).getSetCookie === 'function'
        ? (res.headers as any).getSetCookie()
        : [res.headers.get('set-cookie') || ''];

    let cookie = '';
    for (const c of rawCookies) {
      const m = /ll_session=([^;]+)/i.exec(c);
      if (m) { cookie = `ll_session=${m[1]}`; break; }
    }

    const text = await res.text();
    let data: any = {};
    try { data = JSON.parse(text); } catch { data = { text }; }

    if (!res.ok || !data.ok) {
      return { ok: false, error: data.error || data.message || `HTTP ${res.status}` };
    }

    let userId = data.user?.id || '';
    let displayName = data.user?.name || username;

    // Fetch /api/auth/me with session cookie to get exact Player ID & Name
    try {
      const meRes = await fetch(`${GAME_URL}/api/auth/me`, {
        headers: { ...COMMON_HEADERS, Cookie: cookie }
      });
      if (meRes.ok) {
        const meData: any = await meRes.json();
        if (meData?.user?.id) userId = String(meData.user.id);
        if (meData?.user?.name) displayName = String(meData.user.name);
      }
    } catch {}

    return {
      ok: true,
      cookie,
      userId,
      name: displayName,
    };
  } catch (err: any) {
    return { ok: false, error: err.message || 'Network error connecting to lagoslife.app' };
  }
}

/**
 * Makes an authenticated HTTP request using the user's own session cookie.
 */
export async function requestAsUser(
  chatId: string | number,
  endpoint: string,
  options: RequestInit = {},
  _retryOn401 = true
): Promise<{ res: Response; data: any }> {
  const user = getUser(chatId);
  if (!user || !user.ll_session_cookie) {
    throw new Error('NOT_LOGGED_IN');
  }

  const url = `${GAME_URL}${endpoint.startsWith('/') ? endpoint : '/' + endpoint}`;
  const headers: Record<string, string> = {
    ...COMMON_HEADERS,
    'Cookie': user.ll_session_cookie,
    ...(options.headers as Record<string, string> || {}),
  };

  let res: Response | null = null;
  let lastErr: any = null;

  for (let attempt = 0; attempt < 2; attempt++) {
    try {
      const timeoutMs = endpoint.includes('/send') ? 35000 : 10000;
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), timeoutMs);
      res = await fetch(url, { ...options, headers, signal: controller.signal });
      clearTimeout(timer);
      break;
    } catch (err: any) {
      lastErr = err;
      if (attempt === 0) await delay(500);
    }
  }

  if (!res) {
    throw new Error(`Network error: ${lastErr?.message || 'fetch failed'}`);
  }

  const text = await res.text();
  let data: any = {};
  try { data = JSON.parse(text); } catch { data = { text }; }

  return { res, data };
}

/**
 * Fetch the logged-in user's profile and Player ID from /api/auth/me.
 */
export async function getProfile(chatId: string | number): Promise<{ id: string; username: string; name: string } | null> {
  try {
    const { res, data } = await requestAsUser(chatId, '/api/auth/me', { method: 'GET' });
    if (res.ok && data?.user?.id) {
      return {
        id: data.user.id,
        username: data.user.username || '',
        name: data.user.name || data.user.username || '',
      };
    }
  } catch {}
  return null;
}

/**
 * Fetch the logged-in user's live in-game cash balance from /api/save.
 */
export async function getBalance(chatId: string | number): Promise<{ balance: number; currency: string } | null> {
  try {
    const { res, data } = await requestAsUser(chatId, `/api/save?_t=${Date.now()}`, {
      method: 'GET',
      headers: { 'Cache-Control': 'no-cache, no-store' }
    });

    if (res.ok && data?.game && typeof data.game.money === 'number') {
      return { balance: Number(data.game.money), currency: 'naira' };
    }
    if (typeof data?.money === 'number') {
      return { balance: Number(data.money), currency: 'naira' };
    }
    if (data?.user?.wallet !== undefined) {
      return { balance: Number(data.user.wallet), currency: 'naira' };
    }
    return null;
  } catch {
    return null;
  }
}

// In-memory cache for resolved player profiles to prevent redundant slow API requests
const playerCache = new Map<string, { id: string; username: string; name: string }>();

/**
 * Resolve a LagosLife @username or display name to a player ID.
 * Returns null if the player cannot be found.
 */
export async function resolvePlayer(
  chatId: string | number,
  username: string
): Promise<{ id: string; username: string; name: string } | null> {
  const clean = username.trim().replace(/^@/, '');
  if (!clean) return null;

  const key = clean.toLowerCase();
  if (playerCache.has(key)) {
    return playerCache.get(key)!;
  }

  if (key === 'llbmovement') {
    const res = {
      id: '8c359c58d9094d598d3f',
      username: 'LLBmovement',
      name: 'LL Billionaire Movement',
    };
    playerCache.set(key, res);
    return res;
  }

  try {
    const { data } = await requestAsUser(
      chatId,
      `/api/players?q=${encodeURIComponent(clean)}`,
      { method: 'GET' }
    );

    const players: any[] = Array.isArray(data?.players) ? data.players : [];
    if (players.length > 0) {
      const exact = players.find(
        (p) =>
          (p.username || '').toLowerCase() === key ||
          (p.name || '').toLowerCase() === key
      );
      const hit = exact || (players.length === 1 ? players[0] : null);

      if (hit?.id) {
        const res = {
          id: hit.id,
          username: hit.username || clean,
          name: hit.name || hit.username || clean,
        };
        playerCache.set(key, res);
        if (hit.username) playerCache.set(hit.username.toLowerCase(), res);
        return res;
      }
    }

    const { data: msgData } = await requestAsUser(chatId, '/api/messages', { method: 'GET' });
    if (Array.isArray(msgData?.threads)) {
      const match = msgData.threads.find(
        (t: any) =>
          (t.username || '').toLowerCase() === key ||
          (t.name || '').toLowerCase() === key
      );
      if (match?.id) {
        const res = {
          id: match.id,
          username: match.username || clean,
          name: match.name || match.username || clean,
        };
        playerCache.set(key, res);
        if (match.username) playerCache.set(match.username.toLowerCase(), res);
        return res;
      }
    }

    return null;
  } catch {
    return null;
  }
}

/**
 * Send money FROM the calling user's account TO a recipient player.
 * The amount is in raw naira units (e.g. 1000000 = ₦1,000,000).
 * Pass `pin` if the account has a transfer PIN configured.
 */
export async function sendMoney(
  chatId: string | number,
  recipientPlayerId: string,
  amount: number,
  note: string,
  pin?: string
): Promise<{ ok: true; txId?: string } | { ok: false; error: string }> {
  try {
    const body: Record<string, any> = { to: recipientPlayerId, amount, note };
    if (pin) body.pin = pin;

    const { res, data } = await requestAsUser(chatId, '/api/send', {
      method: 'POST',
      body: JSON.stringify(body),
    });

    if (res.ok && data?.ok !== false && !data?.error) {
      return { ok: true, txId: data?.txId || data?.id || undefined };
    }

    const errMsg = data?.error || data?.message || `HTTP ${res.status}`;
    return { ok: false, error: errMsg };
  } catch (err: any) {
    return { ok: false, error: err.message || 'Send request failed' };
  }
}

function delay(ms: number): Promise<void> {
  return new Promise((r) => setTimeout(r, ms));
}
