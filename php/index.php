<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HeisDaemon — Official Executive Suite</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0d14;
            --card: #121824;
            --border: #1e293b;
            --accent: #10b981;
            --accent-glow: rgba(16, 185, 129, 0.2);
            --gold: #f59e0b;
            --text: #f8fafc;
            --text-muted: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .container {
            max-width: 520px;
            width: 100%;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 36px 32px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            background: var(--accent-glow);
            color: var(--accent);
            border: 1px solid rgba(16, 185, 129, 0.3);
            margin-bottom: 20px;
        }
        .pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 10px var(--accent);
        }
        h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        p {
            color: var(--text-muted);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 28px;
        }
        .features {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 16px;
            text-align: left;
            margin-bottom: 28px;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            padding: 8px 0;
            color: #cbd5e1;
        }
        .btn-stack {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 14px 20px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.05);
            color: var(--text);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.08);
        }
        .footer {
            margin-top: 24px;
            font-size: 13px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="badge">
            <span class="pulse"></span> System Live & Operational
        </div>
        <h1>⚡ HeisDaemon Executive Suite</h1>
        <p>Proprietary high-frequency financial automation, luxury house cloning, and nightclub floor cash sweeper for LagosLife.</p>
        
        <div class="features">
            <div class="feature-item">💸 <b>Mega & Trillionaire Transfers:</b> Send millions, billions, or trillions (T) with smart auto-batching</div>
            <div class="feature-item">📦 <b>₦10B Auto-Chunking:</b> Seamlessly handles the 32-second transfer cooldown</div>
            <div class="feature-item">👥 <b>Multi-Recipient Payroll:</b> Batch disperse funds to unlimited players</div>
            <div class="feature-item">🏰 <b>Luxury House Cloning:</b> 1-Click clone celebrity mansions & custom residences</div>
            <div class="feature-item">🪩 <b>Quilox Cash Sweeper:</b> Grab sprayed cash off the VIP nightclub dance floor</div>
            <div class="feature-item">🛡️ <b>Anti-Distribution Gate:</b> Private Master Admin device & account authorization</div>
            <div class="feature-item">🔐 <b>PIN & Session Security:</b> Ephemeral in-memory security and instant credential wiping</div>
        </div>

        <div class="btn-stack">
            <a href="https://t.me/HeisdaemonBot" target="_blank" class="btn btn-primary">🚀 Launch @HeisdaemonBot</a>
            <a href="supervisor.php" class="btn" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.35);">🎛️ Bot Web Supervisor & Cloud Controls</a>
            <a href="https://t.me/Heisdaemon" target="_blank" class="btn btn-secondary">📢 Live Channel @Heisdaemon</a>
        </div>

        <div class="footer">
            Powered by LagosLife &middot; Hosted on lagoslife.hostedbyfps.com
        </div>
    </div>
</body>
</html>
