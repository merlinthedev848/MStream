import { ref, shallowRef } from "vue";
import { useNotify } from "~/components/Common/Toasts/useNotify.ts";
import createRequiredInjectionState from "~/functions/createRequiredInjectionState.ts";
import { useTranslate } from "~/vendor/gettext";

export interface WebcasterProps {
    baseUri: string;
}

export interface WebcasterMetadata {
    title: string;
    artist: string;
}

const MAX_RING_BUFFER_FRAMES = 64;
const MAX_RING_BUFFER_BYTES = 2 * 1024 * 1024; // 2 MB hard ceiling
const MAX_SOCKET_BUFFERED_AMOUNT = 1024 * 1024; // 1 MB WebSocket send buffer ceiling
const HEARTBEAT_INTERVAL_MS = 15000;
const BASE_RECONNECT_DELAY_MS = 1000;
const MAX_RECONNECT_DELAY_MS = 15000;
const MAX_RECONNECT_ATTEMPTS = 8;

export const [useProvideWebcaster, useInjectWebcaster] =
    createRequiredInjectionState((props: WebcasterProps) => {
        const { baseUri } = props;

        const { notifySuccess, notifyError } = useNotify();
        const { $gettext } = useTranslate();

        const metadata = shallowRef<WebcasterMetadata | null>(null);
        const isConnected = ref(false);

        let socket: WebSocket | null = null;
        let intentionalClose = false;
        let reconnectAttempts = 0;
        let reconnectTimer: ReturnType<typeof setTimeout> | null = null;
        let heartbeatTimer: ReturnType<typeof setInterval> | null = null;

        // Bounded ring buffer for audio frames to prevent V8 heap exhaustion
        const frameQueue: ArrayBuffer[] = [];
        let queuedBytes = 0;

        const clearRingBuffer = () => {
            frameQueue.length = 0;
            queuedBytes = 0;
        };

        const enqueueFrame = (frame: ArrayBuffer) => {
            if (frame.byteLength > MAX_RING_BUFFER_BYTES) {
                return;
            }

            while (
                frameQueue.length >= MAX_RING_BUFFER_FRAMES ||
                queuedBytes + frame.byteLength > MAX_RING_BUFFER_BYTES
            ) {
                const dropped = frameQueue.shift();
                if (dropped) {
                    queuedBytes -= dropped.byteLength;
                } else {
                    break;
                }
            }

            frameQueue.push(frame);
            queuedBytes += frame.byteLength;
        };

        const flushRingBuffer = () => {
            if (
                !socket ||
                socket.readyState !== WebSocket.OPEN ||
                !isConnected.value
            ) {
                return;
            }

            while (
                frameQueue.length > 0 &&
                socket.bufferedAmount < MAX_SOCKET_BUFFERED_AMOUNT
            ) {
                const nextFrame = frameQueue.shift();
                if (!nextFrame) {
                    break;
                }
                queuedBytes -= nextFrame.byteLength;
                socket.send(nextFrame);
            }
        };

        const clearTimers = () => {
            if (reconnectTimer !== null) {
                clearTimeout(reconnectTimer);
                reconnectTimer = null;
            }
            if (heartbeatTimer !== null) {
                clearInterval(heartbeatTimer);
                heartbeatTimer = null;
            }
        };

        const sendMetadata = (data: WebcasterMetadata) => {
            metadata.value = data;

            if (
                isConnected.value &&
                socket &&
                socket.readyState === WebSocket.OPEN
            ) {
                socket.send(
                    JSON.stringify({
                        type: "metadata",
                        data,
                    }),
                );
            }
        };

        const connect = (
            mediaRecorder: MediaRecorder,
            username: string | null = null,
            password: string | null = null,
        ) => {
            intentionalClose = false;
            clearTimers();

            const openSocket = () => {
                socket = new WebSocket(baseUri, "webcast");

                const hello: Record<string, string> = {
                    mime: mediaRecorder.mimeType,
                };

                if (null !== username) {
                    hello.user = username;
                }
                if (null !== password) {
                    hello.password = password;
                }

                socket.onopen = () => {
                    if (!socket) {
                        return;
                    }

                    socket.send(
                        JSON.stringify({
                            type: "hello",
                            data: hello,
                        }),
                    );

                    isConnected.value = true;
                    const wasReconnect = reconnectAttempts > 0;
                    reconnectAttempts = 0;

                    flushRingBuffer();

                    if (heartbeatTimer !== null) {
                        clearInterval(heartbeatTimer);
                    }
                    heartbeatTimer = setInterval(() => {
                        if (
                            socket &&
                            socket.readyState === WebSocket.OPEN &&
                            isConnected.value &&
                            metadata.value !== null &&
                            socket.bufferedAmount < MAX_SOCKET_BUFFERED_AMOUNT
                        ) {
                            flushRingBuffer();
                        }
                    }, HEARTBEAT_INTERVAL_MS);

                    // Timeout as Liquidsoap won't return any success/failure message, so the only
                    // way we know if we're still connected is to set a timer.
                    setTimeout(() => {
                        if (
                            isConnected.value &&
                            socket &&
                            socket.readyState === WebSocket.OPEN
                        ) {
                            if (!wasReconnect) {
                                notifySuccess($gettext("Web DJ connected!"));
                            }

                            if (metadata.value !== null) {
                                socket.send(
                                    JSON.stringify({
                                        type: "metadata",
                                        data: metadata.value,
                                    }),
                                );
                            }
                        }
                    }, 1000);
                };

                socket.onerror = () => {
                    if (reconnectAttempts === 0) {
                        notifyError(
                            $gettext(
                                "An error occurred with the Web DJ socket.",
                            ),
                        );
                    }
                };

                socket.onclose = () => {
                    isConnected.value = false;
                    if (heartbeatTimer !== null) {
                        clearInterval(heartbeatTimer);
                        heartbeatTimer = null;
                    }

                    if (
                        !intentionalClose &&
                        mediaRecorder.state === "recording" &&
                        reconnectAttempts < MAX_RECONNECT_ATTEMPTS
                    ) {
                        const expDelay = Math.min(
                            MAX_RECONNECT_DELAY_MS,
                            BASE_RECONNECT_DELAY_MS * 2 ** reconnectAttempts,
                        );
                        const jitter = Math.floor(Math.random() * 250);
                        reconnectAttempts += 1;

                        reconnectTimer = setTimeout(() => {
                            if (
                                !intentionalClose &&
                                mediaRecorder.state === "recording"
                            ) {
                                openSocket();
                            }
                        }, expDelay + jitter);
                    } else if (intentionalClose) {
                        clearRingBuffer();
                    }
                };
            };

            openSocket();

            mediaRecorder.ondataavailable = async (e: BlobEvent) => {
                if (e.data.size === 0) {
                    return;
                }
                const data = await e.data.arrayBuffer();
                if (
                    isConnected.value &&
                    socket &&
                    socket.readyState === WebSocket.OPEN &&
                    socket.bufferedAmount < MAX_SOCKET_BUFFERED_AMOUNT
                ) {
                    flushRingBuffer();
                    socket.send(data);
                } else if (!intentionalClose) {
                    enqueueFrame(data);
                }
            };

            mediaRecorder.onstop = () => {
                intentionalClose = true;
                clearTimers();
                clearRingBuffer();
                if (socket && socket.readyState === WebSocket.OPEN) {
                    socket.close();
                }
                socket = null;
                isConnected.value = false;
            };
        };

        return {
            isConnected,
            connect,
            metadata,
            sendMetadata,
        };
    });
