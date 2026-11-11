import { ref, watch } from "vue";
import { getPreferredAudioMimeType } from "~/components/Public/WebDJ/audioMimeTypes.ts";
import createRequiredInjectionState from "~/functions/createRequiredInjectionState.ts";

/** MediaRecorder timeslice: shorter than 1s keeps harbor fed continuously. */
const MEDIA_RECORDER_TIMESLICE_MS = 250;

/**
 * Default ScriptProcessor-era buffer was 256 frames (~5.8 ms @ 44.1 kHz), which
 * hammered the main thread and caused intermittent crackle. GainNode mix bus
 * removes that path; keep a larger value only for any legacy consumers.
 */
const DEFAULT_AUDIO_BUFFER_SIZE = 4096;

export const [useProvideWebDjNode, useInjectWebDjNode] =
    createRequiredInjectionState((webcaster) => {
        const { connect: connectSocket } = webcaster;

        const doPassThrough = ref(false);

        // Higher source bitrate: Liquidsoap re-encodes for mounts anyway.
        const bitrate = ref(256);
        const sampleRate = ref(48000);
        const channelCount = ref(2);
        const bufferSize = ref(DEFAULT_AUDIO_BUFFER_SIZE);
        const mimeType = ref(getPreferredAudioMimeType());

        let audioContext: AudioContext | null = null;
        let sinkNode: GainNode | null = null;
        let cueGainNode: GainNode | null = null;
        let streamDestination: MediaStreamAudioDestinationNode | null = null;
        let mediaRecorder: MediaRecorder | null = null;
        let graphSampleRate: number | null = null;
        let graphChannelCount: number | null = null;

        const tearDownGraph = () => {
            try {
                cueGainNode?.disconnect();
                sinkNode?.disconnect();
                streamDestination?.disconnect();
            } catch {
                // Nodes may already be disconnected
            }

            if (audioContext && audioContext.state !== "closed") {
                void audioContext.close();
            }

            audioContext = null;
            sinkNode = null;
            cueGainNode = null;
            streamDestination = null;
            graphSampleRate = null;
            graphChannelCount = null;
        };

        const ensureGraph = () => {
            const wantsRecreate =
                audioContext !== null &&
                mediaRecorder === null &&
                (graphSampleRate !== sampleRate.value ||
                    graphChannelCount !== channelCount.value);

            if (wantsRecreate) {
                tearDownGraph();
            }

            if (audioContext) {
                return {
                    context: audioContext,
                    sink: sinkNode as GainNode,
                    cueGain: cueGainNode as GainNode,
                    streamNode:
                        streamDestination as MediaStreamAudioDestinationNode,
                };
            }

            audioContext = new AudioContext({
                sampleRate: sampleRate.value,
                latencyHint: "playback",
            });
            graphSampleRate = sampleRate.value;
            graphChannelCount = channelCount.value;

            // Unity-gain mix bus — no ScriptProcessor / main-thread copies.
            sinkNode = audioContext.createGain();
            sinkNode.gain.value = 1;

            cueGainNode = audioContext.createGain();
            cueGainNode.gain.value = doPassThrough.value ? 1 : 0;

            streamDestination = audioContext.createMediaStreamDestination();
            streamDestination.channelCount = channelCount.value;

            sinkNode.connect(streamDestination);
            sinkNode.connect(cueGainNode);
            cueGainNode.connect(audioContext.destination);

            return {
                context: audioContext,
                sink: sinkNode,
                cueGain: cueGainNode,
                streamNode: streamDestination,
            };
        };

        watch(doPassThrough, (enabled) => {
            if (cueGainNode) {
                cueGainNode.gain.value = enabled ? 1 : 0;
            }
        });

        // Lazy getters preserve the previous injection API for tracks/settings.
        const context = {
            get value() {
                return ensureGraph().context;
            },
        };

        const sink = {
            get value() {
                return ensureGraph().sink;
            },
        };

        const passThrough = {
            get value() {
                return ensureGraph().cueGain;
            },
        };

        const streamNode = {
            get value() {
                return ensureGraph().streamNode;
            },
        };

        const startStream = (
            username: string | null = null,
            password: string | null = null,
        ) => {
            const graph = ensureGraph();
            void graph.context.resume();

            const selectedMime = mimeType.value || getPreferredAudioMimeType();

            const recorderOptions: MediaRecorderOptions = {
                audioBitsPerSecond: bitrate.value * 1000,
            };

            if (selectedMime) {
                recorderOptions.mimeType = selectedMime;
            }

            try {
                mediaRecorder = new MediaRecorder(
                    graph.streamNode.stream,
                    recorderOptions,
                );
            } catch {
                // Fall back if the browser rejected the preferred mime/bitrate combo.
                mediaRecorder = new MediaRecorder(graph.streamNode.stream, {
                    mimeType: getPreferredAudioMimeType(),
                    audioBitsPerSecond: bitrate.value * 1000,
                });
            }

            mimeType.value = mediaRecorder.mimeType || selectedMime;

            connectSocket(mediaRecorder, username, password);
            mediaRecorder.start(MEDIA_RECORDER_TIMESLICE_MS);
        };

        const stopStream = () => {
            mediaRecorder?.stop();
            mediaRecorder = null;
        };

        return {
            doPassThrough,
            bitrate,
            sampleRate,
            channelCount,
            bufferSize,
            mimeType,
            context,
            sink,
            passThrough,
            streamNode,
            startStream,
            stopStream,
        };
    });
