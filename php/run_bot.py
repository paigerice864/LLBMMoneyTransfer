import time
import json
import urllib.request
import ssl
import subprocess
import os
import sys

# Ensure UTF-8 output on Windows terminal
if sys.platform == 'win32':
    try:
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
        sys.stderr.reconfigure(encoding='utf-8', errors='replace')
    except Exception:
        pass

BOT_TOKEN = "8929717139:AAGwizYA0iz0k9yKi4WCXvo233EL260GGsQ"
PHP_SCRIPT = r"c:\xampp\htdocs\lagoslife\llbm_php_bot\webhook.php"

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

def main():
    print(">>> HeisDaemon Bot Runner Started!", flush=True)
    print(f">>> Polling @HeisdaemonBot and executing {PHP_SCRIPT}", flush=True)
    offset = 0

    while True:
        try:
            url = f"https://api.telegram.org/bot{BOT_TOKEN}/getUpdates?timeout=25"
            if offset != 0:
                url += f"&offset={offset}"

            req = urllib.request.Request(url, headers={"User-Agent": "HeisDaemon-Daemon/1.0"})
            with urllib.request.urlopen(req, context=ctx, timeout=35) as resp:
                data = json.loads(resp.read().decode("utf-8"))

            if data.get("ok") and data.get("result"):
                for update in data["result"]:
                    up_id = update["update_id"]
                    offset = up_id + 1

                    user_info = ""
                    text_info = ""
                    if "message" in update:
                        m = update["message"]
                        u = m.get("from", {})
                        user_info = f"@{u.get('username', 'no_user')} ({u.get('id')})"
                        text_info = m.get("text", "[media/action]")
                    elif "callback_query" in update:
                        cb = update["callback_query"]
                        u = cb.get("from", {})
                        user_info = f"@{u.get('username', 'no_user')} ({u.get('id')})"
                        text_info = f"cb:{cb.get('data')}"

                    t0 = time.time()
                    payload = json.dumps(update)

                    # Execute local PHP webhook directly via stdin
                    proc = subprocess.run(
                        ["php", PHP_SCRIPT],
                        input=payload,
                        text=True,
                        encoding="utf-8",
                        capture_output=True,
                        timeout=30
                    )

                    duration_ms = int((time.time() - t0) * 1000)

                    if proc.returncode == 0:
                        print(f"[OK] [{time.strftime('%H:%M:%S')}] Up #{up_id} from {user_info}: \"{text_info}\" processed in {duration_ms}ms", flush=True)
                    else:
                        print(f"[ERR] [{time.strftime('%H:%M:%S')}] Up #{up_id} Error: {proc.stderr.strip()}", flush=True)

        except Exception as e:
            # print(f"Polling loop error: {e}", flush=True)
            time.sleep(2)

if __name__ == "__main__":
    main()
