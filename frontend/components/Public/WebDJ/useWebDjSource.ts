import { WebcasterMetadata } from "~/components/Public/WebDJ/useWebcaster.ts";
import { useInjectWebDjNode } from "~/components/Public/WebDJ/useWebDjNode";

interface StreamAudioSourceWithStop extends MediaStreamAudioSourceNode {
    stop?(): void;
}

interface TagLibAudio {
    length: string;
}

export interface TagLibProcessResult {
    audio: TagLibAudio;
    metadata: WebcasterMetadata | null;
}

export interface WebDjFilePointer {
    file: File;
    audio: TagLibAudio;
    metadata: WebcasterMetadata;
}

export function useWebDjSource() {
    const { context } = useInjectWebDjNode();

    const createAudioSource = (
        pointer: WebDjFilePointer,
        cb: (source: MediaElementAudioSourceNode) => void,
        onEnd?: () => void,
    ) => {
        const objectUrl = URL.createObjectURL(pointer.file);
        const el = new Audio(objectUrl);
        el.controls = false;
        el.autoplay = false;
        el.loop = false;

        let source: any = null;
        let cleanedUp = false;

        const cleanupMediaResources = () => {
            if (cleanedUp) {
                return;
            }
            cleanedUp = true;
            try {
                el.pause();
                el.removeAttribute("src");
                el.load();
            } catch {
                // Ignore cleanup errors on detached media element
            }
            URL.revokeObjectURL(objectUrl);
            if (source) {
                try {
                    source.disconnect();
                } catch {
                    // Node may already be disconnected
                }
            }
        };

        el.addEventListener("ended", () => {
            cleanupMediaResources();
            if (typeof onEnd === "function") {
                onEnd();
            }
        });

        el.addEventListener("canplay", () => {
            if (source) {
                return;
            }

            source = context.value.createMediaElementSource(el);

            source.play = () => el.play();
            source.position = () => el.currentTime;
            source.duration = () => el.duration;
            source.paused = () => el.paused;
            source.stop = () => {
                cleanupMediaResources();
                return el.remove();
            };
            source.pause = () => el.pause();
            source.seek = (percent: number) => {
                const time = percent * parseFloat(pointer.audio.length);
                el.currentTime = time;
                return time;
            };

            cb(source);
        });
    };

    const createMicrophoneSource = (
        audioDeviceId: ConstrainDOMString,
        cb: (source: StreamAudioSourceWithStop) => void,
    ): void => {
        void (async () => {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: false,
                audio: {
                    deviceId: audioDeviceId,
                },
            });

            const source: StreamAudioSourceWithStop =
                context.value.createMediaStreamSource(stream);
            source.stop = () => {
                for (const track of stream.getAudioTracks()) {
                    track.stop();
                }
                try {
                    source.disconnect();
                } catch {
                    // Ignore if already disconnected
                }
            };

            cb(source);
        })();
    };

    return {
        createAudioSource,
        createMicrophoneSource,
    };
}
