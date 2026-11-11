export interface WebDjMimeTypeOption {
    name: string;
    value: string;
}

/**
 * Prefer Ogg/Opus (Firefox) when available — Liquidsoap/ffmpeg handles it
 * cleanly — then WebM/Opus (Chrome/Edge), then generic WebM.
 */
const CANDIDATE_AUDIO_MIME_TYPES: WebDjMimeTypeOption[] = [
    { name: "Ogg Opus", value: "audio/ogg;codecs=opus" },
    { name: "WebM Opus", value: "audio/webm;codecs=opus" },
    { name: "WebM", value: "audio/webm" },
];

export function getSupportedAudioMimeTypes(): WebDjMimeTypeOption[] {
    if (typeof MediaRecorder === "undefined") {
        return [];
    }

    return CANDIDATE_AUDIO_MIME_TYPES.filter((option) => {
        try {
            return MediaRecorder.isTypeSupported(option.value);
        } catch {
            return false;
        }
    });
}

export function getPreferredAudioMimeType(): string {
    const supported = getSupportedAudioMimeTypes();
    return supported[0]?.value ?? "audio/webm;codecs=opus";
}
