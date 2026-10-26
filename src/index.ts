import path from 'path';
import dotenv from 'dotenv';
dotenv.config();
dotenv.config({ path: path.resolve(__dirname, '../.env') });
import { Telegraf, Markup } from 'telegraf';
import { message } from 'telegraf/filters';
import { runMigrations } from './migrations';
import {
  getUser,
  saveSession,
  updateUserId,
  clearSession,
  isAuthenticated,
} from './session';
import {
  llLogin,
  resolvePlayer,
  sendMoney,
  getBalance,
  getProfile,
} from './api';
import {
  recordTransfer,
  getUserTransfers,
  getPending,
  setPending,
  clearPending,
  ParsedRecipient,
  createBatchJob,
  updateBatchJob,
  recordBatchChunk,
} from './transfers';

// ────────────────────────────────────────────────────────────────────────────
// Constants
// ────────────────────────────────────────────────────────────────────────────

const MAX_CHUNK      = 100_000_000_000; // ₦100B per single API transfer (platform limit)
const COOLDOWN_S     = 32;              // 32-second cooldown between every transfer
const BOT_TOKEN      = process.env.TELEGRAM_BOT_TOKEN || '';
const NOTIFY_CHANNEL = process.env.NOTIFY_CHANNEL || '@Heisdaemon';

// Official LLBM Movement account details
const LLBM_MOVEMENT_USERNAME = 'LLBmovement';
const LLBM_MOVEMENT_PLAYER_ID = '8c359c58d9094d598d3f';
const LLBM_MOVEMENT_NAME = 'LL Billionaire Movement';

// ────────────────────────────────────────────────────────────────────────────
// Boot
// ────────────────────────────────────────────────────────────────────────────

runMigrations();

if (!BOT_TOKEN) {
  console.error('[LLBM Bot] TELEGRAM_BOT_TOKEN not set in .env');
  process.exit(1);
}

const bot = new Telegraf(BOT_TOKEN);

// ────────────────────────────────────────────────────────────────────────────
// Per-user in-memory state
// All maps are keyed by chatId (string). Each user is 100% isolated.
// ────────────────────────────────────────────────────────────────────────────

interface LoginState {
  username: string;
  phase: 'username' | 'password';
}
const loginState = new Map<string, LoginState>();
const awaitingPin = new Set<string>();
const userPins = new Map<string, string>();

interface ActiveBatch {
  jobId:         string;
  cancel:        boolean;
  progressMsgId?: number;
}
const activeBatches = new Map<string, ActiveBatch>();
const subCache = new Map<string, number>();

// ────────────────────────────────────────────────────────────────────────────
// Channel Subscription Verification
// ────────────────────────────────────────────────────────────────────────────

async function isUserSubscribed(userId: number | string, forceRefresh = false): Promise<boolean> {
  const uid = String(userId);
  const now = Date.now();
  if (!forceRefresh) {
    const cachedTime = subCache.get(uid);
    if (cachedTime && now - cachedTime < 300000) {
      return true;
    }
  }

  try {
    const member = await bot.telegram.getChatMember(NOTIFY_CHANNEL, Number(userId));
    const valid = ['creator', 'administrator', 'member', 'restricted'].includes(member.status);
    if (valid) {
      subCache.set(uid, now);
      return true;
    }
    subCache.delete(uid);
    return false;
  } catch (err: any) {
    const msg = (err?.message || '').toLowerCase();
    if (msg.includes('user not found') || msg.includes('participant_id_invalid') || msg.includes('left')) {
      subCache.delete(uid);
      return false;
    }
    console.warn(`[SubCheck Error for ${userId}]:`, err.message);
    return false;
  }
}

function subscriptionPrompt(customNote?: string) {
  const channelHandle = NOTIFY_CHANNEL.replace(/^@/, '');
  const channelLink = `https://t.me/${channelHandle}`;
  const text =
    `📢 *Channel Subscription Required*\n` +
    `━━━━━━━━━━━━━━━━━━━━━━━━━\n\n` +
    (customNote ? `${customNote}\n\n` : '') +
    `To use *LLBM Money Bot*, you must subscribe to our official notification channel:\n👉 *${NOTIFY_CHANNEL}*\n\n` +
    `🔔 *Why?*\n` +
    `All live transfers, batch alerts, and activity across the network are publicly broadcast there in real-time!\n\n` +
    `👇 *Quick Steps:*\n` +
    `1. Click *📢 Join Channel* below\n` +
    `2. Click *✅ Verify Subscription* to unlock the bot`;

  const keyboard = Markup.inlineKeyboard([
    [Markup.button.url(`📢 Join ${NOTIFY_CHANNEL}`, channelLink)],
    [Markup.button.callback('✅ Verify Subscription', 'check_sub')],
  ]);

  return { text, keyboard };
}

// ────────────────────────────────────────────────────────────────────────────
// Formatting helpers
// ────────────────────────────────────────────────────────────────────────────

function escMd(text: any): string {
  if (text === null || text === undefined) return '';
  return String(text).replace(/([_*`\[\]\\])/g, '\\$1');
}

function fmtShort(n: number): string {
  if (n >= 1_000_000_000) return `₦${+(n / 1_000_000_000).toFixed(2)}B`;
  if (n >= 1_000_000)     return `₦${+(n / 1_000_000).toFixed(1)}M`;
  if (n >= 1_000)         return `₦${+(n / 1_000).toFixed(1)}K`;
  return `₦${n.toLocaleString('en-NG')}`;
}

function fmtFull(n: number): string {
  return `₦${n.toLocaleString('en-NG')}`;
}

function progressBar(done: number, total: number, width = 12): string {
  const filled = Math.min(Math.round((done / total) * width), width);
  return '▓'.repeat(filled) + '░'.repeat(width - filled);
}

function parseAmount(raw: string): number | null {
  const clean = raw.trim().replace(/,/g, '').toLowerCase();
  const m = /^([\d.]+)\s*([kmb]?)$/.exec(clean);
  if (!m) return null;
  const base = parseFloat(m[1]);
  if (isNaN(base) || base <= 0) return null;
  const mult: Record<string, number> = { k: 1e3, m: 1e6, b: 1e9 };
  return Math.round(base * (mult[m[2]] || 1));
}

function chunkAmounts(total: number): number[] {
  const chunks: number[] = [];
  let rem = total;
  while (rem > 0) { chunks.push(Math.min(rem, MAX_CHUNK)); rem -= MAX_CHUNK; }
  return chunks;
}

function estTime(totalChunks: number): string {
  const secs = Math.max(0, (totalChunks - 1) * COOLDOWN_S);
  if (secs < 60) return `~${secs}s`;
  return `~${Math.round(secs / 60)}min`;
}

function parseMultiInput(raw: string): {
  parsed: Array<{ username: string; amount: number }>;
  errors: string[];
} {
  const lines  = raw.split(/[\n;]+/).map(l => l.trim()).filter(Boolean);
  const parsed: Array<{ username: string; amount: number }> = [];
  const errors: string[] = [];

  for (const line of lines) {
    const parts = line.replace(/,/g, ' ').trim().split(/\s+/).filter(Boolean);
    if (parts.length < 2) { errors.push(`❌ "${line}" — need username AND amount`); continue; }
    const username = parts[0].replace(/^@/, '').trim();
    const amount   = parseAmount(parts[1]);
    if (!username) { errors.push(`❌ "${line}" — invalid username`); continue; }
    if (!amount)   { errors.push(`❌ "${line}" — invalid amount "${parts[1]}"`); continue; }
    parsed.push({ username, amount });
  }
  return { parsed, errors };
}

// ────────────────────────────────────────────────────────────────────────────
// Public Notification helper (Never displays Telegram IDs or Handles!)
// ────────────────────────────────────────────────────────────────────────────

async function notify(text: string): Promise<void> {
  try {
    await bot.telegram.sendMessage(NOTIFY_CHANNEL, text, { parse_mode: 'Markdown' });
  } catch (err: any) {
    console.warn('[Notify]', err.message);
    try {
      const clean = text.replace(/[*_`\[\]]/g, '');
      await bot.telegram.sendMessage(NOTIFY_CHANNEL, clean);
    } catch {}
  }
}

