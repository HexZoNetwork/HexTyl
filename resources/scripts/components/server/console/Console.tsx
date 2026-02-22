import React, { useEffect, useMemo, useRef, useState } from 'react';
import { ITerminalOptions, Terminal } from 'xterm';
import { FitAddon } from 'xterm-addon-fit';
import { SearchAddon } from 'xterm-addon-search';
import { SearchBarAddon } from 'xterm-addon-search-bar';
import { WebLinksAddon } from 'xterm-addon-web-links';
import { Unicode11Addon } from 'xterm-addon-unicode11';
import { ScrollDownHelperAddon } from '@/plugins/XtermScrollDownHelperAddon';
import createServerChatMessage from '@/api/server/chat/createServerChatMessage';
import createIdeSession from '@/api/server/createIdeSession';
import { httpErrorToHuman } from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { ServerContext } from '@/state/server';
import { usePermissions } from '@/plugins/usePermissions';
import { theme as th } from 'twin.macro';
import useEventListener from '@/plugins/useEventListener';
import { debounce } from 'debounce';
import { usePersistedState } from '@/plugins/usePersistedState';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import classNames from 'classnames';
import { ChevronDoubleRightIcon } from '@heroicons/react/solid';

import 'xterm/css/xterm.css';
import styles from './style.module.css';

const theme = {
    background: th`colors.black`.toString(),
    cursor: 'transparent',
    black: th`colors.black`.toString(),
    red: '#E54B4B',
    green: '#9ECE58',
    yellow: '#FAED70',
    blue: '#396FE2',
    magenta: '#BB80B3',
    cyan: '#2DDAFD',
    white: '#d0d0d0',
    brightBlack: 'rgba(255, 255, 255, 0.2)',
    brightRed: '#FF5370',
    brightGreen: '#C3E88D',
    brightYellow: '#FFCB6B',
    brightBlue: '#82AAFF',
    brightMagenta: '#C792EA',
    brightCyan: '#89DDFF',
    brightWhite: '#ffffff',
    selection: '#FAF089',
};

const terminalProps: ITerminalOptions = {
    disableStdin: true,
    cursorStyle: 'underline',
    allowTransparency: true,
    fontSize: 12,
    fontFamily: th('fontFamily.mono'),
    rows: 30,
    theme: theme,
};

