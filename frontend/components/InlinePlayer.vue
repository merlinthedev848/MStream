<template>
    <div
        v-bind="$attrs"
        class="player-inline"
        :class="{ 'is-active': isActive }"
        :aria-hidden="isActive ? undefined : 'true'"
    >
        <div
            v-if="canSeek"
            class="inline-seek d-inline-flex align-items-center ms-1"
            :class="{ 'is-visible': showSeek }"
        >
            <div class="flex-shrink-0 mx-1 text-white-50 time-display broadcast-mono">
                {{ showSeek ? currentTimeText : '--:--' }}
            </div>
            <div class="flex-fill mx-2">
                <input
                    v-model.number="seekPosition"
                    type="range"
                    :title="$gettext('Seek')"
                    class="player-seek-range form-range"
                    min="0"
                    max="100"
                    step="1"
                    :disabled="!showSeek"
                >
            </div>
            <div class="flex-shrink-0 mx-1 text-white-50 time-display broadcast-mono">
                {{ showSeek ? durationText : '--:--' }}
            </div>
        </div>

        <button
            type="button"
            class="btn p-2 ms-2 text-reset"
            :aria-label="$gettext('Stop')"
            :disabled="!isActive"
            @click="stop()"
        >
            <icon-ic-stop/>
        </button>
        <div
            v-if="showVolume"
            class="inline-volume-controls d-inline-flex align-items-center ms-2"
        >
            <div class="flex-shrink-0">
                <mute-button
                    class="btn p-2 text-reset"
                    :volume="volume"
                    :is-muted="isMuted"
                    @toggle-mute="toggleMute"
                />
            </div>
            <div class="flex-fill mx-1">
                <input
                    v-model.number="volume"
                    type="range"
                    :title="$gettext('Volume')"
                    class="player-volume-range form-range"
                    min="0"
                    max="100"
                    step="1"
                    :disabled="!isActive"
                >
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { storeToRefs } from "pinia";
import { computed } from "vue";
import MuteButton from "~/components/Common/Audio/MuteButton.vue";
import { StreamChannel, usePlayerStore } from "~/functions/usePlayerStore.ts";
import IconIcStop from "~icons/ic/baseline-stop";

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<{
        channel?: StreamChannel;
    }>(),
    {
        channel: StreamChannel.Global,
    },
);

const playerStore = usePlayerStore();
const {
    isPlaying,
    current,
    duration,
    durationText,
    currentTimeText,
    volume,
    showVolume,
    isMuted,
    progress,
} = storeToRefs(playerStore);
const { stop, seek, toggleMute } = playerStore;

const isCurrentChannel = computed(
    () => props.channel === current.value.channel,
);

const isActive = computed(() => isCurrentChannel.value && isPlaying.value);

/** File/on-demand media can seek; keep the seek rail mounted while that track is current. */
const canSeek = computed(
    () =>
        isCurrentChannel.value &&
        !current.value.isStream &&
        duration.value !== 0,
);

const showSeek = computed(() => isActive.value && canSeek.value);

const seekPosition = computed({
    get: () => progress.value.position,
    set: (prog) => {
        seek(prog);
    },
});

defineExpose({
    stop,
});
</script>

<style lang="scss">
.player-inline {
    display: inline-flex;
    align-items: center;
    min-height: 2.5rem;
    min-width: 3rem;
    opacity: 0.45;

    &.is-active {
        opacity: 1;
    }

    &:not(.is-active) {
        pointer-events: none;
    }

    .inline-seek {
        width: 300px;
        visibility: hidden;

        &.is-visible {
            visibility: visible;
        }

        div.time-display {
            font-size: 85%;
        }
    }

    .inline-volume-controls {
        width: 125px;
    }

    input.player-volume-range,
    input.player-seek-range {
        width: 100%;
        height: 10px;
    }
}
</style>
