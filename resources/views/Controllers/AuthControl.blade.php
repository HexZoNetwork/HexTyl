<!DOCTYPE html>
<html>
<head>
    <title>Systemctl Controller</title>
    <style>
        body {
            background: #000;
            color: #00ff66;
            font-family: 'Courier New', monospace;
            padding: 20px;
        }
        #terminal {
            border: 1px solid #253427;
            background: #020702;
            height: 520px;
            overflow-y: auto;
            margin-bottom: 10px;
            padding: 12px;
            white-space: pre-wrap;
            line-height: 1.45;
            font-size: 14px;
        }
        #input-area {
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #1d2a1f;
            background: #020702;
            padding: 8px 10px;
        }
        #cmd-input {
            background: transparent;
            border: none;
            color: #b9ffc8;
            flex: 1;
            outline: none;
            font-family: inherit;
            font-size: 15px;
        }
        .prompt {
            color: #ff61d8;
            white-space: nowrap;
        }
        .hint {
            margin-top: 8px;
            color: #6f9f77;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <h3>HexZo@{{ $hexzShellUser ?? 'shell' }} Shell</h3>
    <div style="margin-bottom:8px;color:#aaaaaa;font-size:12px;">
        Terminal token mode active. Session runs as OS user: {{ $hexzShellUser ?? 'shell' }}.
    </div>
    <div id="terminal">Welcome, HexZo. System ready...
</div>
    <div id="input-area">
        <span class="prompt" id="prompt-text">{{ $hexzPrompt ?? 'shell#' }}</span>
        <input type="text" id="cmd-input" autofocus autocomplete="off">
    </div>
    <div class="hint">Tip: history pakai ↑/↓, <code>cd -</code> sudah aktif, alias <code>la</code> dan <code>ll</code> aktif.</div>

    <script>
        const input = document.getElementById('cmd-input');
        const terminal = document.getElementById('terminal');
        const promptEl = document.getElementById('prompt-text');
        const token = @json($hexzToken ?? '');
        let prompt = @json($hexzPrompt ?? 'shell#');
        let busy = false;
        const history = [];
        let historyIndex = -1;

        const write = (text) => {
            terminal.textContent += text;
            terminal.scrollTop = terminal.scrollHeight;
        };

        const setPrompt = (next) => {
            prompt = next || prompt;
            promptEl.textContent = prompt;
        };

        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!history.length) return;
                historyIndex = historyIndex <= 0 ? 0 : historyIndex - 1;
                input.value = history[historyIndex];
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!history.length) return;
                if (historyIndex >= history.length - 1) {
                    historyIndex = history.length;
                    input.value = '';
                } else {
                    historyIndex += 1;
                    input.value = history[historyIndex] || '';
                }
                return;
            }

            if (e.key !== 'Enter') {
                return;
            }

            e.preventDefault();
            if (busy) return;

            const cmd = this.value;
            this.value = '';
            if (!cmd.trim()) {
                write(`${prompt} \n`);
                return;
            }

            history.push(cmd);
            historyIndex = history.length;
            busy = true;
            input.disabled = true;

            const source = new EventSource(`/hexz/stream?cmd=${encodeURIComponent(cmd)}&token=${encodeURIComponent(token)}`);

            source.onmessage = function(event) {
                let payload = null;
                try {
                    payload = JSON.parse(event.data);
                } catch (_err) {
                    write(event.data);
                    return;
                }

                if (payload.type === 'output' && typeof payload.chunk === 'string') {
                    write(payload.chunk);
                }

                if (payload.type === 'cwd') {
                    setPrompt(payload.prompt);
                }

                if (payload.type === 'done') {
                    source.close();
                    busy = false;
                    input.disabled = false;
                    input.focus();
                }
            };

            source.onerror = function() {
                source.close();
                busy = false;
                input.disabled = false;
                write('[stream closed]\n');
                input.focus();
            };
        });

        window.addEventListener('load', () => {
            setPrompt(prompt);
            input.focus();
        });
    </script>
</body>
</html>