async function wipeSensitive(ctx: any): Promise<void> {
  try { await ctx.deleteMessage(); } catch {}
}

// ────────────────────────────────────────────────────────────────────────────
// Menus
// ────────────────────────────────────────────────────────────────────────────

function mainMenu(loggedIn: boolean) {
  if (loggedIn) {
    return Markup.keyboard([
      ['💸 Send Money',        '💰 My Balance'],
      ['💎 Support LLBM',      '📋 Transfer History'],
      ['👤 My Account',        '🔐 Set/Update PIN'],
      ['🔓 Logout'],
    ]).resize();
  }
  return Markup.keyboard([['🔑 Login']]).resize();
}

// ────────────────────────────────────────────────────────────────────────────
// Batch progress message
// ────────────────────────────────────────────────────────────────────────────

interface ProgressState {
  senderUsername:   string;
  totalChunks:      number;
  doneChunks:       number;
  successChunks:    number;
  failedChunks:     number;
  totalAmount:      number;
  sentAmount:       number;
  currentRecipient?: string;
  currentAmount?:    number;
  countdownSec?:     number;
  phase:             'sending' | 'cooldown' | 'done' | 'cancelled';
  totalRecipients?:  number;
}

function fmtProgress(s: ProgressState): string {
  const bar  = progressBar(s.doneChunks, s.totalChunks);
  const pct  = s.totalChunks > 0 ? Math.round((s.doneChunks / s.totalChunks) * 100) : 0;
  const sep  = '━━━━━━━━━━━━━━━━━━━━━━━━━';

  let statusLine = '';
  if (s.phase === 'sending') {
    statusLine = `🔄 *Chunk ${s.doneChunks + 1}/${s.totalChunks}* → @${escMd(s.currentRecipient || '')}  ${fmtShort(s.currentAmount || 0)}`;
  } else if (s.phase === 'cooldown') {
    statusLine = `⏳ *Cooldown:* ${s.countdownSec}s until next chunk…`;
  } else if (s.phase === 'done') {
    statusLine = s.failedChunks === 0 ? `🎉 *All chunks delivered successfully!*` : `⚠️ *Done — ${s.failedChunks} chunk(s) failed*`;
  } else if (s.phase === 'cancelled') {
    statusLine = `🚫 *Batch cancelled*`;
  }

  return [
    `💸 *Batch Transfer in Progress*`,
    sep,
    `👤 *From:* @${escMd(s.senderUsername)}`,
    s.totalRecipients ? `👥 *Recipients:* ${s.totalRecipients}` : '',
    `📦 *Total Chunks:* ${s.totalChunks}  ·  Max ${fmtShort(MAX_CHUNK)} each`,
    sep,
    `${bar}  ${pct}%  (${s.doneChunks}/${s.totalChunks})`,
    sep,
    `✅ *Sent so far:*  ${fmtShort(s.sentAmount)}`,
    `📊 *Remaining:*    ${fmtShort(Math.max(0, s.totalAmount - s.sentAmount))}`,
    `✔️ OK: ${s.successChunks}    ❌ Failed: ${s.failedChunks}`,
    sep,
    statusLine,
  ].filter(l => l !== '').join('\n');
}

// ────────────────────────────────────────────────────────────────────────────
// Core async batch executor
// ────────────────────────────────────────────────────────────────────────────

interface Chunk {
  recipientUsername: string;
  recipientPlayerId: string;
  amount:            number;
  note:              string;
}

function delay(ms: number): Promise<void> {
  return new Promise(r => setTimeout(r, ms));
}

async function safeSend(chatId: string | number, text: string, extra: any = {}): Promise<any> {
  try {
    return await bot.telegram.sendMessage(String(chatId), text, { parse_mode: 'Markdown', ...extra });
  } catch (err: any) {
    if (err?.message?.includes('can\'t parse entities') || err?.message?.includes('400')) {
      const clean = text.replace(/[*_`\[\]\\]/g, '');
      return await bot.telegram.sendMessage(String(chatId), clean, extra);
    }
    throw err;
  }
}

async function safeEdit(chatId: string | number, msgId: number, text: string, extra?: any): Promise<void> {
  try {
    await bot.telegram.editMessageText(String(chatId), msgId, undefined, text, {
      parse_mode: 'Markdown', ...extra
    });
  } catch (err: any) {
    try {
      const clean = text.replace(/[*_`\[\]\\]/g, '');
      await bot.telegram.editMessageText(String(chatId), msgId, undefined, clean, extra);
    } catch {}
  }
}

