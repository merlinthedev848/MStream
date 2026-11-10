<template>
    <audio
        v-if="isPlaying"
        ref="$audio"
        :title="current.title ?? undefined"
    />
</template>

<script setup lang="ts">
import { isString } from "es-toolkit/compat";
import Hls from "hls.js";
import { storeToRefs } from "pinia";
import {
    nextTick,
    onMounted,
    onScopeDispose,
    ref,
    useTemplateRef,
    watch,
} from "vue";
import { usePlayerStore } from "~/functions/usePlayerStore.ts";

const BASE_RECONNECT_DELAY_MS = 1000;
const MAX_RECONNECT_DELAY_MS = 30000;
const MAX_RECONNECT_ATTEMPTS = 10;

const playerStore = usePlayerStore();
const { logVolume, isMuted, isPlaying, current, duration, progress } =
    storeToRefs(playerStore);
const { stop: storeStop, setIsPlaying, setPlayPosition } = playerStore;

const $audio = useTemplateRef("$audio");

const hls = ref<Hls | null>(null);
const bc = ref<BroadcastChannel | null>(null);
let reconnectTimer: ReturnType<typeof setTimeout> | null = null;
let reconnectAttempts = 0;

watch(logVolume, (newVol) => {
    if ($audio.value !== null) {
        $audio.value.volume = newVol;
    }
});

watch(isMuted, (newMuted) => {
    if ($audio.value !== null) {
        $audio.value.muted = newMuted;
    }
});

const clearReconnectTimer = () => {
    if (reconnectTimer !== null) {
        clearTimeout(reconnectTimer);
        reconnectTimer = null;
    }
};

const stop = () => {
    clearReconnectTimer();
    reconnectAttempts = 0;

    if ($audio.value !== null) {
        $audio.value.pause();
        $audio.value.src = "";
    }

    if (hls.value !== null) {
        hls.value.destroy();
        hls.value = null;
    }

    setIsPlaying(false);
};

const scheduleReconnect = () => {
    if (
        reconnectTimer !== null ||
        current.value.url === null ||
        reconnectAttempts >= MAX_RECONNECT_ATTEMPTS
    ) {
        return;
    }

    const expDelay = Math.min(
        MAX_RECONNECT_DELAY_MS,
        BASE_RECONNECT_DELAY_MS * 2 ** reconnectAttempts,
    );
    const jitter = Math.floor(Math.random() * 500);
    reconnectAttempts += 1;

    console.log(
        `Stream interrupted. Reconnecting in ${expDelay + jitter}ms (attempt ${reconnectAttempts}/${MAX_RECONNECT_ATTEMPTS})...`,
    );

    reconnectTimer = setTimeout(() => {
        reconnectTimer = null;
        if (current.value.url !== null) {
            play();
        }
    }, expDelay + jitter);
};

