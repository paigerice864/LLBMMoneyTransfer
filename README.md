# 💸 LLBM Money Bot

A production-ready Telegram bot that allows multiple users to send money directly from their own **LagosLife** accounts simultaneously without interfering with each other.

- **Bot:** [@LLBM_MoneyBot](https://t.me/LLBM_MoneyBot)
- **Official Broadcast Channel:** [@llbmnotify](https://t.me/llbmnotify)

---

## 🚀 Key Features

| Feature | Description |
|---|---|
| 📢 **Mandatory Channel Subscription** | Users must join `@llbmnotify` before unlocking the bot. One-tap instant verification. |
| 🔔 **Live Public Ticker (Privacy-First)** | All transfers and batch completions announce publicly to `@llbmnotify` **without revealing any user's Telegram ID or username** — only in-game account handles are shown. |
| 💎 **Direct Support for LLBM Movement** | One-tap button to support the **LL Billionaire Movement** (`@LLBmovement`). Enter any amount — amounts > ₦10B automatically split into batches with a 32s cooldown. |
| 💰 **Accurate Live Balance Fetching** | Real-time in-game cash balance is fetched directly from LagosLife `/api/save`. |
| 🆔 **Verified Player ID Fetching** | The user's permanent LagosLife Player ID is queried from `/api/auth/me` and linked to their account profile. |
| 🔒 **Complete Multi-User Isolation** | Each user signs into their own LagosLife account. All API calls, balances, and transfers use only the calling user's credentials. |
| 🗑️ **Zero-Trace Password & PIN Wiping** | Passwords and PINs are instantly deleted from chat messages upon sending. |
| 🔐 **Transfer PIN Support** | Enter your PIN once per session. Stored strictly in-memory (never written to database or disk), and automatically applied to all transfers and batch chunks. |
| ⏱️ **Automatic 32s Cooldown** | 32-second cooldown enforced between every transfer and chunk, with a live updating countdown progress bar. |
| 📦 **Smart Chunk Auto-Splitting** | LagosLife enforces a ₦10B transfer cap. Any amount exceeding ₦10B (e.g. ₦100B) automatically splits into ₦10B chunks without manual intervention. |
| 👥 **Multi-Recipient Batch Sending** | Paste multiple recipients at once (`@alice 5M\n@bob 10B`). The bot resolves usernames live, estimates total transfer time, and executes in sequence. |
| 🚫 **Live Cancel Button** | Stop any active batch gracefully at any point. |

---

## 💎 Support LLBM Movement Flow

1. Click **💎 Support LLBM** from the main menu or use `/support`.
2. Enter the amount to contribute (e.g. `100K`, `5M`, `10B`, `50B`, `100B`).
3. If over ₦10B, it automatically calculates the chunks (e.g. 50B = 5 chunks of ₦10B).
4. Review the confirmation screen and click **Confirm & Send**.
5. Each chunk executes with the standard 32s cooldown, and a special announcement is broadcast to `@llbmnotify` thanking the supporter!

---

## 🛠️ Multi-Recipient Paste Format

When selecting **Multiple Recipients**, paste your list separated by newlines or semicolons:

```text
@alice 5M
@bob 10B
@charlie 2.5B
@david, 500K
```

Supports flexible amount formats:
- `500K` = ₦500,000
- `5M` = ₦5,000,000
- `10B` = ₦10,000,000,000
- Raw digits e.g. `1000000`

---

## ⚙️ Setup & Deployment (FadeHost)

### 1. Requirements on Telegram
Ensure **@LLBM_MoneyBot** is added as an **Administrator** to your channel **@llbmnotify** with:
- ✅ *Post Messages*
- ✅ *Manage Channel / Invite Users via Link* (so it can check member subscription)

### 2. Local Testing
```cmd
cd c:\xampp\htdocs\lagoslife\llbm_money_bot
npm start
```

### 3. Deploy to FadeHost via GitHub

1. Push the `llbm_money_bot` folder to your GitHub repository:
```bash
git init
git add .
git commit -m "LLBM Money Bot with Balance fix, Player ID fetch, LLBM Movement Support & Privacy channel notifications"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/llbm-money-bot.git
git push -u origin main
```

2. On your FadeHost server:
```bash
git clone https://github.com/YOUR_USERNAME/llbm-money-bot.git
cd llbm-money-bot
npm install
npm run build
cp .env.example .env
nano .env
```

Ensure `.env` contains:
```env
TELEGRAM_BOT_TOKEN=8966519495:AAGc8_VpQ1dgJt_uSIuRAX7DHCWxJ0pKqr0
NOTIFY_CHANNEL=@llbmnotify
GAME_URL=https://lagoslife.app
DB_PATH=./data/llbm_money_bot.db
```

3. Run continuously using PM2:
```bash
npm install -g pm2
pm2 start dist/index.js --name llbm-money-bot
pm2 save
pm2 startup
```