async function runBatch(
  chatId:        string,
  jobId:         string,
  chunks:        Chunk[],
  progressMsgId: number,
  state:         ProgressState
): Promise<void> {
  const pin = userPins.get(chatId);
  const cancelBtn = Markup.inlineKeyboard([[Markup.button.callback('🚫 Cancel Batch', 'cancel_batch')]]);
  const failedList: Array<{ username: string; amount: number; reason: string }> = [];

  for (let i = 0; i < chunks.length; i++) {
    const ref = activeBatches.get(chatId);
    if (!ref || ref.cancel) {
      state.phase = 'cancelled';
      await safeEdit(chatId, progressMsgId, fmtProgress(state));
      updateBatchJob(jobId, { status: 'cancelled', completedChunks: state.doneChunks, failedChunks: state.failedChunks, sentAmount: state.sentAmount });
      activeBatches.delete(chatId);
      await bot.telegram.sendMessage(chatId, '🚫 Batch cancelled.', mainMenu(true));
      return;
    }

    const chunk = chunks[i];

    state.phase            = 'sending';
    state.currentRecipient = chunk.recipientUsername;
    state.currentAmount    = chunk.amount;
    await safeEdit(chatId, progressMsgId, fmtProgress(state), cancelBtn);

    const result = await sendMoney(chatId, chunk.recipientPlayerId, chunk.amount, chunk.note, pin);

    if (result.ok) {
      state.successChunks++;
      state.sentAmount += chunk.amount;
      recordBatchChunk({ jobId, chunkIndex: i, recipientUsername: chunk.recipientUsername, recipientPlayerId: chunk.recipientPlayerId, amount: chunk.amount, note: chunk.note, status: 'success' });
      recordTransfer({ chat_id: chatId, sender_ll_username: state.senderUsername, recipient_username: chunk.recipientUsername, recipient_player_id: chunk.recipientPlayerId, amount: chunk.amount, note: chunk.note, status: 'success', tx_response: result.txId });
    } else {
      state.failedChunks++;
      failedList.push({
        username: chunk.recipientUsername,
        amount: chunk.amount,
        reason: result.error || 'Network error / API rejected',
      });
      recordBatchChunk({ jobId, chunkIndex: i, recipientUsername: chunk.recipientUsername, recipientPlayerId: chunk.recipientPlayerId, amount: chunk.amount, note: chunk.note, status: 'failed', errorReason: result.error });
      recordTransfer({ chat_id: chatId, sender_ll_username: state.senderUsername, recipient_username: chunk.recipientUsername, recipient_player_id: chunk.recipientPlayerId, amount: chunk.amount, note: chunk.note, status: 'failed', error_reason: result.error });
    }
    state.doneChunks++;

    // 32-second cooldown countdown between EVERY chunk
    if (i < chunks.length - 1) {
      let remaining = COOLDOWN_S;
      state.phase = 'cooldown';

      while (remaining > 0) {
        const batchRef = activeBatches.get(chatId);
        if (!batchRef || batchRef.cancel) {
          state.phase = 'cancelled';
          await safeEdit(chatId, progressMsgId, fmtProgress(state));
          updateBatchJob(jobId, { status: 'cancelled', completedChunks: state.doneChunks, failedChunks: state.failedChunks, sentAmount: state.sentAmount });
          activeBatches.delete(chatId);
          await bot.telegram.sendMessage(chatId, '🚫 Batch cancelled during cooldown.', mainMenu(true));
          return;
        }
        state.countdownSec = remaining;
        await safeEdit(chatId, progressMsgId, fmtProgress(state), cancelBtn);
        const step = Math.min(5, remaining);
        await delay(step * 1000);
        remaining -= step;
      }
    }
  }

  state.phase = 'done';
  await safeEdit(chatId, progressMsgId, fmtProgress(state));
  updateBatchJob(jobId, { status: 'completed', completedChunks: state.doneChunks, failedChunks: state.failedChunks, sentAmount: state.sentAmount });
  activeBatches.delete(chatId);

  const isLLBM = chunks[0]?.recipientUsername?.toLowerCase() === LLBM_MOVEMENT_USERNAME.toLowerCase();

  let failedSection = '';
  if (failedList.length > 0) {
    failedSection =
      `\n\n❌ *Failed Transfers (${failedList.length}):*\n` +
      failedList.map((f, idx) =>
        `${idx + 1}\\. @${escMd(f.username)} — *${fmtShort(f.amount)}*\n   ⚠️ _Reason: ${escMd(f.reason)}_`
      ).join('\n');
  }

  const summary =
    `${state.failedChunks === 0 ? '✅' : '⚠️'} *Batch Complete!*\n` +
    `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
    `👤 *From:* @${escMd(state.senderUsername)}\n` +
    (isLLBM ? `🎯 *Beneficiary:* LL Billionaire Movement (@${LLBM_MOVEMENT_USERNAME})\n` : '') +
    `📦 *Chunks:* ${state.totalChunks}\n` +
    `💵 *Total Sent:* ${fmtFull(state.sentAmount)}\n` +
    `✔️ OK: ${state.successChunks}   ❌ Failed: ${state.failedChunks}` +
    failedSection;

  await safeSend(chatId, summary, mainMenu(true));

  let channelFailedSection = '';
  if (failedList.length > 0) {
    channelFailedSection = '\n⚠️ *Failed:* ' + failedList.map(f => `@${escMd(f.username)} (${fmtShort(f.amount)})`).join(', ');
  }

  if (isLLBM) {
    await notify(
      `💎 *LLBM MOVEMENT CONTRIBUTION!*\n` +
      `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
      `👤 *Supporter:* @${escMd(state.senderUsername)}\n` +
      `🎯 *Beneficiary:* @${LLBM_MOVEMENT_USERNAME} (${LLBM_MOVEMENT_NAME})\n` +
      `💵 *Amount:* ${fmtFull(state.sentAmount)} _(${fmtShort(state.sentAmount)})_\n` +
      `📦 *Chunks:* ${state.totalChunks} (Delivered: ${state.successChunks})\n` +
      `❤️ *Thank you for empowering the Movement!* 🚀\n` +
      `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
      `🤖 *LLBM Money Bot*`
    );
  } else {
    await notify(
      `📦 *BATCH TRANSFER COMPLETED*\n` +
      `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
      `👤 *Sender:* @${escMd(state.senderUsername)}\n` +
      `📦 *Total Chunks:* ${state.totalChunks}\n` +
      `💵 *Total Sent:* ${fmtFull(state.sentAmount)} _(${fmtShort(state.sentAmount)})_\n` +
      `✔️ *Successful:* ${state.successChunks}   ❌ *Failed:* ${state.failedChunks}` +
      channelFailedSection + `\n` +
      `⚡ *Status:* ${state.failedChunks === 0 ? 'All Delivered ✅' : 'Completed with issues ⚠️'}\n` +
      `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
      `🤖 *LLBM Money Bot*`
    );
  }
}

async function startBatch(ctx: any, chunks: Chunk[], totalAmount: number, totalRecipients?: number) {
  const chatId = String(ctx.chat?.id || ctx.chatId);
  const u      = getUser(chatId)!;

  const progressMsg = await safeSend(
    chatId,
    fmtProgress({
      senderUsername:  u.ll_username,
      totalChunks:     chunks.length,
      doneChunks:      0,
      successChunks:   0,
      failedChunks:    0,
      totalAmount,
      sentAmount:      0,
      phase:           'sending',
      currentRecipient: chunks[0]?.recipientUsername,
      currentAmount:    chunks[0]?.amount,
      totalRecipients,
    }),
    Markup.inlineKeyboard([[Markup.button.callback('🚫 Cancel Batch', 'cancel_batch')]])
  );

  const jobId = createBatchJob({
    chatId,
    senderUsername: u.ll_username,
    mode:           totalRecipients && totalRecipients > 1 ? 'multi' : 'single',
    totalChunks:    chunks.length,
    totalAmount,
    progressMsgId:  progressMsg.message_id,
  });

  activeBatches.set(chatId, { jobId, cancel: false, progressMsgId: progressMsg.message_id });
  clearPending(chatId);

  runBatch(chatId, jobId, chunks, progressMsg.message_id, {
    senderUsername:  u.ll_username,
    totalChunks:     chunks.length,
    doneChunks:      0,
    successChunks:   0,
    failedChunks:    0,
    totalAmount,
    sentAmount:      0,
    phase:           'sending',
    totalRecipients,
  });
}

// ────────────────────────────────────────────────────────────────────────────
// MANDATORY SUBSCRIPTION GATEWAY MIDDLEWARE
// ────────────────────────────────────────────────────────────────────────────

bot.use(async (ctx, next) => {
  if (ctx.callbackQuery && 'data' in ctx.callbackQuery && ctx.callbackQuery.data === 'check_sub') {
    return next();
  }

  if (ctx.chat?.type === 'private' && ctx.from) {
    const isSubbed = await isUserSubscribed(ctx.from.id);
    if (!isSubbed) {
      const prompt = subscriptionPrompt();
      await ctx.reply(prompt.text, { parse_mode: 'Markdown', ...prompt.keyboard });
      return;
    }
  }

  return next();
});

bot.action('check_sub', async (ctx) => {
  const userId = ctx.from?.id;
  if (!userId) return;

  await ctx.answerCbQuery('Checking channel membership…');
  const isSubbed = await isUserSubscribed(userId, true);

  if (isSubbed) {
    await ctx.deleteMessage().catch(() => {});
    await ctx.reply(
      `🎉 *Channel Verified!*\n\nThank you for joining ${NOTIFY_CHANNEL}. The bot is unlocked for your account.`,
      { parse_mode: 'Markdown' }
    );

    const chatId = String(ctx.chat!.id);
    const loggedIn = isAuthenticated(chatId);
    if (loggedIn) {
      const u = getUser(chatId)!;
      const balResult = await getBalance(chatId);
      const balLine   = balResult ? `\n💰 Balance: *${fmtFull(balResult.balance)}* _(${fmtShort(balResult.balance)})_` : '';
      await ctx.reply(
        `👤 Logged in as *@${u.ll_username}*${balLine}\n\nUse the menu below to send money:`,
        { parse_mode: 'Markdown', ...mainMenu(true) }
      );
    } else {
      await ctx.reply(
        `👇 Tap *Login* to connect your LagosLife account and start sending money:`,
        { parse_mode: 'Markdown', ...mainMenu(false) }
      );
    }
  } else {
    const prompt = subscriptionPrompt('❌ *We could not find your subscription in the channel.*');
    await ctx.reply(prompt.text, { parse_mode: 'Markdown', ...prompt.keyboard });
  }
});

// ────────────────────────────────────────────────────────────────────────────
// /start
// ────────────────────────────────────────────────────────────────────────────

bot.start(async (ctx) => {
  const chatId   = String(ctx.chat.id);
  const loggedIn = isAuthenticated(chatId);

  if (loggedIn) {
    const u = getUser(chatId)!;
    
    // Auto-fetch Player ID if missing
    if (!u.ll_user_id || u.ll_user_id === 'n/a') {
      const prof = await getProfile(chatId);
      if (prof?.id) {
        updateUserId(chatId, prof.id, prof.name);
        u.ll_user_id = prof.id;
        u.ll_name = prof.name;
      }
    }

    const balResult = await getBalance(chatId);
    const balLine   = balResult ? `\n💰 Balance: *${fmtFull(balResult.balance)}* _(${fmtShort(balResult.balance)})_` : '';
    const pinLine   = userPins.has(chatId) ? `\n🔐 Transfer PIN: *Set ✅*` : `\n🔐 Transfer PIN: *Not set*`;

    await ctx.reply(
      `🏦 *LLBM Money Bot*\n\n` +
      `Welcome back, *${u.ll_name || u.ll_username}*! 👋\n` +
      `👤 Account: *@${u.ll_username}*\n` +
      `🆔 Player ID: \`${u.ll_user_id || 'n/a'}\`` +
      balLine + pinLine,
      { parse_mode: 'Markdown', ...mainMenu(true) }
    );
  } else {
    await ctx.reply(
      `🏦 *LLBM Money Bot*\n\n` +
      `Send LagosLife money directly from your own account.\n\n` +
      `✅ Auto-batch for amounts over *₦100B*\n` +
      `✅ *32s cooldown* handled automatically between chunks\n` +
      `✅ Multi-recipient — send to *many users at once*\n` +
      `✅ Support the *LLBM Movement* directly\n` +
      `✅ Transfer PIN support\n` +
      `✅ Multiple users, fully isolated\n\n` +
      `👇 Tap *Login* to begin.`,
      { parse_mode: 'Markdown', ...mainMenu(false) }
    );
  }
});

