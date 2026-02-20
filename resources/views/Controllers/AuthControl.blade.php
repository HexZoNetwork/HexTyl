<!DOCTYPE html>
<html>
<head>
    <title>Systemctl Controller</title>
    <style>
        body { background: #000; color: #00ff00; font-family: 'Courier New', monospace; padding: 20px; }
        #terminal { border: 1px solid #333; height: 500px; overflow-y: auto; margin-bottom: 10px; padding: 10px; }
        #input-area { display: flex; }
        input { background: #000; border: none; color: #00ff00; flex: 1; outline: none; font-family: inherit; font-size: 1.1em;}
        .prompt { color: #ff00ff; margin-right: 10px; }
    </style>
</head>
<body>
    <h3>HexZo@{{ $hexzShellUser ?? 'shell' }}: ~ Shell</h3>
    <div style="margin-bottom:8px;color:#aaaaaa;font-size:12px;">
        Terminal token mode active. Session runs as OS user: {{ $hexzShellUser ?? 'shell' }}.
    </div>
    <div id="terminal">
        <div>Welcome, HexZo. System ready...</div>
    </div>
    <div id="input-area">
        <span class="prompt">{{ $hexzPrompt ?? 'shell#' }}</span>
        <input type="text" id="cmd-input" autofocus autocomplete="off">
    </div>

    <script>
        const input = document.getElementById('cmd-input');
        const terminal = document.getElementById('terminal');
        const token = @json($hexzToken ?? '');
        const prompt = @json($hexzPrompt ?? 'shell#');

        input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                const cmd = this.value;
                this.value = '';
                terminal.innerHTML += `<div><span style="color:#ff00ff">${prompt}</span> ${cmd}</div>`;
                const source = new EventSource(`/hexz/stream?cmd=${encodeURIComponent(cmd)}&token=${encodeURIComponent(token)}`);
                
                source.onmessage = function(event) {
                    if (event.data.includes("[Finished]")) {
                        source.close();
                    }
                    terminal.innerHTML += event.data;
                    terminal.scrollTop = terminal.scrollHeight;
                };

                source.onerror = function() {
                    source.close();
                };
            }
        });
    </script>
</body>
</html>
