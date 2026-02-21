<!DOCTYPE html>
<html>
<head>
    <title>Systemctl Controller</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.min.css">
    <style>
        body {
            background: #050607;
            color: #d7ffe1;
            font-family: 'Courier New', monospace;
            padding: 16px;
        }
        .term-shell {
            border: 1px solid #233128;
            border-radius: 8px;
            background: #020703;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }
        #terminal {
            height: 68vh;
            min-height: 420px;
            padding: 10px 12px;
        }
        .term-head {
            padding: 9px 12px;
            border-bottom: 1px solid #1f2b24;
            font-size: 12px;
            color: #9ec8aa;
            background: linear-gradient(180deg, #0a100c 0%, #070c09 100%);
        }
        .term-foot {
            padding: 8px 12px;
            border-top: 1px solid #1f2b24;
            font-size: 11px;
            color: #7ca487;
            background: #060b08;
        }
    </style>
</head>
<body>
    <h3 style="margin:0 0 8px;">HexZo {{ $hexzShellUser ?? 'shell' }} Shell</h3>
    <div class="term-shell">
        <div class="term-head" id="termHead">Terminal token mode active. Session runs as OS user: {{ $hexzShellUser ?? 'shell' }}.</div>
        <div id="terminal"></div>
        <div class="term-foot">Interactive mode: supports y/n prompts, arrows, Ctrl keys, and full-screen terminal apps.</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
    <script>
        const terminalEl = document.getElementById('terminal');
        const termHead = document.getElementById('termHead');
        const token = @json($hexzToken ?? '');
        const term = new Terminal({
            cursorBlink: true,
            convertEol: true,
            allowProposedApi: false,
            fontFamily: '"Cascadia Mono","JetBrains Mono","Fira Code","Consolas","Courier New",monospace',
            fontSize: 14,
            lineHeight: 1.22,
            theme: {
                background: '#020703',
                foreground: '#d7ffe1',
                cursor: '#7bffa6',
                black: '#001100',
                brightBlack: '#4e6654',
                green: '#6bff9a',
                brightGreen: '#9effbf',
            },
        });
        term.open(terminalEl);
        term.focus();
        term.writeln('Welcome, HexZo. System ready...');
        term.writeln('');

        let syncing = false;
        let lastScreen = '';
        let stopped = false;

        const encodeBase64 = (text) => {
            const bytes = new TextEncoder().encode(text);
            let binary = '';
            for (let i = 0; i < bytes.length; i++) {
                binary += String.fromCharCode(bytes[i]);
            }
            return btoa(binary);
        };

        const sendInput = async (data) => {
            if (stopped) return;
            const payload = encodeBase64(data);
            try {
                await fetch(`/hexz/input?token=${encodeURIComponent(token)}&d=${encodeURIComponent(payload)}`, {
                    method: 'GET',
                    cache: 'no-store',
                    credentials: 'same-origin',
                });
            } catch (_err) {}
        };

        term.onData((data) => {
            sendInput(data);
        });

        const syncScreen = async () => {
            if (syncing || stopped) return;
            syncing = true;
            try {
                const response = await fetch(`/hexz/snapshot?token=${encodeURIComponent(token)}`, {
                    method: 'GET',
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                const data = await response.json();
                if (!data || data.success !== true) {
                    return;
                }

                if (typeof data.prompt === 'string' && data.prompt.length > 0) {
                    termHead.textContent = `Interactive shell active: ${data.prompt}`;
                }

                const nextScreen = String(data.screen || '');
                if (nextScreen !== lastScreen) {
                    lastScreen = nextScreen;
                    term.clear();
                    term.write(nextScreen.replace(/\n/g, '\r\n'));
                }
            } catch (_err) {
                // ignore intermittent sync errors
            } finally {
                syncing = false;
            }
        };

        const timer = setInterval(syncScreen, 120);
        syncScreen();

        window.addEventListener('beforeunload', () => {
            stopped = true;
            clearInterval(timer);
        });
    </script>
</body>
</html>