const play = () => {
    clearReconnectTimer();

    if (isPlaying.value) {
        if ($audio.value !== null) {
            $audio.value.pause();
            $audio.value.src = "";
        }
        if (hls.value !== null) {
            hls.value.destroy();
            hls.value = null;
        }
        isPlaying.value = false;
        void nextTick().then(() => {
            play();
        });
        return;
    }

    isPlaying.value = true;

    void nextTick().then(() => {
        if (!$audio.value) {
            return;
        }

        $audio.value.onplaying = () => {
            reconnectAttempts = 0;
            clearReconnectTimer();
        };

        // Handle audio network errors with exponential backoff reconnection.
        $audio.value.onerror = (e: string | Event) => {
            if (isString(e)) {
                return;
            }

            const eventTarget = e.target as HTMLAudioElement;

            if (
                (eventTarget.error?.code === MediaError.MEDIA_ERR_NETWORK ||
                    eventTarget.error?.code === MediaError.MEDIA_ERR_DECODE) &&
                $audio.value?.src
            ) {
                scheduleReconnect();
            }
        };

        $audio.value.onstalled = () => {
            if (current.value.isStream && $audio.value?.src) {
                scheduleReconnect();
            }
        };

        $audio.value.onended = () => {
            if (current.value.isStream) {
                scheduleReconnect();
            } else {
                stop();
            }
        };

        $audio.value.ontimeupdate = () => {
            const audioDuration = $audio.value?.duration ?? 0;

            setPlayPosition(
                audioDuration !== Infinity && !Number.isNaN(audioDuration)
                    ? audioDuration
                    : 0,
                $audio.value?.currentTime ?? 0,
            );
        };

        $audio.value.volume = logVolume.value;
        $audio.value.muted = isMuted.value;

        if (current.value.url !== null) {
            if (current.value.isHls) {
                // HLS playback support with bounded buffer ceilings to prevent heap growth
                if (Hls.isSupported()) {
                    const hlsInstance = new Hls({
                        maxBufferLength: 30,
                        maxMaxBufferLength: 60,
                        backBufferLength: 15,
                        liveSyncDurationCount: 3,
                        liveMaxLatencyDurationCount: 6,
                    });
                    hlsInstance.on(Hls.Events.ERROR, (_event, data) => {
                        if (!data.fatal) {
                            return;
                        }
                        if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                            hlsInstance.startLoad();
                        } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                            hlsInstance.recoverMediaError();
                        } else {
                            scheduleReconnect();
                        }
                    });
                    hlsInstance.loadSource(current.value.url);
                    hlsInstance.attachMedia($audio.value);
                    hls.value = hlsInstance;
                } else if (
                    $audio.value.canPlayType("application/vnd.apple.mpegurl")
                ) {
                    $audio.value.src = current.value.url;
                } else {
                    console.log("Your browser does not support HLS.");
                }
            } else {
                // Standard streams
                $audio.value.src = current.value.url;

                // Firefox caches the downloaded stream, this causes playback issues.
                // Giving the browser a new url on each start bypasses the old cache/buffer
                if (navigator.userAgent.includes("Firefox")) {
                    const sep = $audio.value.src.includes("?") ? "&" : "?";
                    $audio.value.src += `${sep}refresh=${Date.now()}`;
                }
            }
        }

        $audio.value.load();
        $audio.value.play().catch((err: unknown) => {
            if (err instanceof DOMException && err.name === "NotAllowedError") {
                console.warn(
                    "Audio playback failed or was blocked by autoplay policy:",
                    err,
                );
                stop();
            } else if (current.value.isStream) {
                scheduleReconnect();
            } else {
                stop();
            }
        });

        if (bc.value) {
            bc.value.postMessage("played");
        }
    });
};

watch(progress, (newProgress) => {
    if (newProgress.isSeek && $audio.value !== null) {
        $audio.value.currentTime =
            (newProgress.position / 100) * duration.value;
    }
});

watch(current, (newCurrent) => {
    reconnectAttempts = 0;
    if (newCurrent.url === null) {
        stop();
    } else {
        play();
    }
});

onMounted(() => {
    // Handle system & hardware media controls (Bluetooth, Lockscreen, Smartwatch)
    if ("mediaSession" in navigator) {
        navigator.mediaSession.setActionHandler("pause", () => {
            storeStop();
        });
        navigator.mediaSession.setActionHandler("stop", () => {
            storeStop();
        });
        navigator.mediaSession.setActionHandler("play", () => {
            if (current.value.url !== null) {
                play();
            }
        });
    }

    // Global keyboard shortcuts (M for Mute/Unmute)
    window.addEventListener("keydown", handleGlobalKeydown);

    if ("BroadcastChannel" in window) {
        bc.value = new BroadcastChannel("audio_player");
        bc.value.addEventListener(
            "message",
            () => {
                storeStop();
            },
            { passive: true },
        );
    }
});

const handleGlobalKeydown = (e: KeyboardEvent) => {
    const target = e.target as HTMLElement | null;
    if (
        target &&
        (target.tagName === "INPUT" ||
            target.tagName === "TEXTAREA" ||
            target.tagName === "SELECT" ||
            target.isContentEditable)
    ) {
        return;
    }

    if (e.code === "KeyM" && !e.ctrlKey && !e.metaKey && !e.altKey) {
        playerStore.toggleMute();
    }
};

onScopeDispose(() => {
    clearReconnectTimer();
    window.removeEventListener("keydown", handleGlobalKeydown);
    if ("mediaSession" in navigator) {
        navigator.mediaSession.setActionHandler("pause", null);
        navigator.mediaSession.setActionHandler("stop", null);
        navigator.mediaSession.setActionHandler("play", null);
    }
    if (bc.value) {
        bc.value.close();
    }
});
</script>