// ────────────────────────────────────────────────────────────────────────────
// /help
// ────────────────────────────────────────────────────────────────────────────

bot.help(async (ctx) => {
  await ctx.reply(
    `📖 *Help*\n\n` +
    `/login — Connect your LagosLife account\n` +
    `/send — Send money (single or multi-recipient)\n` +
    `/support — Send money directly to support LLBM Movement\n` +
    `/balance — Check your wallet balance\n` +
    `/history — Last 10 transfers\n` +
    `/account — Your account & Player ID details\n` +
    `/setpin — Set or update your transfer PIN\n` +
    `/clearpin — Remove your stored PIN\n` +
    `/logout — Disconnect\n\n` +
    `*Smart batch rules:*\n` +
    `• Max *₦100B* per single transfer\n` +
    `• Over ₦100B → auto-split into chunks\n` +
    `• *32s cooldown* between every chunk\n` +
    `• PIN entered once → applied to all chunks\n` +
    `• Live alerts posted to ${NOTIFY_CHANNEL}`,
    { parse_mode: 'Markdown' }
  );
});

// ────────────────────────────────────────────────────────────────────────────
// SUPPORT LLBM MOVEMENT (Direct contribution flow)
// ────────────────────────────────────────────────────────────────────────────

bot.command('support', handleSupportLLBM);
bot.hears('💎 Support LLBM', handleSupportLLBM);
bot.action('mode_support_llbm', handleSupportLLBM);

async function handleSupportLLBM(ctx: any) {
  const chatId = String(ctx.chat?.id || ctx.from?.id);
  if (!isAuthenticated(chatId)) return ctx.reply('🔒 Please /login first.', mainMenu(false));

  if (activeBatches.has(chatId)) {
    const cancelKb = Markup.inlineKeyboard([[Markup.button.callback('🚫 Cancel Batch', 'cancel_batch')]]);
    return ctx.reply('⚠️ A batch transfer is currently running. Please wait for it to finish or cancel it first.', cancelKb);
  }

  clearPending(chatId);
  setPending(chatId, {
    mode: 'single',
    step: 'amount',
    recipient_username: LLBM_MOVEMENT_USERNAME,
    recipient_player_id: LLBM_MOVEMENT_PLAYER_ID,
    note: 'Support LLBM Movement 💎',
  });

  const promptText =
    `💎 *Support LLBM Movement*\n` +
    `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
    `🎯 *Beneficiary:* *${LLBM_MOVEMENT_NAME}* (@${LLBM_MOVEMENT_USERNAME})\n` +
    `🆔 *Player ID:* \`${LLBM_MOVEMENT_PLAYER_ID}\`\n\n` +
    `How much funds would you like to contribute?\n\n` +
    `💡 _Examples:_ \`100K\`, \`5M\`, \`10B\`, \`50B\`, \`100B\`\n` +
    `📦 _Amounts over ₦100B will automatically be sent in batches of ₦100B with the standard 32s cooldown._`;

  if (ctx.callbackQuery) {
    await ctx.answerCbQuery();
    await ctx.editMessageText(promptText, { parse_mode: 'Markdown' });
  } else {
    await ctx.reply(promptText, { parse_mode: 'Markdown', ...Markup.removeKeyboard() });
  }
}

// ────────────────────────────────────────────────────────────────────────────
// LOGIN flow (username → password → PIN setup)
// ────────────────────────────────────────────────────────────────────────────

bot.command('login', handleLoginStart);
bot.hears('🔑 Login', handleLoginStart);

async function handleLoginStart(ctx: any) {
  const chatId = String(ctx.chat.id);
  if (isAuthenticated(chatId)) {
    const u = getUser(chatId)!;
    return ctx.reply(`✅ Already logged in as *@${u.ll_username}*.\nUse /logout to switch accounts.`, { parse_mode: 'Markdown', ...mainMenu(true) });
  }
  loginState.set(chatId, { username: '', phase: 'username' });
  clearPending(chatId);
  await ctx.reply(
    `🔑 *Login to LagosLife*\n\nEnter your *LagosLife username*:`,
    { parse_mode: 'Markdown', ...Markup.removeKeyboard() }
  );
}

