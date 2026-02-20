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
    <h3>HexZo@Root: ~ Second Shell</h3>
    <div id="terminal">
        <div>Welcome, HexZo. System ready...</div>
    </div>
    <div id="input-area">
        <span class="prompt">root#</span>
        <input type="text" id="cmd-input" autofocus autocomplete="off">
    </div>

    <script>
        const input = document.getElementById('cmd-input');
        const terminal = document.getElementById('terminal');

        input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                const cmd = this.value;
                this.value = '';
                
                // Print command ke screen
                terminal.innerHTML += `<div><span style="color:#ff00ff">root#</span> ${cmd}</div>`;
                
                // Source SSE
                const source = new EventSource(`/hexz/stream?cmd=${encodeURIComponent(cmd)}`);
                
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