export default () => {
    const TERMINAL_PRELUDE = '\u001b[1m\u001b[33mMakLoYapit@HexZ~ \u001b[0m';
    const ref = useRef<HTMLDivElement>(null);
    const terminal = useMemo(() => new Terminal({ ...terminalProps }), []);
    const fitAddon = new FitAddon();
    const searchAddon = new SearchAddon();
    const searchBar = new SearchBarAddon({ searchAddon });
    const webLinksAddon = new WebLinksAddon();
    const unicode11Addon = new Unicode11Addon();
    const scrollDownHelperAddon = new ScrollDownHelperAddon();
    const { connected, instance } = ServerContext.useStoreState((state) => state.socket);
    const [canSendCommands, canSendChat, canIdeConnect] = usePermissions(['control.console', 'chat.create', 'ide.connect']);
    const serverId = ServerContext.useStoreState((state) => state.server.data!.id);
    const serverUuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const [history, setHistory] = usePersistedState<string[]>(`${serverId}:command_history`, []);
    const [historyIndex, setHistoryIndex] = useState(-1);
    const [chatMenu, setChatMenu] = useState<{ x: number; y: number; text: string } | null>(null);
    const longPressTimerRef = useRef<number | null>(null);
    // SearchBarAddon has hardcoded z-index: 999 :(
    const zIndex = `
    .xterm-search-bar__addon {
        z-index: 10;
    }`;

    const handleConsoleOutput = (line: string, prelude = false) =>
        terminal.writeln((prelude ? TERMINAL_PRELUDE : '') + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');

    const handleTransferStatus = (status: string) => {
        switch (status) {
            // Sent by either the source or target node if a failure occurs.
            case 'failure':
                terminal.writeln(TERMINAL_PRELUDE + 'Transfer has failed.\u001b[0m');
                return;
        }
    };

    const handleDaemonErrorOutput = (line: string) =>
        terminal.writeln(
            TERMINAL_PRELUDE + '\u001b[1m\u001b[41m' + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m'
        );

    const handlePowerChangeEvent = (state: string) =>
        terminal.writeln(TERMINAL_PRELUDE + 'Server marked as ' + state + '...\u001b[0m');

    const getSelectedConsoleText = (): string => {
        const terminalSelection = terminal.getSelection()?.trim() || '';
        if (terminalSelection) return terminalSelection;

        const browserSelection = window.getSelection()?.toString().trim() || '';
        return browserSelection;
    };

    const sendSelectionToSharedChat = (selectedText: string) => {
        if (!canSendChat || !selectedText.trim()) return;

        const payload = [
            '[Console Bug Source]',
            `Text: ${selectedText.trim()}`,
            `URL: ${window.location.href}`,
            `Time: ${new Date().toISOString()}`,
        ].join('\n');

        createServerChatMessage(serverUuid, { body: payload })
            .then(() => {
                terminal.writeln(TERMINAL_PRELUDE + '\u001b[32mSent selected console text to shared chat.\u001b[0m');
            })
            .catch((error) => {
                terminal.writeln(
                    TERMINAL_PRELUDE +
                        '\u001b[31mFailed sending selected text to chat: ' +
                        httpErrorToHuman(error).replace(/(?:\r\n|\r|\n)$/im, '') +
                        '\u001b[0m'
                );
            });
    };

    const openIdeSession = () => {
        createIdeSession(serverUuid, { terminal: true, extensions: false })
            .then((session) => {
                if (session.launch_url) {
                    window.open(session.launch_url, '_blank', 'noopener,noreferrer');
                    terminal.writeln(TERMINAL_PRELUDE + '\u001b[32mIDE session launched.\u001b[0m');
                    return;
                }

                terminal.writeln(TERMINAL_PRELUDE + '\u001b[31mIDE launch URL is empty.\u001b[0m');
            })
            .catch((error) => {
                terminal.writeln(
                    TERMINAL_PRELUDE +
                        '\u001b[31mIDE connect failed: ' +
                        httpErrorToHuman(error).replace(/(?:\r\n|\r|\n)$/im, '') +
                        '\u001b[0m'
                );
            });
    };

    const handleCommandKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowUp') {
            const newIndex = Math.min(historyIndex + 1, history!.length - 1);

            setHistoryIndex(newIndex);
            e.currentTarget.value = history![newIndex] || '';

            // By default up arrow will also bring the cursor to the start of the line,
            // so we'll preventDefault to keep it at the end.
            e.preventDefault();
        }

        if (e.key === 'ArrowDown') {
            const newIndex = Math.max(historyIndex - 1, -1);

            setHistoryIndex(newIndex);
            e.currentTarget.value = history![newIndex] || '';
        }

        const command = e.currentTarget.value;
        if (e.key === 'Enter' && command.length > 0) {
            setHistory((prevHistory) => [command, ...prevHistory!].slice(0, 32));
            setHistoryIndex(-1);

            instance && instance.send('send command', command);
            e.currentTarget.value = '';
        }
    };

    useEffect(() => {
        if (connected && ref.current && !terminal.element) {
            terminal.loadAddon(fitAddon);
            terminal.loadAddon(searchAddon);
            terminal.loadAddon(searchBar);
            terminal.loadAddon(webLinksAddon);
            terminal.loadAddon(unicode11Addon);
            terminal.loadAddon(scrollDownHelperAddon);

            terminal.open(ref.current);

            // Activate Unicode 11 for proper emoji and special character width handling
            terminal.unicode.activeVersion = '11';

            fitAddon.fit();
            searchBar.addNewStyle(zIndex);

            // Add support for capturing keys
            terminal.attachCustomKeyEventHandler((e: KeyboardEvent) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
                    document.execCommand('copy');
                    return false;
                } else if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    searchBar.show();
                    return false;
                } else if (e.key === 'Escape') {
                    searchBar.hidden();
                }
                return true;
            });
        }
    }, [terminal, connected]);

    useEventListener(
        'resize',
        debounce(() => {
            if (terminal.element) {
                fitAddon.fit();
            }
        }, 100)
    );

    useEffect(() => {
        const listeners: Record<string, (s: string) => void> = {
            [SocketEvent.STATUS]: handlePowerChangeEvent,
            [SocketEvent.CONSOLE_OUTPUT]: handleConsoleOutput,
            [SocketEvent.INSTALL_OUTPUT]: handleConsoleOutput,
            [SocketEvent.TRANSFER_LOGS]: handleConsoleOutput,
            [SocketEvent.TRANSFER_STATUS]: handleTransferStatus,
            [SocketEvent.DAEMON_MESSAGE]: (line) => handleConsoleOutput(line, true),
            [SocketEvent.DAEMON_ERROR]: handleDaemonErrorOutput,
        };

        if (connected && instance) {
            // Do not clear the console if the server is being transferred.
            if (!isTransferring) {
                terminal.clear();
            }

            Object.keys(listeners).forEach((key: string) => {
                instance.addListener(key, listeners[key]);
            });
            instance.send(SocketRequest.SEND_LOGS);
        }

        return () => {
            if (instance) {
                Object.keys(listeners).forEach((key: string) => {
                    instance.removeListener(key, listeners[key]);
                });
            }
        };
    }, [connected, instance]);

    useEffect(() => {
        const closeMenu = () => setChatMenu(null);
        window.addEventListener('click', closeMenu);
        window.addEventListener('touchstart', closeMenu);

        return () => {
            window.removeEventListener('click', closeMenu);
            window.removeEventListener('touchstart', closeMenu);
        };
    }, []);

    return (
        <div className={classNames(styles.terminal, 'relative')}>
            <SpinnerOverlay visible={!connected} size={'large'} />
            <div
                className={classNames(styles.container, styles.overflows_container, { 'rounded-b': !canSendCommands })}
                onContextMenu={(event) => {
                    if (!canSendChat) return;
                    const selectedText = getSelectedConsoleText();
                    if (!selectedText) return;

                    event.preventDefault();
                    setChatMenu({
                        x: event.clientX,
                        y: event.clientY,
                        text: selectedText,
                    });
                }}
                onTouchStart={() => {
                    if (!canSendChat) return;
                    if (longPressTimerRef.current) window.clearTimeout(longPressTimerRef.current);
                    longPressTimerRef.current = window.setTimeout(() => {
                        const selectedText = getSelectedConsoleText();
                        if (selectedText) {
                            sendSelectionToSharedChat(selectedText);
                        }
                    }, 650);
                }}
                onTouchEnd={() => {
                    if (longPressTimerRef.current) {
                        window.clearTimeout(longPressTimerRef.current);
                        longPressTimerRef.current = null;
                    }
                }}
                onTouchCancel={() => {
                    if (longPressTimerRef.current) {
                        window.clearTimeout(longPressTimerRef.current);
                        longPressTimerRef.current = null;
                    }
                }}
            >
                <div className={'h-full'}>
                    <div id={styles.terminal} ref={ref} />
                </div>
                {chatMenu && (
                    <button
                        type={'button'}
                        className={'fixed z-50 rounded bg-gray-900 border border-cyan-500/60 text-cyan-200 text-xs px-2 py-1 hover:bg-gray-800'}
                        style={{ left: chatMenu.x, top: chatMenu.y }}
                        onClick={(event) => {
                            event.stopPropagation();
                            sendSelectionToSharedChat(chatMenu.text);
                            setChatMenu(null);
                        }}
                    >
                        Send to Shared Chat
                    </button>
                )}
            </div>
            {canSendCommands && (
                <div className={classNames('relative', styles.overflows_container)}>
                    <input
                        className={classNames('peer', styles.command_input)}
                        type={'text'}
                        placeholder={'Type a command...'}
                        aria-label={'Console command input.'}
                        disabled={!instance || !connected}
                        onKeyDown={handleCommandKeyDown}
                        autoCorrect={'off'}
                        autoCapitalize={'none'}
                    />
                    <div
                        className={classNames(
                            'text-gray-100 peer-focus:text-gray-50 peer-focus:animate-pulse',
                            styles.command_icon
                        )}
                    >
                        <ChevronDoubleRightIcon className={'w-4 h-4'} />
                    </div>
                    {canIdeConnect && (
                        <button
                            type={'button'}
                            className={'absolute right-2 top-1/2 -translate-y-1/2 rounded bg-blue-600 px-2 py-1 text-[11px] text-white hover:bg-blue-500'}
                            onClick={openIdeSession}
                        >
                            Open VSCode
                        </button>
                    )}
                </div>
            )}
        </div>
    );
};