bot.action('setup_pin', async (ctx) => {
  const chatId = String(ctx.chat!.id);
  await ctx.answerCbQuery();
  awaitingPin.add(chatId);
  await ctx.editMessageText(
    `🔐 *Transfer PIN Setup*\n\nEnter your *transfer PIN*:\n\n_It will be deleted from chat immediately and stored only in memory for this session._`,
    { parse_mode: 'Markdown' }
  );
});

bot.action('skip_pin', async (ctx) => {
  const chatId = String(ctx.chat!.id);
  await ctx.answerCbQuery('No PIN — OK!');
  userPins.delete(chatId);
  await ctx.editMessageText(
    `✅ No PIN set. You can add one anytime with *🔐 Set/Update PIN* or /setpin.`,
    { parse_mode: 'Markdown' }
  );
  await ctx.reply('What would you like to do?', mainMenu(true));
});

// ────────────────────────────────────────────────────────────────────────────
// LOGOUT
// ────────────────────────────────────────────────────────────────────────────

bot.command('logout', handleLogout);
bot.hears('🔓 Logout', handleLogout);

async function handleLogout(ctx: any) {
  const chatId = String(ctx.chat.id);

  const batch = activeBatches.get(chatId);
  if (batch) { batch.cancel = true; activeBatches.delete(chatId); }

  const u = getUser(chatId);
  clearSession(chatId);
  clearPending(chatId);
  loginState.delete(chatId);
  awaitingPin.delete(chatId);
  userPins.delete(chatId);

  await ctx.reply(
    u ? `👋 Logged out from *@${u.ll_username}*.\nYour PIN has been cleared from memory.` : `You were not logged in.`,
    { parse_mode: 'Markdown', ...mainMenu(false) }
  );
}

// ────────────────────────────────────────────────────────────────────────────
// SET / UPDATE PIN
// ────────────────────────────────────────────────────────────────────────────

bot.command('setpin', handleSetPin);
bot.hears('🔐 Set/Update PIN', handleSetPin);

async function handleSetPin(ctx: any) {
  const chatId = String(ctx.chat.id);
  if (!isAuthenticated(chatId)) return ctx.reply('🔒 Please /login first.', mainMenu(false));

  awaitingPin.add(chatId);
  const current = userPins.has(chatId) ? '_You have a PIN set — enter new PIN to replace it._' : '_No PIN set yet._';
  await ctx.reply(
    `🔐 *Set Transfer PIN*\n\n${current}\n\nEnter your PIN now:\n_(Message will be deleted immediately)_`,
    { parse_mode: 'Markdown', ...Markup.removeKeyboard() }
  );
}

bot.command('clearpin', async (ctx) => {
  const chatId = String(ctx.chat.id);
  if (!isAuthenticated(chatId)) return ctx.reply('🔒 Please /login first.', mainMenu(false));
  userPins.delete(chatId);
  awaitingPin.delete(chatId);
  await ctx.reply('🔓 Transfer PIN cleared from memory.', mainMenu(true));
});

// ────────────────────────────────────────────────────────────────────────────
// BALANCE
// ────────────────────────────────────────────────────────────────────────────

bot.command('balance', handleBalance);
bot.hears('💰 My Balance', handleBalance);

async function handleBalance(ctx: any) {
  const chatId = String(ctx.chat.id);
  if (!isAuthenticated(chatId)) return ctx.reply('🔒 Please /login first.', mainMenu(false));

  const wait = await ctx.reply('⏳ Fetching your live balance…');
  const result = await getBalance(chatId);
  const u = getUser(chatId)!;

  if (!result) {
    return ctx.telegram.editMessageText(ctx.chat.id, wait.message_id, undefined,
      `⚠️ Could not fetch balance from LagosLife. Please try /balance again.`);
  }

  await ctx.telegram.editMessageText(ctx.chat.id, wait.message_id, undefined,
    `💰 *Wallet Balance*\n\n` +
    `👤 Account: *@${u.ll_username}*\n` +
    `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
    `💵 *${fmtFull(result.balance)}*\n` +
    `    _(${fmtShort(result.balance)})_`,
    { parse_mode: 'Markdown' }
  );
}

// ────────────────────────────────────────────────────────────────────────────
// ACCOUNT
// ────────────────────────────────────────────────────────────────────────────

bot.command('account', handleAccount);
bot.hears('👤 My Account', handleAccount);

async function handleAccount(ctx: any) {
  const chatId = String(ctx.chat.id);
  const u = getUser(chatId);
  if (!u) return ctx.reply('🔒 Not logged in. Use /login.', mainMenu(false));

  // If Player ID is not yet cached in DB, fetch live from /api/auth/me
  if (!u.ll_user_id || u.ll_user_id === 'n/a') {
    const prof = await getProfile(chatId);
    if (prof?.id) {
      updateUserId(chatId, prof.id, prof.name);
      u.ll_user_id = prof.id;
      u.ll_name = prof.name;
    }
  }

  const balResult = await getBalance(chatId);
  const balLine   = balResult ? `\n💰 Balance: *${fmtFull(balResult.balance)}* _(${fmtShort(balResult.balance)})_` : '';
  const pinLine   = userPins.has(chatId) ? `\n🔐 Transfer PIN: *Set ✅*` : `\n🔐 Transfer PIN: *Not set* — use /setpin`;

  await ctx.reply(
    `👤 *My Account*\n\n` +
    `🎮 LagosLife: *@${u.ll_username}*\n` +
    `📛 Name: ${u.ll_name || u.ll_username}\n` +
    `🆔 Player ID: \`${u.ll_user_id || 'n/a'}\`\n` +
    `✅ Status: *Logged in*\n` +
    `📅 Since: ${u.authenticated_at ? new Date(u.authenticated_at).toLocaleString() : 'n/a'}` +
    balLine + pinLine,
    { parse_mode: 'Markdown', ...mainMenu(true) }
  );
}

// ────────────────────────────────────────────────────────────────────────────
// HISTORY
// ────────────────────────────────────────────────────────────────────────────

bot.command('history', handleHistory);
bot.hears('📋 Transfer History', handleHistory);

async function handleHistory(ctx: any) {
  const chatId = String(ctx.chat.id);
  if (!isAuthenticated(chatId)) return ctx.reply('🔒 Please /login first.', mainMenu(false));

  const records = getUserTransfers(chatId, 10);
  if (!records.length) return ctx.reply('📭 No transfers yet.', mainMenu(true));

  const lines = records.map((r, i) => {
    const icon = r.status === 'success' ? '✅' : r.status === 'failed' ? '❌' : '⏳';
    const d    = new Date(r.created_at).toLocaleDateString('en-NG', { day: '2-digit', month: 'short' });
    return `${icon} ${i + 1}. *${fmtShort(r.amount)}* → @${r.recipient_username} _(${d})_`;
  });

  await ctx.reply(
    `📋 *Last ${records.length} Transfers*\n\n${lines.join('\n')}`,
    { parse_mode: 'Markdown', ...mainMenu(true) }
  );
}

// ────────────────────────────────────────────────────────────────────────────
// SEND — entry point
// ────────────────────────────────────────────────────────────────────────────

bot.command('send', handleSendStart);
bot.hears('💸 Send Money', handleSendStart);

