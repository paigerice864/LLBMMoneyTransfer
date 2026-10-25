<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HeisDaemon — Executive Suite & Web Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #090d16;
            --surface: #0f172a;
            --surface-card: #131d33;
            --surface-hover: #1b2845;
            --border: #1e293b;
            --border-accent: rgba(16, 185, 129, 0.3);
            --accent: #10b981;
            --accent-glow: rgba(16, 185, 129, 0.25);
            --gold: #f59e0b;
            --gold-glow: rgba(245, 158, 11, 0.2);
            --blue: #3b82f6;
            --purple: #a855f7;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --danger: #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.5;
            padding-bottom: 80px;
        }

        /* Top Nav */
        header {
            background: rgba(15, 23, 42, 0.88);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 50;
            padding: 14px 24px;
        }
        .nav-inner {
            max-width: 1240px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text);
        }
        .brand-logo {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 4px 14px var(--accent-glow);
        }
        .brand-title {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .brand-sub {
            font-size: 11px;
            color: var(--accent);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
        }
        .btn-pill:hover {
            border-color: var(--accent);
            background: var(--surface-hover);
        }
        .btn-pill-tg {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border-color: rgba(59, 130, 246, 0.35);
        }
        .btn-pill-tg:hover {
            background: rgba(59, 130, 246, 0.25);
            border-color: #60a5fa;
        }

        /* Container */
        .main-wrap {
            max-width: 1240px;
            margin: 24px auto 0;
            padding: 0 20px;
        }

        /* Hero Banner */
        .hero {
            background: linear-gradient(135deg, rgba(19, 29, 51, 0.95) 0%, rgba(15, 23, 42, 0.98) 100%);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px 28px;
            margin-bottom: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .hero-left h1 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .hero-left p {
            color: var(--text-muted);
            font-size: 14px;
        }
        .hero-stats {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        .stat-box {
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 18px;
            min-width: 150px;
        }
        .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .stat-value {
            font-size: 20px;
            font-weight: 800;
            color: var(--accent);
            font-family: 'JetBrains Mono', monospace;
        }

        /* Tabs Nav */
        .tabs {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 22px;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 12px 20px;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 700;
            border-radius: 12px 12px 0 0;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .tab-btn:hover {
            color: var(--text);
            background: rgba(255,255,255,0.03);
        }
        .tab-btn.active {
            color: var(--accent);
            background: rgba(16, 185, 129, 0.1);
            border-bottom: 2px solid var(--accent);
        }

        /* Tab Content */
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }

        /* Cards & Grid */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
        }
        .card {
            background: var(--surface-card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            transition: all 0.2s ease;
        }
        .card:hover {
            border-color: rgba(255,255,255,0.12);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .card-title {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Account Cards */
        .account-card {
            background: linear-gradient(145deg, #131d33 0%, #0d1527 100%);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 14px;
        }
        .acc-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .acc-name {
            font-size: 17px;
            font-weight: 800;
        }
        .acc-handle {
            font-size: 13px;
            color: var(--accent);
        }
        .status-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 9999px;
            background: rgba(16, 185, 129, 0.15);
            color: var(--accent);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .status-badge.offline {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border-color: rgba(239, 68, 68, 0.3);
        }
        .acc-balance {
            font-size: 24px;
            font-weight: 800;
            color: var(--text);
            font-family: 'JetBrains Mono', monospace;
            margin-bottom: 10px;
        }
        .acc-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--text-muted);
            border-top: 1px solid rgba(255,255,255,0.05);
            padding-top: 10px;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        .input-control, select, textarea {
            width: 100%;
            background: rgba(15, 23, 42, 0.75);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            color: var(--text);
            font-family: inherit;
            outline: none;
            transition: all 0.2s;
        }
        .input-control:focus, select:focus, textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 10px var(--accent-glow);
        }
        .chip-stack {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .chip {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s;
        }
        .chip:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: rgba(16, 185, 129, 0.1);
        }

        /* Action Buttons */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 22px;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.45);
        }
        .btn-action:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .btn-action.gold {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        .btn-action.blue {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        /* Mansion Catalog */
        .mansion-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .mansion-name {
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .mansion-meta {
            font-size: 12px;
            color: var(--accent);
            margin-bottom: 8px;
        }
        .mansion-desc {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 14px;
            line-height: 1.4;
        }

        /* ────────────────────────────────────────────────────────────────────────
           ROLLING BEAUTIFUL PROGRESS MODAL
           ──────────────────────────────────────────────────────────────────────── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(3, 7, 18, 0.85);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        .progress-card {
            background: linear-gradient(145deg, #131d35 0%, #0c1424 100%);
            border: 1px solid rgba(16, 185, 129, 0.4);
            border-radius: 24px;
            max-width: 500px;
            width: 100%;
            padding: 32px 28px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.7), 0 0 35px rgba(16, 185, 129, 0.2);
            text-align: center;
            position: relative;
            animation: modalFadeIn 0.3s ease-out;
        }
        @keyframes modalFadeIn {
            from { transform: scale(0.92); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        /* Rolling Orbital Spinner */
        .orbital-wrapper {
            position: relative;
            width: 110px;
            height: 110px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .orbital-ring {
            position: absolute;
            border-radius: 50%;
            border-style: solid;
        }
        .orbital-ring.outer {
            width: 100px;
            height: 100px;
            border-width: 4px;
            border-color: rgba(16, 185, 129, 0.15);
            border-top-color: #10b981;
            border-right-color: #059669;
            animation: orbitClockwise 1.4s linear infinite;
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.6));
        }
        .orbital-ring.inner {
            width: 74px;
            height: 74px;
            border-width: 3px;
            border-color: rgba(245, 158, 11, 0.15);
            border-bottom-color: #f59e0b;
            border-left-color: #d97706;
            animation: orbitCounter 0.9s cubic-bezier(0.4, 0, 0.2, 1) infinite;
            filter: drop-shadow(0 0 8px rgba(245, 158, 11, 0.5));
        }
        .orbital-core {
            font-size: 16px;
            font-weight: 800;
            font-family: 'JetBrains Mono', monospace;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
            animation: corePulse 1.5s ease-in-out infinite;
        }

        @keyframes orbitClockwise {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes orbitCounter {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
        }
        @keyframes corePulse {
            0%, 100% { transform: scale(1); opacity: 0.9; }
            50% { transform: scale(1.08); opacity: 1; }
        }

        .progress-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: -0.3px;
        }
        .progress-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 22px;
            min-height: 20px;
        }

        /* Rolling Striped Shimmer Bar */
        .progress-bar-track {
            background: rgba(0, 0, 0, 0.45);
            border: 1px solid var(--border);
            border-radius: 9999px;
            height: 16px;
            overflow: hidden;
            position: relative;
            margin-bottom: 20px;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.5);
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 50%, #10b981 100%);
            border-radius: 9999px;
            transition: width 0.4s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.5);
        }
        .progress-bar-fill::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: linear-gradient(
                45deg,
                rgba(255, 255, 255, 0.22) 25%,
                transparent 25%,
                transparent 50%,
                rgba(255, 255, 255, 0.22) 50%,
                rgba(255, 255, 255, 0.22) 75%,
                transparent 75%,
                transparent
            );
            background-size: 32px 32px;
            animation: rollStripes 0.9s linear infinite;
        }
        @keyframes rollStripes {
            from { background-position: 0 0; }
            to { background-position: 32px 0; }
        }

        /* Progress Metrics */
        .progress-stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 22px;
        }
        .p-stat {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 10px;
        }
        .p-stat-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 3px;
        }
        .p-stat-val {
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
            font-family: 'JetBrains Mono', monospace;
        }

        .prog-actions-row {
            display: flex;
            gap: 10px;
        }
        .btn-prog-cancel {
            flex: 1;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid rgba(239, 68, 68, 0.4);
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-prog-cancel:hover {
            background: rgba(239, 68, 68, 0.25);
            border-color: var(--danger);
        }
        .btn-prog-min {
            flex: 1;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.04);
            color: var(--text-muted);
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-prog-min:hover {
            color: var(--text);
            background: rgba(255,255,255,0.08);
        }

        /* ────────────────────────────────────────────────────────────────────────
           LIVE TERMINAL & CONSOLE LOG
           ──────────────────────────────────────────────────────────────────────── */
        .terminal-card {
            background: #06090e;
            border: 1px solid #1e293b;
            border-radius: 18px;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            margin-top: 24px;
        }
        .terminal-header {
            background: #0a0f1d;
            border-bottom: 1px solid #1e293b;
            padding: 12px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .terminal-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            color: #cbd5e1;
        }
        .term-pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 10px var(--accent);
            animation: corePulse 1.2s infinite;
        }
        .term-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .term-chip {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s;
        }
        .term-chip:hover, .term-chip.active {
            color: var(--accent);
            border-color: var(--accent);
            background: rgba(16, 185, 129, 0.12);
        }
        .terminal-body {
            height: 300px;
            overflow-y: auto;
            padding: 14px 18px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            line-height: 1.6;
            color: #94a3b8;
            scroll-behavior: smooth;
        }
        .terminal-body::-webkit-scrollbar {
            width: 6px;
        }
        .terminal-body::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 3px;
        }
        .log-row {
            padding: 3px 0;
            display: flex;
            align-items: baseline;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.02);
            word-break: break-word;
        }
        .log-time {
            color: #475569;
            flex-shrink: 0;
            font-size: 11px;
        }
        .log-tag {
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 4px;
            flex-shrink: 0;
            text-transform: uppercase;
        }
        .log-tag.transfer { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .log-tag.batch    { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .log-tag.engine   { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }
        .log-tag.failed   { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .log-tag.bot      { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .log-msg {
            color: #cbd5e1;
            flex: 1;
        }

        /* Toast */
        #toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            background: #1e293b;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            gap: 12px;
            z-index: 9999;
            border: 1px solid rgba(255,255,255,0.1);
        }
        #toast.success { background: #064e3b; border-color: #10b981; }
        #toast.error   { background: #7f1d1d; border-color: #ef4444; }

        /* Login Modal */
        .login-card {
            background: var(--surface-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            max-width: 440px;
            width: 100%;
            padding: 28px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6);
        }

        /* History Table */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        th { color: var(--text-muted); font-weight: 600; }
        td { color: var(--text); }
    </style>
</head>
<body>

    <!-- Header -->
    <header>
        <div class="nav-inner">
            <a href="index.php" class="brand">
                <div class="brand-logo">⚡</div>
                <div>
                    <div class="brand-title">HeisDaemon Suite</div>
                    <div class="brand-sub">Executive Cloud Portal</div>
                </div>
            </a>
            <div class="nav-actions">
                <a href="https://t.me/HeisdaemonBot" target="_blank" class="btn-pill btn-pill-tg">
                    🤖 Open @HeisdaemonBot
                </a>
                <a href="supervisor.php" class="btn-pill">
                    🎛️ Supervisor
                </a>
                <button onclick="refreshData()" class="btn-pill">
                    🔄 Refresh
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-wrap">
        
        <!-- Hero Stats Banner -->
        <div class="hero">
            <div class="hero-left">
                <h1>⚡ Welcome, HeisDaemon Executive</h1>
                <p>High-frequency transfers, celebrity mansion cloning, and nightclub cash sweeper. Live progress and alerts sync directly to your Telegram bot.</p>
            </div>
            <div class="hero-stats">
                <div class="stat-box">
                    <div class="stat-label">Total Connected Wealth</div>
                    <div class="stat-value" id="statTotalWealth">Loading…</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Active Accounts</div>
                    <div class="stat-value" id="statAccountsCount">Loading…</div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('portfolio', this)">💼 Accounts & Balances</button>
            <button class="tab-btn" onclick="switchTab('transfer', this)">💸 Send Money (Single / Mega)</button>
            <button class="tab-btn" onclick="switchTab('payroll', this)">👥 Multi-Recipient Payroll</button>
            <button class="tab-btn" onclick="switchTab('house', this)">🏰 House Beautification</button>
            <button class="tab-btn" onclick="switchTab('quilox', this)">🪩 Quilox Cash Sweeper</button>
            <button class="tab-btn" onclick="switchTab('llbm', this)">💎 Support LLBM</button>
            <button class="tab-btn" onclick="switchTab('history', this)">📋 Transfers Table</button>
        </div>

        <!-- TAB 1: ACCOUNTS & PORTFOLIO -->
        <div id="tab-portfolio" class="tab-pane active">
            <div class="grid-2">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">👤 Active LagosLife Accounts</div>
                        <button onclick="openLoginModal()" class="btn-pill btn-pill-tg" style="font-size: 12px; padding: 4px 12px;">+ Connect Account</button>
                    </div>
                    <div id="accountsList">Loading accounts…</div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">🔐 Account Security & PIN</div>
                    </div>
                    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
                        Manage your secure 4-8 digit LagosLife transfer PIN. Stored PINs are automatically attached to outgoing mega-transfers and auto-chunked batches.
                    </p>
                    <div class="form-group">
                        <label>Select Account:</label>
                        <select id="pinAccountSelect"></select>
                    </div>
                    <div class="form-group">
                        <label>Enter 4-8 Digit Transfer PIN:</label>
                        <input type="password" id="pinInput" class="input-control" placeholder="••••" maxlength="8">
                    </div>
                    <button onclick="savePin()" class="btn-action blue">💾 Save Transfer PIN</button>
                </div>
            </div>
        </div>

        <!-- TAB 2: SEND MONEY -->
        <div id="tab-transfer" class="tab-pane">
            <div class="card" style="max-width: 680px; margin: 0 auto;">
                <div class="card-header">
                    <div class="card-title">💸 Instant LagosLife Money Transfer</div>
                    <span class="status-badge">Auto ₦10B Chunking Active</span>
                </div>

                <div class="form-group">
                    <label>From Account (Sender):</label>
                    <select id="sendFromSelect" onchange="updateSenderBalance()"></select>
                    <div id="senderBalanceNote" style="font-size: 12px; color: var(--accent); margin-top: 4px;"></div>
                </div>

                <div class="form-group">
                    <label>Recipient Username:</label>
                    <input type="text" id="sendRecipient" class="input-control" placeholder="@username (e.g., @wedekaov, @phinnybabe)">
                </div>

                <div class="form-group">
                    <label>Amount to Send (Supports K, M, B, T):</label>
                    <input type="text" id="sendAmount" class="input-control" placeholder="e.g. 5M, 10B, 50B, 1T" oninput="previewChunking()">
                    <div class="chip-stack">
                        <span class="chip" onclick="setSendAmount('100M')">100M</span>
                        <span class="chip" onclick="setSendAmount('1B')">1B</span>
                        <span class="chip" onclick="setSendAmount('5B')">5B</span>
                        <span class="chip" onclick="setSendAmount('10B')">10B</span>
                        <span class="chip" onclick="setSendAmount('50B')">50B</span>
                        <span class="chip" onclick="setSendAmount('100B')">100B</span>
                        <span class="chip" onclick="setSendAmount('1T')">1T (Trillion)</span>
                        <span class="chip" onclick="setSendAmount('2.5T')">2.5T</span>
                    </div>
                    <div id="chunkPreviewNote" style="font-size: 12px; color: var(--gold); margin-top: 8px; display: none;"></div>
                </div>

                <div class="form-group">
                    <label>Transfer Note (Optional):</label>
                    <input type="text" id="sendNote" class="input-control" placeholder="Sent via HeisDaemon Suite">
                </div>

                <div class="form-group">
                    <label>Transfer PIN (Leave blank to use saved PIN):</label>
                    <input type="password" id="sendPinOverride" class="input-control" placeholder="Leave empty if already saved">
                </div>

                <button onclick="executeTransfer()" id="btnSendMoney" class="btn-action">
                    🚀 Execute Transfer Now
                </button>
                <div style="font-size: 12px; color: var(--text-muted); text-align: center; margin-top: 10px;">
                    🔔 Real-time confirmation & receipt will automatically be dispatched to <b>@HeisdaemonBot</b>
                </div>
            </div>
        </div>

        <!-- TAB 3: MULTI-RECIPIENT PAYROLL -->
        <div id="tab-payroll" class="tab-pane">
            <div class="card" style="max-width: 720px; margin: 0 auto;">
                <div class="card-header">
                    <div class="card-title">👥 Multi-Recipient Payroll Blitz</div>
                    <span class="status-badge">Mass Dispersal</span>
                </div>

                <div class="form-group">
                    <label>Disperse From Account:</label>
                    <select id="payrollFromSelect"></select>
                </div>

                <div class="form-group">
                    <label>Paste Recipient List (One per line: <code>@username amount</code>):</label>
                    <textarea id="payrollList" class="input-control" rows="8" placeholder="@alice 5M&#10;@bob 10B&#10;@charlie 1T&#10;@david 500M"></textarea>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                        Supports amounts in <code>K</code>, <code>M</code>, <code>B</code>, <code>T</code> or raw numbers. Amounts > ₦10B automatically auto-chunk with 32s cooldown timing.
                    </div>
                </div>

                <button onclick="executePayroll()" id="btnPayroll" class="btn-action blue">
                    🚀 Launch Payroll Batch Worker
                </button>
            </div>
        </div>

        <!-- TAB 4: HOUSE BEAUTIFICATION -->
        <div id="tab-house" class="tab-pane">
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <div class="card-title">🏰 Master Luxury Mansion Catalog</div>
                    <span class="status-badge">Clean Slate Guarantee</span>
                </div>
                <div class="form-group" style="max-width: 380px;">
                    <label>Apply Design to Account:</label>
                    <select id="houseAccountSelect"></select>
                </div>
                <div class="grid-2" id="catalogGrid">Loading mansions…</div>
            </div>

            <div class="card" style="max-width: 600px;">
                <div class="card-header">
                    <div class="card-title">✍️ Clone Any Player's House by Username</div>
                </div>
                <div class="form-group">
                    <label>Target LagosLife Username:</label>
                    <input type="text" id="targetPlayerUser" class="input-control" placeholder="@wedekaov, @phinnybabe, @kimoflele">
                </div>
                <button onclick="clonePlayerHouse()" id="btnClonePlayer" class="btn-action gold">
                    🔍 Inspect & Clone Player Layout
                </button>
            </div>
        </div>

        <!-- TAB 5: QUILOX NIGHTCLUB -->
        <div id="tab-quilox" class="tab-pane">
            <div class="card" style="max-width: 640px; margin: 0 auto;">
                <div class="card-header">
                    <div class="card-title">🪩 Quilox Nightclub Floor Cash Sweeper</div>
                    <span class="status-badge" style="background: rgba(245, 158, 11, 0.15); color: var(--gold); border-color: rgba(245, 158, 11, 0.3);">Venue: clubEko</span>
                </div>

                <div class="form-group">
                    <label>Active Character:</label>
                    <select id="quiloxAccountSelect"></select>
                </div>

                <div style="background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 14px; padding: 20px; text-align: center; margin-bottom: 20px;">
                    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">Total Sprayed Cash Scooped</div>
                    <div style="font-size: 32px; font-weight: 800; color: var(--gold); font-family: 'JetBrains Mono', monospace;" id="quiloxTotalScooped">₦0</div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <button onclick="sweepQuilox()" id="btnSweepQuilox" class="btn-action gold">
                        ⚡ Sweep Dance Floor Now
                    </button>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <button onclick="teleportQuilox()" class="btn-pill" style="justify-content: center;">
                            📍 Teleport to Quilox
                        </button>
                        <button onclick="claimQuilox()" class="btn-pill" style="justify-content: center;">
                            💰 Claim Cash to Wallet
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 6: SUPPORT LLBM -->
        <div id="tab-llbm" class="tab-pane">
            <div class="card" style="max-width: 600px; margin: 0 auto;">
                <div class="card-header">
                    <div class="card-title">💎 Support LL Billionaire Movement</div>
                </div>
                <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid var(--border-accent); border-radius: 14px; padding: 18px; margin-bottom: 18px;">
                    <div style="font-size: 16px; font-weight: 800; margin-bottom: 4px;">LL Billionaire Movement (@LLBmovement)</div>
                    <div style="font-size: 13px; color: var(--text-muted);">Player ID: <code>8c359c58d9094d598d3f</code></div>
                </div>

                <div class="form-group">
                    <label>Contribute From:</label>
                    <select id="llbmFromSelect"></select>
                </div>

                <div class="form-group">
                    <label>Contribution Amount:</label>
                    <input type="text" id="llbmAmount" class="input-control" placeholder="e.g. 100M, 1B, 10B, 1T">
                    <div class="chip-stack">
                        <span class="chip" onclick="document.getElementById('llbmAmount').value='100M'">100M</span>
                        <span class="chip" onclick="document.getElementById('llbmAmount').value='1B'">1B</span>
                        <span class="chip" onclick="document.getElementById('llbmAmount').value='10B'">10B</span>
                        <span class="chip" onclick="document.getElementById('llbmAmount').value='50B'">50B</span>
                        <span class="chip" onclick="document.getElementById('llbmAmount').value='1T'">1T</span>
                    </div>
                </div>

                <button onclick="contributeLLBM()" id="btnLLBM" class="btn-action">
                    💎 Send Contribution Now
                </button>
            </div>
        </div>

        <!-- TAB 7: TRANSFERS TABLE -->
        <div id="tab-history" class="tab-pane">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📋 Recent Financial Operations</div>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Sender</th>
                                <th>Recipient</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="historyTbody">
                            <tr><td colspan="5" style="text-align: center;">Loading history…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ────────────────────────────────────────────────────────────────────────
             REAL-TIME TERMINAL CONSOLE LOG (Always visible at bottom)
             ──────────────────────────────────────────────────────────────────────── -->
        <div class="terminal-card">
            <div class="terminal-header">
                <div class="terminal-title">
                    <span class="term-pulse"></span>
                    <span>HEISDAEMON REAL-TIME CONSOLE STREAM</span>
                </div>
                <div class="term-controls">
                    <span class="term-chip active" onclick="setLogFilter('all', this)">ALL</span>
                    <span class="term-chip" onclick="setLogFilter('transfer', this)">TRANSFERS</span>
                    <span class="term-chip" onclick="setLogFilter('batch', this)">BATCHES</span>
                    <span class="term-chip" onclick="setLogFilter('system', this)">ENGINE</span>
                    <span class="term-chip" onclick="toggleAutoScroll(this)" id="btnAutoScroll">SCROLL: ON</span>
                    <span class="term-chip" onclick="clearLocalLogs()">CLEAR</span>
                </div>
            </div>
            <div class="terminal-body" id="termBody">
                <div class="log-row">
                    <span class="log-time">[Init]</span>
                    <span class="log-tag engine">SYSTEM</span>
                    <span class="log-msg">Connecting to HeisDaemon live event pipeline…</span>
                </div>
            </div>
        </div>

    </div>

    <!-- ────────────────────────────────────────────────────────────────────────
         ROLLING BEAUTIFUL PROGRESS MODAL
         ──────────────────────────────────────────────────────────────────────── -->
    <div id="progressModal" class="modal-overlay">
        <div class="progress-card">
            
            <!-- Rolling Orbital Glowing Spinner -->
            <div class="orbital-wrapper">
                <div class="orbital-ring outer"></div>
                <div class="orbital-ring inner"></div>
                <div class="orbital-core" id="progPercent">0%</div>
            </div>

            <div class="progress-title" id="progTitle">⚡ Operation in Progress</div>
            <div class="progress-subtitle" id="progSubtitle">Communicating with LagosLife server…</div>

            <!-- Rolling Striped Progress Bar -->
            <div class="progress-bar-track">
                <div class="progress-bar-fill" id="progBarFill" style="width: 0%;"></div>
            </div>

            <!-- Progress Metrics -->
            <div class="progress-stats-grid" id="progMetrics">
                <div class="p-stat">
                    <div class="p-stat-label">Chunks</div>
                    <div class="p-stat-val" id="pChunks">0 / 1</div>
                </div>
                <div class="p-stat">
                    <div class="p-stat-label">Sent</div>
                    <div class="p-stat-val" id="pSent">₦0</div>
                </div>
                <div class="p-stat">
                    <div class="p-stat-label">Cooldown</div>
                    <div class="p-stat-val" id="pCooldown">Ready</div>
                </div>
            </div>

            <div class="prog-actions-row">
                <button onclick="cancelActiveBatch()" id="btnProgCancel" class="btn-prog-cancel">🚫 Cancel Batch</button>
                <button onclick="closeProgressModal()" class="btn-prog-min">Minimize Window</button>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="modal-overlay">
        <div class="login-card">
            <div class="card-header" style="margin-bottom: 16px;">
                <div class="card-title">🔑 Connect LagosLife Account</div>
                <button onclick="closeLoginModal()" style="background: none; border: none; color: #fff; font-size: 20px; cursor: pointer;">&times;</button>
            </div>
            <div class="form-group">
                <label>LagosLife Username:</label>
                <input type="text" id="modalUsername" class="input-control" placeholder="username">
            </div>
            <div class="form-group">
                <label>LagosLife Password:</label>
                <input type="password" id="modalPassword" class="input-control" placeholder="password">
            </div>
            <button onclick="submitLogin()" id="btnLoginSubmit" class="btn-action">
                🚀 Authenticate Account
            </button>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast"></div>

    <script>
        let portalData = { accounts: [], mansion_catalog: [] };
        let activeJobId = null;
        let batchPollInterval = null;
        let logPollInterval = null;
        let autoScrollLogs = true;
        let currentLogFilter = 'all';
        let rawLogsCache = [];
        let cooldownSeconds = 0;
        let cooldownTimer = null;

        async function fetchAPI(action, data = {}) {
            try {
                const res = await fetch('web_api.php?action=' + encodeURIComponent(action), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                return await res.json();
            } catch (e) {
                return { ok: false, error: e.message };
            }
        }

        function showToast(msg, isSuccess = true) {
            const t = document.getElementById('toast');
            t.className = isSuccess ? 'success' : 'error';
            t.innerHTML = (isSuccess ? '✅ ' : '❌ ') + msg;
            t.style.display = 'flex';
            setTimeout(() => { t.style.display = 'none'; }, 4500);
        }

        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-' + tabId).classList.add('active');
            if (btn) btn.classList.add('active');
        }

        async function refreshData() {
            const res = await fetchAPI('get_data');
            if (!res.ok) {
                showToast(res.error || 'Failed to load portal data', false);
                return;
            }
            portalData = res;
            renderAll();
        }

        function renderAll() {
            // Stats
            document.getElementById('statTotalWealth').innerText = portalData.total_wealth_srt || '₦0';
            document.getElementById('statAccountsCount').innerText = (portalData.accounts || []).length;

            // Render Accounts
            const listEl = document.getElementById('accountsList');
            const selects = [
                document.getElementById('sendFromSelect'),
                document.getElementById('payrollFromSelect'),
                document.getElementById('houseAccountSelect'),
                document.getElementById('quiloxAccountSelect'),
                document.getElementById('llbmFromSelect'),
                document.getElementById('pinAccountSelect'),
            ];

            selects.forEach(s => s.innerHTML = '');

            if (!portalData.accounts || portalData.accounts.length === 0) {
                listEl.innerHTML = '<p style="color: var(--text-muted); font-size: 13px;">No accounts connected yet. Tap <b>+ Connect Account</b> to get started.</p>';
                return;
            }

            let accHtml = '';
            portalData.accounts.forEach(acc => {
                const statusClass = acc.is_authenticated ? '' : 'offline';
                const statusText = acc.is_authenticated ? '🟢 Active' : '🔴 Expired';

                accHtml += `
                    <div class="account-card">
                        <div class="acc-top">
                            <div>
                                <div class="acc-name">${acc.ll_name}</div>
                                <div class="acc-handle">@${acc.ll_username} &middot; <span style="color: var(--text-muted); font-size: 11px;">ID: ${acc.ll_user_id}</span></div>
                            </div>
                            <span class="status-badge ${statusClass}">${statusText}</span>
                        </div>
                        <div class="acc-balance">${acc.balance_fmt}</div>
                        <div class="acc-footer">
                            <span>PIN: ${acc.has_pin ? 'Protected ✅' : 'Not Set'}</span>
                            <button onclick="refreshSingleBalance('${acc.chat_id}')" class="chip">🔄 Refresh Balance</button>
                        </div>
                    </div>
                `;

                // Add to selects
                selects.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = acc.chat_id;
                    opt.text = `@${acc.ll_username} (${acc.balance_short})`;
                    s.appendChild(opt);
                });
            });

            listEl.innerHTML = accHtml;
            updateSenderBalance();

            // Render Mansions Catalog
            const catGrid = document.getElementById('catalogGrid');
            if (portalData.mansion_catalog) {
                let catHtml = '';
                for (const [key, tpl] of Object.entries(portalData.mansion_catalog)) {
                    catHtml += `
                        <div class="mansion-card">
                            <div>
                                <div class="mansion-name">${tpl.name}</div>
                                <div class="mansion-meta">Owner: @${tpl.owner} &middot; ${tpl.items} Elements</div>
                                <div class="mansion-desc">${tpl.description}</div>
                            </div>
                            <button onclick="applyCatalogMansion('${key}')" class="btn-action" style="padding: 10px 16px; font-size: 13px;">
                                🚀 Install (${tpl.cost === 0 ? 'Free' : '₦' + tpl.cost})
                            </button>
                        </div>
                    `;
                }
                catGrid.innerHTML = catHtml;
            }

            // Render History
            const histTbody = document.getElementById('historyTbody');
            if (portalData.recent_transfers && portalData.recent_transfers.length > 0) {
                let hHtml = '';
                portalData.recent_transfers.forEach(tx => {
                    const statusColor = tx.status === 'success' ? '#10b981' : '#ef4444';
                    hHtml += `
                        <tr>
                            <td><b>@${tx.sender_ll_username}</b></td>
                            <td>@${tx.recipient_username}</td>
                            <td style="font-family: 'JetBrains Mono', monospace; font-weight: 700;">₦${Number(tx.amount).toLocaleString()}</td>
                            <td><span style="color: ${statusColor}; font-weight: 700;">${tx.status.toUpperCase()}</span></td>
                            <td style="color: var(--text-muted); font-size: 12px;">${tx.created_at}</td>
                        </tr>
                    `;
                });
                histTbody.innerHTML = hHtml;
            } else {
                histTbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No recorded transfers yet.</td></tr>';
            }
        }

        async function refreshSingleBalance(chatId) {
            showToast('Fetching live balance from LagosLife…');
            const res = await fetchAPI('refresh_balance', { chat_id: chatId });
            if (res.ok) {
                showToast(`Updated balance: ${res.balance_fmt}`);
                refreshData();
            } else {
                showToast(res.error || 'Failed to update balance', false);
            }
        }

        function updateSenderBalance() {
            const sel = document.getElementById('sendFromSelect');
            const chatId = sel.value;
            const acc = (portalData.accounts || []).find(a => a.chat_id === chatId);
            const note = document.getElementById('senderBalanceNote');
            if (acc) {
                note.innerText = `Available Funds: ${acc.balance_fmt}`;
            } else {
                note.innerText = '';
            }
        }

        function setSendAmount(val) {
            document.getElementById('sendAmount').value = val;
            previewChunking();
        }

        function previewChunking() {
            const raw = document.getElementById('sendAmount').value.trim().toLowerCase();
            const noteEl = document.getElementById('chunkPreviewNote');
            let mult = 1;
            if (raw.endsWith('k')) mult = 1000;
            else if (raw.endsWith('m')) mult = 1000000;
            else if (raw.endsWith('b')) mult = 1000000000;
            else if (raw.endsWith('t')) mult = 1000000000000;

            const num = parseFloat(raw);
            if (!isNaN(num) && num > 0) {
                const total = num * mult;
                if (total > 10000000000) {
                    const chunks = Math.ceil(total / 10000000000);
                    noteEl.style.display = 'block';
                    noteEl.innerHTML = `📦 <b>Auto-Chunking Active:</b> Transfers <b>₦${total.toLocaleString()}</b> across <b>${chunks} chunks</b> of ₦10B with 32s cooldown timing.`;
                    return;
                }
            }
            noteEl.style.display = 'none';
        }

        /* ────────────────────────────────────────────────────────────────────────
           PROGRESS MODAL CONTROLLERS (ROLLING BEAUTIFUL)
           ──────────────────────────────────────────────────────────────────────── */
        function openProgressModal(title, subtitle = 'Connecting to LagosLife nodes…') {
            document.getElementById('progTitle').innerText = title;
            document.getElementById('progSubtitle').innerText = subtitle;
            document.getElementById('progPercent').innerText = '0%';
            document.getElementById('progBarFill').style.width = '0%';
            document.getElementById('pChunks').innerText = '0 / 1';
            document.getElementById('pSent').innerText = '₦0';
            document.getElementById('pCooldown').innerText = 'Ready';
            document.getElementById('btnProgCancel').style.display = 'inline-block';
            document.getElementById('progressModal').style.display = 'flex';
        }

        function closeProgressModal() {
            document.getElementById('progressModal').style.display = 'none';
        }

        function setProgressState(percent, title, subtitle, chunksStr = null, sentStr = null, cooldownStr = null) {
            document.getElementById('progPercent').innerText = percent + '%';
            document.getElementById('progBarFill').style.width = Math.min(100, Math.max(0, percent)) + '%';
            if (title) document.getElementById('progTitle').innerText = title;
            if (subtitle) document.getElementById('progSubtitle').innerText = subtitle;
            if (chunksStr) document.getElementById('pChunks').innerText = chunksStr;
            if (sentStr) document.getElementById('pSent').innerText = sentStr;
            if (cooldownStr) document.getElementById('pCooldown').innerText = cooldownStr;
        }

        function startCooldownTracker(seconds) {
            if (cooldownTimer) clearInterval(cooldownTimer);
            cooldownSeconds = seconds;
            document.getElementById('pCooldown').innerText = cooldownSeconds + 's remaining';
            cooldownTimer = setInterval(() => {
                cooldownSeconds--;
                if (cooldownSeconds <= 0) {
                    clearInterval(cooldownTimer);
                    document.getElementById('pCooldown').innerText = 'Dispatched 🚀';
                } else {
                    document.getElementById('pCooldown').innerText = cooldownSeconds + 's remaining';
                }
            }, 1000);
        }

        function pollActiveBatch(jobId) {
            activeJobId = jobId;
            if (batchPollInterval) clearInterval(batchPollInterval);

            batchPollInterval = setInterval(async () => {
                const res = await fetchAPI('get_batch_status', { job_id: jobId });
                if (!res.ok || !res.job) {
                    clearInterval(batchPollInterval);
                    return;
                }

                const job = res.job;
                const pct = job.percent || 0;
                const chunksText = `${job.completed_chunks} / ${job.total_chunks}`;
                const sentText = job.sent_amount_srt || '₦0';

                if (job.status === 'cancelled') {
                    clearInterval(batchPollInterval);
                    setProgressState(pct, '🚫 Batch Cancelled', 'Batch was halted by user request.', chunksText, sentText, 'Halted');
                    document.getElementById('btnProgCancel').style.display = 'none';
                    refreshData();
                    return;
                }

                if (job.is_done) {
                    clearInterval(batchPollInterval);
                    setProgressState(100, '🎉 Batch Completed!', `Successfully sent ${job.sent_amount_fmt} across ${job.total_chunks} chunks.`, chunksText, sentText, 'Complete');
                    document.getElementById('btnProgCancel').style.display = 'none';
                    showToast(`Batch completed! Total ${sentText} dispatched.`);
                    refreshData();
                    return;
                }

                // In Progress
                const sub = `Dispersing chunk ${job.completed_chunks + 1} of ${job.total_chunks}…`;
                setProgressState(pct, '⚡ Processing Chunks…', sub, chunksText, sentText, 'Active');
            }, 2500);
        }

        async function cancelActiveBatch() {
            if (!activeJobId) {
                closeProgressModal();
                return;
            }
            if (confirm("Are you sure you want to cancel this batch? Current chunk will finalize and remaining chunks will halt.")) {
                const res = await fetchAPI('cancel_batch', { job_id: activeJobId });
                showToast(res.message || 'Cancellation requested.');
            }
        }

        /* ────────────────────────────────────────────────────────────────────────
           OPERATIONAL ACTIONS
           ──────────────────────────────────────────────────────────────────────── */
        async function executeTransfer() {
            const btn = document.getElementById('btnSendMoney');
            const senderChatId = document.getElementById('sendFromSelect').value;
            const recipient = document.getElementById('sendRecipient').value.trim();
            const amount = document.getElementById('sendAmount').value.trim();
            const note = document.getElementById('sendNote').value.trim();
            const pin = document.getElementById('sendPinOverride').value.trim();

            if (!recipient || !amount) {
                showToast('Please provide recipient username and amount', false);
                return;
            }

            openProgressModal('⚡ Executing Transfer…', `Dispatching funds to @${recipient.replace(/^@/, '')}…`);
            setProgressState(25, 'Connecting to Game Engine…', `Resolving @${recipient.replace(/^@/, '')}…`);

            btn.disabled = true;

            const res = await fetchAPI('send_money', {
                chat_id: senderChatId,
                recipient: recipient,
                amount: amount,
                note: note,
                pin: pin
            });

            btn.disabled = false;

            if (res.ok) {
                if (res.batch) {
                    // Mega multi-chunk batch
                    openProgressModal('📦 Mega Transfer Batch Active', `Sending ${amount} in ${res.total_chunks} chunks with 32s cooldown timing…`);
                    pollActiveBatch(res.job_id);
                } else {
                    // Single chunk instant success
                    setProgressState(100, '🎉 Transfer Successful!', res.message, '1 / 1', res.amount_srt, 'Done');
                    document.getElementById('btnProgCancel').style.display = 'none';
                    showToast(res.message);
                    document.getElementById('sendRecipient').value = '';
                    document.getElementById('sendAmount').value = '';
                    refreshData();
                }
            } else {
                setProgressState(0, '❌ Transfer Failed', res.error || 'Transfer declined by game server.', '0 / 1', '₦0', 'Failed');
                document.getElementById('btnProgCancel').style.display = 'none';
                showToast(res.error || 'Transfer failed', false);
            }
        }

        async function executePayroll() {
            const btn = document.getElementById('btnPayroll');
            const senderChatId = document.getElementById('payrollFromSelect').value;
            const list = document.getElementById('payrollList').value.trim();

            if (!list) {
                showToast('Please paste recipient list', false);
                return;
            }

            openProgressModal('👥 Resolving Payroll List…', 'Validating recipient usernames and splitting chunks…');
            setProgressState(15, 'Resolving Recipient IDs…', 'Connecting to LagosLife database…');

            btn.disabled = true;

            const res = await fetchAPI('batch_payroll', {
                chat_id: senderChatId,
                list: list
            });

            btn.disabled = false;

            if (res.ok) {
                openProgressModal('🚀 Payroll Batch Active', `Dispersing ${res.total_amount_srt} across ${res.total_chunks} chunks with 32s cooldown timing…`);
                document.getElementById('payrollList').value = '';
                pollActiveBatch(res.job_id);
            } else {
                setProgressState(0, '❌ Payroll Launch Failed', res.error || 'Could not initialize batch.', '0 / 0', '₦0', 'Failed');
                document.getElementById('btnProgCancel').style.display = 'none';
                showToast(res.error || 'Payroll launch failed', false);
            }
        }

        async function applyCatalogMansion(tplKey) {
            const chatId = document.getElementById('houseAccountSelect').value;
            openProgressModal('🏰 Installing Mansion…', 'Removing clutter and installing luxury elements…');
            setProgressState(50, 'Placing Furniture & Marble…', 'Clean-slate placement underway…');

            const res = await fetchAPI('beautify_template', {
                chat_id: chatId,
                template: tplKey
            });

            if (res.ok) {
                setProgressState(100, '🎉 Residence Beautified!', res.message, '1 / 1', 'Complete', 'Installed');
                document.getElementById('btnProgCancel').style.display = 'none';
                showToast(res.message);
                refreshData();
            } else {
                setProgressState(0, '❌ Beautification Failed', res.error, '0 / 1', 'Failed', 'Error');
                document.getElementById('btnProgCancel').style.display = 'none';
                showToast(res.error || 'Failed to install mansion', false);
            }
        }

        async function clonePlayerHouse() {
            const btn = document.getElementById('btnClonePlayer');
            const chatId = document.getElementById('houseAccountSelect').value;
            const target = document.getElementById('targetPlayerUser').value.trim();

            if (!target) {
                showToast('Enter player username to clone', false);
                return;
            }

            openProgressModal('🔍 Inspecting Residence…', `Knocking and reading house layout of @${target}…`);
            setProgressState(40, 'Scanning Room Coordinates…', `Capturing furniture items from @${target}…`);

            btn.disabled = true;

            const res = await fetchAPI('clone_player_house', {
                chat_id: chatId,
                target_user: target
            });

            btn.disabled = false;

            if (res.ok) {
                setProgressState(100, '🎉 Layout Cloned!', res.message, '1 / 1', 'Done', 'Installed');
                document.getElementById('btnProgCancel').style.display = 'none';
                showToast(res.message);
                document.getElementById('targetPlayerUser').value = '';
                refreshData();
            } else {
                setProgressState(0, '⚠️ Clone Notice', res.error, '0 / 1', 'Pending', 'Action Needed');
                document.getElementById('btnProgCancel').style.display = 'none';
                showToast(res.error || 'Could not clone player house', false);
            }
        }

        async function sweepQuilox() {
            const btn = document.getElementById('btnSweepQuilox');
            const chatId = document.getElementById('quiloxAccountSelect').value;

            openProgressModal('🪩 Sweeping Quilox Floor…', 'Scanning VIP dance floor for sprayed banknotes…');
            setProgressState(50, 'Scooping Banknotes…', 'Depositing collected cash into wallet…');

            btn.disabled = true;

            const res = await fetchAPI('quilox_sweep', { chat_id: chatId });

            btn.disabled = false;

            if (res.ok) {
                setProgressState(100, '🍸 Dance Floor Swept!', res.message, '1 / 1', res.grabbed_srt || '₦0', 'Claimed');
                document.getElementById('btnProgCancel').style.display = 'none';
                showToast(res.message);
                refreshData();
            } else {
                setProgressState(0, '❌ Sweep Failed', res.error, '0 / 1', '₦0', 'Error');
                document.getElementById('btnProgCancel').style.display = 'none';
                showToast(res.error || 'Floor sweep failed', false);
            }
        }

        async function teleportQuilox() {
            const chatId = document.getElementById('quiloxAccountSelect').value;
            showToast('Teleporting character to Quilox VIP entrance…');
            const res = await fetchAPI('quilox_teleport', { chat_id: chatId });
            showToast(res.message || (res.ok ? 'Teleported!' : res.error), res.ok);
        }

        async function claimQuilox() {
            const chatId = document.getElementById('quiloxAccountSelect').value;
            showToast('Depositing all claimed club cash…');
            const res = await fetchAPI('quilox_claim', { chat_id: chatId });
            showToast(res.message || (res.ok ? 'Claimed!' : res.error), res.ok);
            if (res.ok) refreshData();
        }

        async function contributeLLBM() {
            const btn = document.getElementById('btnLLBM');
            const chatId = document.getElementById('llbmFromSelect').value;
            const amount = document.getElementById('llbmAmount').value.trim();

            if (!amount) {
                showToast('Enter contribution amount', false);
                return;
            }

            openProgressModal('💎 Processing Contribution…', 'Sending funds to LLBM Movement (@LLBmovement)…');
            setProgressState(60, 'Transferring Contribution…', 'Notifying Telegram channel…');

            btn.disabled = true;

            const res = await fetchAPI('support_llbm', {
                chat_id: chatId,
                amount: amount
            });

            btn.disabled = false;

            if (res.ok) {
                setProgressState(100, '🎉 Contribution Sent!', res.message, '1 / 1', amount, 'Completed');
                document.getElementById('btnProgCancel').style.display = 'none';
                showToast(res.message);
                document.getElementById('llbmAmount').value = '';
                refreshData();
            } else {
                setProgressState(0, '❌ Contribution Failed', res.error, '0 / 1', 'Failed', 'Error');
                document.getElementById('btnProgCancel').style.display = 'none';
                showToast(res.error || 'Contribution failed', false);
            }
        }

        async function savePin() {
            const chatId = document.getElementById('pinAccountSelect').value;
            const pin = document.getElementById('pinInput').value.trim();

            if (!pin || pin.length < 4) {
                showToast('PIN must be 4 to 8 digits', false);
                return;
            }

            const res = await fetchAPI('set_pin', { chat_id: chatId, pin: pin });
            if (res.ok) {
                showToast(res.message);
                document.getElementById('pinInput').value = '';
                refreshData();
            } else {
                showToast(res.error || 'Failed to save PIN', false);
            }
        }

        function openLoginModal() {
            document.getElementById('loginModal').style.display = 'flex';
        }
        function closeLoginModal() {
            document.getElementById('loginModal').style.display = 'none';
        }

        async function submitLogin() {
            const btn = document.getElementById('btnLoginSubmit');
            const u = document.getElementById('modalUsername').value.trim();
            const p = document.getElementById('modalPassword').value.trim();

            if (!u || !p) {
                showToast('Username and password required', false);
                return;
            }

            btn.disabled = true;
            btn.innerText = '⏳ Authenticating…';

            const res = await fetchAPI('login', { username: u, password: p });

            btn.disabled = false;
            btn.innerText = '🚀 Authenticate Account';

            if (res.ok) {
                showToast(`Logged in as @${res.username}!`);
                closeLoginModal();
                document.getElementById('modalUsername').value = '';
                document.getElementById('modalPassword').value = '';
                refreshData();
            } else {
                showToast(res.error || 'Login failed', false);
            }
        }

        /* ────────────────────────────────────────────────────────────────────────
           REAL-TIME TERMINAL LOG STREAM
           ──────────────────────────────────────────────────────────────────────── */
        async function fetchLogs() {
            const res = await fetchAPI('get_logs');
            if (res.ok && res.logs) {
                rawLogsCache = res.logs;
                renderTerminalLogs();
            }
        }

        function setLogFilter(filter, el) {
            currentLogFilter = filter;
            document.querySelectorAll('.term-chip').forEach(c => c.classList.remove('active'));
            if (el) el.classList.add('active');
            renderTerminalLogs();
        }

        function toggleAutoScroll(el) {
            autoScrollLogs = !autoScrollLogs;
            el.innerText = autoScrollLogs ? 'SCROLL: ON' : 'SCROLL: OFF';
            el.style.color = autoScrollLogs ? 'var(--accent)' : 'var(--danger)';
        }

        function clearLocalLogs() {
            rawLogsCache = [];
            document.getElementById('termBody').innerHTML = '<div class="log-row"><span class="log-time">[Clear]</span> <span class="log-tag engine">SYSTEM</span> <span class="log-msg">Logs cleared by user. Streaming fresh events…</span></div>';
        }

        function renderTerminalLogs() {
            const tb = document.getElementById('termBody');
            if (!rawLogsCache || rawLogsCache.length === 0) return;

            let filtered = rawLogsCache;
            if (currentLogFilter !== 'all') {
                filtered = rawLogsCache.filter(l => l.type === currentLogFilter);
            }

            let html = '';
            filtered.forEach(l => {
                let tagClass = 'engine';
                if (l.type === 'transfer') tagClass = (l.badge === 'FAILED') ? 'failed' : 'transfer';
                else if (l.type === 'batch') tagClass = 'batch';
                else if (l.type === 'system') tagClass = 'engine';

                html += `
                    <div class="log-row">
                        <span class="log-time">[${l.time}]</span>
                        <span class="log-tag ${tagClass}">${l.badge}</span>
                        <span class="log-msg">${escapeHtml(l.text)}</span>
                    </div>
                `;
            });

            tb.innerHTML = html;

            if (autoScrollLogs) {
                tb.scrollTop = tb.scrollHeight;
            }
        }

        function escapeHtml(str) {
            return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Initialize on load
        window.addEventListener('DOMContentLoaded', () => {
            refreshData();
            fetchLogs();
            // Real-time polling
            setInterval(fetchLogs, 3000);
            setInterval(refreshData, 10000);
        });
    </script>
</body>
</html>
