import { computed, onUnmounted, ref, watch } from "vue";
import { useInjectWebDjNode } from "~/components/Public/WebDJ/useWebDjNode";

const METER_INTERVAL_MS = 50;

export function useWebDjTrack() {
    const { context, sink, channelCount } = useInjectWebDjNode();

    const trackGain = ref(55);
    const trackPassThrough = ref(false);
    const position = ref<number | null>(null);
    const volume = ref(0);

    const source = ref<any>(null);

    let trackGainNode: GainNode | null = null;
    let trackCueGainNode: GainNode | null = null;
    let analyserNode: AnalyserNode | null = null;
    let meterTimer: ReturnType<typeof setInterval> | null = null;
    let positionTimer: ReturnType<typeof setInterval> | null = null;
    let meterBuffer: Float32Array | null = null;

    const setTrackGain = (newGain: number) => {
        if (trackGainNode) {
            trackGainNode.gain.value = Number(newGain) / 100.0;
        }
    };

    watch(trackGain, setTrackGain);

    watch(trackPassThrough, (enabled) => {
        if (trackCueGainNode) {
            trackCueGainNode.gain.value = enabled ? 1 : 0;
        }
    });

    const stopMeter = () => {
        if (meterTimer !== null) {
            clearInterval(meterTimer);
            meterTimer = null;
        }
        if (positionTimer !== null) {
            clearInterval(positionTimer);
            positionTimer = null;
        }
        volume.value = 0;
    };

    const startMeter = () => {
        stopMeter();

        if (!analyserNode) {
            return;
        }

        const analyser = analyserNode;
        meterBuffer = new Float32Array(analyser.fftSize);

        meterTimer = setInterval(() => {
            if (!analyserNode || !meterBuffer) {
                return;
            }

            analyserNode.getFloatTimeDomainData(
                meterBuffer as Float32Array<ArrayBuffer>,
            );

            let sumSquares = 0;
            for (let i = 0; i < meterBuffer.length; i++) {
                const sample = meterBuffer[i] ?? 0;
                sumSquares += sample * sample;
            }

            // Match prior ScriptProcessor loudness mapping (sum-of-squares vs log N).
            const bufferLog = Math.log(meterBuffer.length);
            const log10 = 2.0 * Math.log(10);
            if (sumSquares > 0) {
                volume.value =
                    100 * Math.exp((Math.log(sumSquares) - bufferLog) / log10);
            } else {
                volume.value = 0;
            }
        }, METER_INTERVAL_MS);

        positionTimer = setInterval(() => {
            if (typeof source.value?.position === "function") {
                position.value = source.value.position();
            }
        }, METER_INTERVAL_MS);
    };

    const prepare = () => {
        stopMeter();

        trackGainNode?.disconnect();
        trackCueGainNode?.disconnect();
        analyserNode?.disconnect();

        const ctx = context.value;

        trackGainNode = ctx.createGain();
        trackGainNode.gain.value = Number(trackGain.value) / 100.0;
        trackGainNode.connect(sink.value);

        // Per-track cue monitor — GainNode mute, not ScriptProcessor silence fills.
        trackCueGainNode = ctx.createGain();
        trackCueGainNode.gain.value = trackPassThrough.value ? 1 : 0;
        trackGainNode.connect(trackCueGainNode);
        trackCueGainNode.connect(ctx.destination);

        analyserNode = ctx.createAnalyser();
        analyserNode.fftSize = 2048;
        analyserNode.channelCount = channelCount.value;
        trackGainNode.connect(analyserNode);

        void ctx.resume();
        startMeter();

        return trackGainNode;
    };

    const isPlaying = computed(() => {
        return source.value !== null;
    });

    const isPaused = computed(() => {
        return source.value !== null ? source.value.paused() : false;
    });

    const togglePause = () => {
        if (source.value === null) {
            return;
        }

        if (source.value.paused()) {
            source.value.play();
        } else {
            source.value.pause();
        }
    };

    const stop = () => {
        source.value?.stop();
        source.value?.disconnect();

        stopMeter();

        trackGainNode?.disconnect();
        trackCueGainNode?.disconnect();
        analyserNode?.disconnect();

        source.value =
            trackGainNode =
            trackCueGainNode =
            analyserNode =
            meterBuffer =
                null;

        position.value = 0.0;
    };

    onUnmounted(() => {
        stopMeter();
    });

    return {
        source,
        trackGain,
        trackPassThrough,
        position,
        volume,
        isPlaying,
        isPaused,
        prepare,
        togglePause,
        stop,
    };
}