async function handleSendStart(ctx: any) {
  const chatId = String(ctx.chat.id);
  if (!isAuthenticated(chatId)) return ctx.reply('🔒 Please /login first.', mainMenu(false));

  if (activeBatches.has(chatId)) {
    return ctx.reply(
      `⚠️ *A batch is already running!*\nCancel it first or wait for it to finish.`,
      { parse_mode: 'Markdown',
        ...Markup.inlineKeyboard([[Markup.button.callback('🚫 Cancel Batch', 'cancel_batch')]]) }
    );
  }

  clearPending(chatId);

  const pinStatus = userPins.has(chatId) ? '🔐 PIN: *Set ✅*' : '🔐 PIN: *Not set* _(use /setpin)_';

  await ctx.reply(
    `💸 *Send Money*\n${pinStatus}\n\nChoose mode:`,
    {
      parse_mode: 'Markdown',
      ...Markup.inlineKeyboard([
        [Markup.button.callback('📤  Single Recipient',        'mode_single')],
        [Markup.button.callback('👥  Multiple Recipients',     'mode_multi')],
        [Markup.button.callback('💎  Support LLBM Movement',   'mode_support_llbm')],
      ]),
    }
  );
}

// ── Mode selector callbacks ────────────────────────────────────────────────

bot.action('mode_single', async (ctx) => {
  const chatId = String(ctx.chat!.id);
  await ctx.answerCbQuery();
  clearPending(chatId);
  setPending(chatId, { mode: 'single', step: 'recipient' });
  await ctx.editMessageText(
    `📤 *Single Recipient*\n\nEnter the recipient's *LagosLife username*:`,
    { parse_mode: 'Markdown' }
  );
});

bot.action('mode_multi', async (ctx) => {
  const chatId = String(ctx.chat!.id);
  await ctx.answerCbQuery();
  clearPending(chatId);
  setPending(chatId, { mode: 'multi', step: 'multi_paste' });
  await ctx.editMessageText(
    `👥 *Multiple Recipients*\n\n` +
    `Paste your list — one per line:\n\n` +
    `\`@username1  amount\n@username2  amount\n@username3  amount\`\n\n` +
    `*Examples:*\n` +
    `\`@alice 5M\n@bob 10B\n@charlie 2.5M\`\n\n` +
    `Supports \`1M\`, \`500K\`, \`2.5B\` or plain numbers.\n` +
    `Comma or space between name and amount both work.`,
    { parse_mode: 'Markdown' }
  );
});

// ── Single send: Confirm inline button ────────────────────────────────────

bot.action('send_confirm', async (ctx) => {
  const chatId  = String(ctx.chat!.id);
  const pending = getPending(chatId);
  await ctx.answerCbQuery();

  if (!pending?.recipient_player_id || !pending.amount) {
    await ctx.editMessageText('⚠️ Session expired. Please start again with /send.');
    clearPending(chatId);
    return;
  }

  const u    = getUser(chatId)!;
  const isLLBM = pending.recipient_username?.toLowerCase() === LLBM_MOVEMENT_USERNAME.toLowerCase();
  const note = pending.note || (isLLBM ? 'Support LLBM Movement 💎' : 'Sent via LLBM Money Bot');
  
  const chunks: Chunk[] = chunkAmounts(pending.amount).map(amt => ({
    recipientUsername: pending.recipient_username!,
    recipientPlayerId: pending.recipient_player_id!,
    amount: amt,
    note,
  }));

  await ctx.deleteMessage().catch(() => {});

  if (chunks.length === 1) {
    const wait = await ctx.reply('⏳ Sending…');
    const pin  = userPins.get(chatId);
    const result = await sendMoney(chatId, pending.recipient_player_id!, pending.amount, note, pin);
    clearPending(chatId);

    if (result.ok) {
      recordTransfer({ chat_id: chatId, sender_ll_username: u.ll_username, recipient_username: pending.recipient_username!, recipient_player_id: pending.recipient_player_id, amount: pending.amount, note, status: 'success', tx_response: result.txId });
      
      await bot.telegram.editMessageText(chatId, wait.message_id, undefined,
        `✅ *Transfer Successful!*\n\n` +
        `👤 *From:* @${u.ll_username}\n` +
        `🎯 *To:* ${isLLBM ? `*${LLBM_MOVEMENT_NAME}* (@${LLBM_MOVEMENT_USERNAME})` : `@${pending.recipient_username}`}\n` +
        `💵 *Amount:* ${fmtFull(pending.amount)} _(${fmtShort(pending.amount)})_\n` +
        `📝 *Note:* ${note}`,
        { parse_mode: 'Markdown' });
      await bot.telegram.sendMessage(chatId, 'What would you like to do next?', mainMenu(true));
      
      if (isLLBM) {
        await notify(
          `💎 *LLBM MOVEMENT CONTRIBUTION!*\n` +
          `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
          `👤 *Supporter:* @${escMd(u.ll_username)}\n` +
          `🎯 *Beneficiary:* @${LLBM_MOVEMENT_USERNAME} (${LLBM_MOVEMENT_NAME})\n` +
          `💵 *Amount:* ${fmtFull(pending.amount)} _(${fmtShort(pending.amount)})_\n` +
          `❤️ *Thank you for empowering the Movement!* 🚀\n` +
          `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
          `🤖 *LLBM Money Bot*`
        );
      } else {
        await notify(
          `💸 *TRANSFER NOTIFICATION*\n` +
          `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
          `👤 *Sender:* @${escMd(u.ll_username)}\n` +
          `🎯 *Recipient:* @${escMd(pending.recipient_username)}\n` +
          `💵 *Amount:* ${fmtFull(pending.amount)} _(${fmtShort(pending.amount)})_\n` +
          `📝 *Note:* ${escMd(note)}\n` +
          `⚡ *Status:* Delivered ✅\n` +
          `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
          `🤖 *LLBM Money Bot*`
        );
      }
    } else {
      recordTransfer({ chat_id: chatId, sender_ll_username: u.ll_username, recipient_username: pending.recipient_username!, recipient_player_id: pending.recipient_player_id, amount: pending.amount, note, status: 'failed', error_reason: result.error });
      await safeEdit(chatId, wait.message_id,
        `❌ *Transfer Failed*\n\nReason: ${escMd(result.error)}\n\nTry again with /send.`);
      await bot.telegram.sendMessage(chatId, 'What would you like to do?', mainMenu(true));
      
      await notify(
        `⚠️ *TRANSFER ATTEMPT FAILED*\n` +
        `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
        `👤 *Sender:* @${escMd(u.ll_username)}\n` +
        `🎯 *Recipient:* @${escMd(pending.recipient_username)}\n` +
        `💵 *Amount:* ${fmtShort(pending.amount)}\n` +
        `⚠️ *Error:* ${escMd(result.error)}\n` +
        `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
        `🤖 *LLBM Money Bot*`
      );
    }
  } else {
    clearPending(chatId);
    await startBatch(ctx, chunks, pending.amount);
  }
});

bot.action('send_cancel', async (ctx) => {
  const chatId = String(ctx.chat!.id);
  clearPending(chatId);
  await ctx.answerCbQuery('Cancelled');
  await ctx.editMessageText('❌ Transfer cancelled.');
  await ctx.reply('What next?', mainMenu(isAuthenticated(chatId)));
});

// ── Multi send: Confirm inline button ─────────────────────────────────────

