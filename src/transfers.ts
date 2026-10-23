import db from './db';
import { randomUUID } from 'crypto';

// ────────────────────────────────────────────────────────────────────────────
// Transfer history
// ────────────────────────────────────────────────────────────────────────────

export interface TransferRecord {
  id: number;
  chat_id: string;
  sender_ll_username: string;
  recipient_username: string;
  recipient_player_id?: string;
  amount: number;
  note?: string;
  status: 'pending' | 'success' | 'failed';
  error_reason?: string;
  tx_response?: string;
  created_at: string;
}

export function recordTransfer(params: {
  chat_id: string;
  sender_ll_username: string;
  recipient_username: string;
  recipient_player_id?: string;
  amount: number;
  note?: string;
  status: 'pending' | 'success' | 'failed';
  error_reason?: string;
  tx_response?: string;
}): number {
  const r = db.prepare(`
    INSERT INTO transfers (
      chat_id, sender_ll_username, recipient_username, recipient_player_id,
      amount, note, status, error_reason, tx_response
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
  `).run(
    params.chat_id,
    params.sender_ll_username,
    params.recipient_username,
    params.recipient_player_id || null,
    params.amount,
    params.note || null,
    params.status,
    params.error_reason || null,
    params.tx_response || null
  );
  return Number(r.lastInsertRowid);
}

export function getUserTransfers(chatId: string, limit = 10): TransferRecord[] {
  return db.prepare(`
    SELECT * FROM transfers WHERE chat_id = ? ORDER BY id DESC LIMIT ?
  `).all(chatId, limit) as TransferRecord[];
}

// ────────────────────────────────────────────────────────────────────────────
// Pending transfer wizard state
// ────────────────────────────────────────────────────────────────────────────

export type SendMode = 'single' | 'multi';
export type SendStep = 'recipient' | 'amount' | 'note' | 'confirm' | 'multi_paste';

export interface ParsedRecipient {
  username: string;
  amount: number;
  playerId?: string;
}

export interface PendingTransfer {
  chat_id: string;
  mode: SendMode;
  recipient_username?: string;
  recipient_player_id?: string;
  amount?: number;
  note?: string;
  step: SendStep;
  data?: string; // JSON: ParsedRecipient[]
}

export function getPending(chatId: string): PendingTransfer | null {
  return db.prepare('SELECT * FROM pending_transfers WHERE chat_id = ?').get(chatId) as PendingTransfer | null;
}

export function setPending(chatId: string, fields: Partial<PendingTransfer> & { step: SendStep }): void {
  const existing = getPending(chatId);
  if (!existing) {
    db.prepare(`
      INSERT INTO pending_transfers (chat_id, mode, recipient_username, recipient_player_id, amount, note, step, data)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    `).run(
      chatId,
      fields.mode || 'single',
      fields.recipient_username || null,
      fields.recipient_player_id || null,
      fields.amount ?? null,
      fields.note || null,
      fields.step,
      fields.data || null
    );
  } else {
    db.prepare(`
      UPDATE pending_transfers
      SET mode                = COALESCE(?, mode),
          recipient_username  = COALESCE(?, recipient_username),
          recipient_player_id = COALESCE(?, recipient_player_id),
          amount              = COALESCE(?, amount),
          note                = COALESCE(?, note),
          step                = ?,
          data                = COALESCE(?, data)
      WHERE chat_id = ?
    `).run(
      fields.mode ?? null,
      fields.recipient_username ?? null,
      fields.recipient_player_id ?? null,
      fields.amount ?? null,
      fields.note ?? null,
      fields.step,
      fields.data ?? null,
      chatId
    );
  }
}

export function clearPending(chatId: string): void {
  db.prepare('DELETE FROM pending_transfers WHERE chat_id = ?').run(chatId);
}

// ────────────────────────────────────────────────────────────────────────────
// Batch jobs
// ────────────────────────────────────────────────────────────────────────────

export interface BatchJob {
  id: string;
  chat_id: string;
  sender_ll_username: string;
  mode: SendMode;
  status: 'running' | 'completed' | 'cancelled' | 'failed';
  total_chunks: number;
  completed_chunks: number;
  failed_chunks: number;
  total_amount: number;
  sent_amount: number;
  progress_msg_id?: number;
  created_at: string;
}

export function createBatchJob(params: {
  chatId: string;
  senderUsername: string;
  mode: SendMode;
  totalChunks: number;
  totalAmount: number;
  progressMsgId?: number;
}): string {
  const id = randomUUID();
  db.prepare(`
    INSERT INTO batch_jobs (id, chat_id, sender_ll_username, mode, status, total_chunks, total_amount, progress_msg_id)
    VALUES (?, ?, ?, ?, 'running', ?, ?, ?)
  `).run(id, params.chatId, params.senderUsername, params.mode, params.totalChunks, params.totalAmount, params.progressMsgId ?? null);
  return id;
}

export function updateBatchJob(jobId: string, fields: {
  status?: string;
  completedChunks?: number;
  failedChunks?: number;
  sentAmount?: number;
}): void {
  db.prepare(`
    UPDATE batch_jobs
    SET status           = COALESCE(?, status),
        completed_chunks = COALESCE(?, completed_chunks),
        failed_chunks    = COALESCE(?, failed_chunks),
        sent_amount      = COALESCE(?, sent_amount),
        updated_at       = CURRENT_TIMESTAMP
    WHERE id = ?
  `).run(
    fields.status ?? null,
    fields.completedChunks ?? null,
    fields.failedChunks ?? null,
    fields.sentAmount ?? null,
    jobId
  );
}

export function recordBatchChunk(params: {
  jobId: string;
  chunkIndex: number;
  recipientUsername: string;
  recipientPlayerId?: string;
  amount: number;
  note?: string;
  status: 'success' | 'failed';
  errorReason?: string;
}): void {
  db.prepare(`
    INSERT INTO batch_chunks (job_id, chunk_index, recipient_username, recipient_player_id, amount, note, status, error_reason, sent_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
  `).run(
    params.jobId,
    params.chunkIndex,
    params.recipientUsername,
    params.recipientPlayerId || null,
    params.amount,
    params.note || null,
    params.status,
    params.errorReason || null
  );
}