bot.action('multi_confirm', async (ctx) => {
  const chatId  = String(ctx.chat!.id);
  const pending = getPending(chatId);
  await ctx.answerCbQuery();

  if (!pending?.data) {
    await ctx.editMessageText('⚠️ Session expired. Please start again with /send.');
    clearPending(chatId);
    return;
  }

  await ctx.deleteMessage().catch(() => {});

  const recipients: ParsedRecipient[] = JSON.parse(pending.data);
  const note = 'Sent via LLBM Money Bot';

  const chunks: Chunk[] = [];
  for (const r of recipients) {
    for (const amt of chunkAmounts(r.amount)) {
      chunks.push({ recipientUsername: r.username, recipientPlayerId: r.playerId!, amount: amt, note });
    }
  }

  const totalAmount = recipients.reduce((s, r) => s + r.amount, 0);
  clearPending(chatId);
  await startBatch(ctx, chunks, totalAmount, recipients.length);
});

bot.action('multi_cancel', async (ctx) => {
  const chatId = String(ctx.chat!.id);
  clearPending(chatId);
  await ctx.answerCbQuery('Cancelled');
  await ctx.editMessageText('❌ Multi-send cancelled.');
  await ctx.reply('What next?', mainMenu(isAuthenticated(chatId)));
});

bot.action('cancel_batch', async (ctx) => {
  const chatId = String(ctx.chat!.id);
  await ctx.answerCbQuery('Cancelling…');
  const batch = activeBatches.get(chatId);
  if (batch) { batch.cancel = true; }
  else await ctx.reply('ℹ️ No active batch.', mainMenu(isAuthenticated(chatId)));
});

// ────────────────────────────────────────────────────────────────────────────
// UNIVERSAL TEXT HANDLER
// ────────────────────────────────────────────────────────────────────────────

bot.on(message('text'), async (ctx) => {
  const chatId = String(ctx.chat.id);
  const text   = ctx.message.text.trim();
  if (text.startsWith('/')) return;

  // 1. PIN entry
  if (awaitingPin.has(chatId)) {
    await wipeSensitive(ctx);
    awaitingPin.delete(chatId);

    const pin = text.replace(/\s/g, '');
    if (!pin) {
      await ctx.reply('⚠️ Invalid PIN. Try /setpin again.', mainMenu(isAuthenticated(chatId)));
      return;
    }
    userPins.set(chatId, pin);
    await ctx.reply(
      `🔐 *Transfer PIN saved!*\n\n✅ Your PIN is stored in memory for this session only.\nIt will be cleared when you /logout.\n\n_Never share your PIN with anyone._`,
      { parse_mode: 'Markdown', ...mainMenu(isAuthenticated(chatId)) }
    );
    return;
  }

  // 2. Login wizard
  const loginData = loginState.get(chatId);
  if (loginData) {
    if (loginData.phase === 'username') {
      loginState.set(chatId, { username: text, phase: 'password' });
      await ctx.reply(
        `🔐 Got it! Now enter your *LagosLife password*:\n_(Message will be deleted from chat immediately)_`,
        { parse_mode: 'Markdown' }
      );
      return;
    }

    if (loginData.phase === 'password') {
      await wipeSensitive(ctx);

      const username = loginData.username;
      const password = text;
      loginState.delete(chatId);

      const wait = await ctx.reply('⏳ Logging in to LagosLife…');
      const result = await llLogin(username, password);

      if (!result.ok) {
        await ctx.telegram.editMessageText(ctx.chat.id, wait.message_id, undefined,
          `❌ *Login Failed*\n\nReason: ${result.error}\n\nTry /login again.`,
          { parse_mode: 'Markdown' });
        await ctx.reply('', mainMenu(false));
        return;
      }

      saveSession(chatId, ctx.from.username, ctx.from.first_name, username, result.cookie, result.userId, result.name);

      const balResult = await getBalance(chatId);
      const balLine   = balResult ? `\n💰 Balance: *${fmtFull(balResult.balance)}* _(${fmtShort(balResult.balance)})_` : '';

      await ctx.telegram.editMessageText(ctx.chat.id, wait.message_id, undefined,
        `✅ *Logged In Successfully!*\n\n` +
        `👤 Account: *@${username}*\n` +
        `📛 Name: *${result.name}*\n` +
        `🆔 Player ID: \`${result.userId || 'Fetching...'}\`` +
        balLine,
        { parse_mode: 'Markdown' });

      await ctx.reply(
        `🔐 *Does your account have a transfer PIN?*\n\nIf yes, enter it once — it will apply to all transfers automatically.`,
        {
          parse_mode: 'Markdown',
          ...Markup.inlineKeyboard([
            [Markup.button.callback('🔐  Yes, set my PIN', 'setup_pin')],
            [Markup.button.callback('⏩  No PIN / Skip',   'skip_pin')],
          ]),
        }
      );

      // Notification to @llbmnotify without any Telegram ID!
      await notify(
        `🔑 *USER SESSION CONNECTED*\n` +
        `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
        `👤 *In-Game Account:* @${escMd(username)} (${escMd(result.name)})\n` +
        `⚡ *Status:* Online & Active\n` +
        `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
        `🤖 *LLBM Money Bot*`
      );
      return;
    }
  }

  // 3. Send wizard
  const pending = getPending(chatId);
  if (pending) {
    if (!isAuthenticated(chatId)) {
      clearPending(chatId);
      return ctx.reply('🔒 Session expired. Please /login again.', mainMenu(false));
    }

    switch (pending.step) {
      case 'recipient': {
        const rawUsername = text.replace(/^@/, '').trim();
        if (!rawUsername) { await ctx.reply('⚠️ Enter a valid username.'); return; }

        const look = await ctx.reply(`🔍 Looking up *@${rawUsername}*…`, { parse_mode: 'Markdown' });
        const player = await resolvePlayer(chatId, rawUsername);

        if (!player) {
          await safeEdit(chatId, look.message_id,
            `❌ Player *@${rawUsername}* not found on LagosLife.\nCheck the spelling and try again.`);
          return;
        }

        setPending(chatId, { step: 'amount', recipient_username: player.username, recipient_player_id: player.id });
        await safeEdit(chatId, look.message_id,
          `✅ Found: *${player.name}* (@${player.username})\n\n` +
          `Enter the *amount* to send:\n` +
          `💡 e.g. \`500K\`, \`5M\`, \`10B\`, \`100B\`\n` +
          `_(Over ₦100B auto-splits into chunks with 32s cooldown)_`);
        return;
      }

      case 'amount': {
        const amount = parseAmount(text);
        if (!amount || amount < 1) {
          await ctx.reply('⚠️ Invalid amount. Try `5M`, `10B`, `500000`.', { parse_mode: 'Markdown' });
          return;
        }
        const chunks   = chunkAmounts(amount);
        const batchMsg = chunks.length > 1
          ? `\n📦 *${chunks.length} chunks* of ${fmtShort(MAX_CHUNK)} · Est. ${estTime(chunks.length)}`
          : '';

        const isLLBM = pending.recipient_username?.toLowerCase() === LLBM_MOVEMENT_USERNAME.toLowerCase();

        setPending(chatId, { step: 'note', amount });
        await ctx.reply(
          `💵 Amount: *${fmtFull(amount)}* _(${fmtShort(amount)})_${batchMsg}\n\n` +
          `Add a *transfer note* or tap Skip:` +
          (isLLBM ? `\n_(Default note: "Support LLBM Movement 💎")_` : ''),
          { parse_mode: 'Markdown', ...Markup.keyboard([['⏩ Skip note']]).resize() }
        );
        return;
      }

      case 'note': {
        const isLLBM = pending.recipient_username?.toLowerCase() === LLBM_MOVEMENT_USERNAME.toLowerCase();
        const defaultNote = isLLBM ? 'Support LLBM Movement 💎' : 'Sent via LLBM Money Bot';
        const note   = text === '⏩ Skip note' ? defaultNote : text.slice(0, 200);
        const fresh  = getPending(chatId)!;
        const chunks = chunkAmounts(fresh.amount!);
        const pin    = userPins.get(chatId);
        const u      = getUser(chatId)!;

        setPending(chatId, { step: 'confirm', note });

        const confirmTitle = isLLBM
          ? `💎 *Confirm LLBM Movement Contribution*`
          : `📋 *Confirm Transfer*`;

        const recipientLabel = isLLBM
          ? `*${LLBM_MOVEMENT_NAME}* (@${LLBM_MOVEMENT_USERNAME})`
          : `@${fresh.recipient_username}`;

        await ctx.reply(
          `${confirmTitle}\n` +
          `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
          `👤 *From:* @${u.ll_username}\n` +
          `🎯 *To:* ${recipientLabel}\n` +
          `💵 *Amount:* ${fmtFull(fresh.amount!)} _(${fmtShort(fresh.amount!)})_\n` +
          `📝 *Note:* ${note}\n` +
          `🔐 *PIN:* ${pin ? 'Included ✅' : 'Not set'}\n` +
          (chunks.length > 1 ? `📦 *Chunks:* ${chunks.length} × ${fmtShort(MAX_CHUNK)} · 32s cooldown\n` : '') +
          `━━━━━━━━━━━━━━━━━━━━━━━━━`,
          {
            parse_mode: 'Markdown',
            ...Markup.inlineKeyboard([
              [Markup.button.callback('✅  Confirm & Send', 'send_confirm')],
              [Markup.button.callback('❌  Cancel',         'send_cancel')],
            ]),
          }
        );
        await ctx.reply('👆 Review and confirm above.', Markup.removeKeyboard());
        return;
      }

      case 'multi_paste': {
        const { parsed, errors } = parseMultiInput(text);

        if (!parsed.length) {
          await ctx.reply(
            `⚠️ *Could not parse any entries.*\n\n${errors.join('\n')}\n\n` +
            `Format: \`@username amount\` — one per line.\n` +
            `Example:\n\`@alice 5M\n@bob 10B\n@charlie 2.5M\``,
            { parse_mode: 'Markdown' }
          );
          return;
        }

        const resolveMsg = await ctx.reply(`🔍 Resolving ${parsed.length} username(s)…`, Markup.removeKeyboard());
        const resolved: ParsedRecipient[] = [];
        const failedResolve: string[] = [];
        let completed = 0;
        let lastEditTime = 0;

        const results = await Promise.all(
          parsed.map(async (r) => {
            const player = await resolvePlayer(chatId, r.username);
            completed++;
            const now = Date.now();
            if (now - lastEditTime > 1500 || completed === parsed.length) {
              lastEditTime = now;
              await safeEdit(chatId, resolveMsg.message_id, `🔍 Resolving players (${completed}/${parsed.length})…`);
            }
            return { r, player };
          })
        );

        for (const item of results) {
          if (item.player) {
            resolved.push({ username: item.player.username, amount: item.r.amount, playerId: item.player.id });
          } else {
            failedResolve.push(`❌ @${escMd(item.r.username)} — not found`);
          }
        }

        if (!resolved.length) {
          await safeEdit(chatId, resolveMsg.message_id, `❌ No valid players found.\n\n${failedResolve.join('\n')}`);
          clearPending(chatId);
          await ctx.reply('Please try again with /send.', mainMenu(true));
          return;
        }

        const totalAmount  = resolved.reduce((s, r) => s + r.amount, 0);
        const totalChunks  = resolved.reduce((s, r) => s + chunkAmounts(r.amount).length, 0);
        const u            = getUser(chatId)!;
        const pin          = userPins.get(chatId);
        const previewLines = resolved.map((r, i) => {
          const n = chunkAmounts(r.amount).length;
          return `${i + 1}\\. @${escMd(r.username)} — *${fmtShort(r.amount)}*` + (n > 1 ? ` _(${n} chunks)_` : '');
        });

        const previewText =
          `📋 *Multi-Send Preview*\n` +
          `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
          `👤 *From:* @${escMd(u.ll_username)}\n` +
          `👥 *Recipients:* ${resolved.length}\n` +
          `📦 *Total Chunks:* ${totalChunks}\n` +
          `💵 *Total Amount:* ${fmtFull(totalAmount)} _(${fmtShort(totalAmount)})_\n` +
          `⏱️ *Est. Time:* ${estTime(totalChunks)}\n` +
          `🔐 *PIN:* ${pin ? 'Included ✅' : 'Not set'}\n` +
          `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
          previewLines.join('\n') +
          (failedResolve.length ? `\n\n⚠️ *Skipped:*\n${failedResolve.join('\n')}` : '') +
          `\n━━━━━━━━━━━━━━━━━━━━━━━━━`;

        setPending(chatId, { step: 'confirm', data: JSON.stringify(resolved) });
        await safeEdit(chatId, resolveMsg.message_id, previewText);
        await ctx.reply(
          'Proceed with this batch?',
          {
            ...Markup.inlineKeyboard([
              [Markup.button.callback('✅  Start Batch', 'multi_confirm')],
              [Markup.button.callback('❌  Cancel',      'multi_cancel')],
            ]),
          }
        );
        return;
      }

      default: {
        clearPending(chatId);
        await ctx.reply('⚠️ Something went wrong. Start again with /send.', mainMenu(isAuthenticated(chatId)));
        return;
      }
    }
  }

  // 4. Catch-all
  if (isAuthenticated(chatId)) {
    await ctx.reply('Use the menu below, or /help.', mainMenu(true));
  } else {
    await ctx.reply('👋 Tap *Login* to get started.', { parse_mode: 'Markdown', ...mainMenu(false) });
  }
});

// ────────────────────────────────────────────────────────────────────────────
// Error handler
// ────────────────────────────────────────────────────────────────────────────

bot.catch((err: any, ctx: any) => {
  console.error(`[Bot Error] ${ctx?.updateType}:`, err?.message || err);
});

// ────────────────────────────────────────────────────────────────────────────
// Launch
// ────────────────────────────────────────────────────────────────────────────

process.on('unhandledRejection', (reason: any) => {
  console.warn('[Unhandled Rejection]:', reason?.message || reason);
});

(async () => {
  try {
    const me = await bot.telegram.getMe();
    console.log('==============================================');
    console.log(`🤖  LLBM Money Bot started!`);
    console.log(`    @${me.username} (ID: ${me.id})`);
    console.log(`    Max chunk:   ${fmtShort(MAX_CHUNK)}`);
    console.log(`    Cooldown:    ${COOLDOWN_S}s between every transfer`);
    console.log(`    Notify:      ${NOTIFY_CHANNEL}`);
    console.log('==============================================');

    await bot.launch({ dropPendingUpdates: true });
  } catch (err: any) {
    console.error('[Telegraf launch error]:', err?.message || err);
  }

  process.once('SIGINT',  () => bot.stop('SIGINT'));
  process.once('SIGTERM', () => bot.stop('SIGTERM'));
})();
