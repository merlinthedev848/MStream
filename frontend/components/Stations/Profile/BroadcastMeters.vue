<template>
    <div
        class="broadcast-meters"
        :class="{ 'is-live': meters.available, 'is-idle': !meters.available }"
        role="group"
        :aria-label="$gettext('Master bus meters')"
    >
        <div class="broadcast-meters-header">
            <span class="broadcast-meters-title">
                {{ $gettext('Meters') }}
            </span>
            <span class="broadcast-meters-source broadcast-mono">
                <template v-if="meters.available">
                    {{ $gettext('LS LIVE') }}
                </template>
                <template v-else>
                    {{ $gettext('NO SIGNAL') }}
                </template>
            </span>
        </div>

        <div class="broadcast-meters-grid">
            <div
                v-for="channel in channels"
                :key="channel.id"
                class="broadcast-meter-channel"
            >
                <span class="broadcast-meter-channel-label broadcast-mono">
                    {{ channel.id }}
                </span>
                <div class="broadcast-meter-stack">
                    <div class="broadcast-meter-row">
                        <span class="broadcast-meter-label broadcast-mono">VU</span>
                        <div
                            class="broadcast-meter-track"
                            aria-hidden="true"
                        >
                            <div
                                class="broadcast-meter-fill"
                                :style="{ width: maskPercent(channel.vu) + '%' }"
                            />
                            <div
                                class="broadcast-meter-hold"
                                :style="{ left: percent(channel.vuHold) + '%' }"
                            />
                        </div>
                        <span class="broadcast-meter-readout broadcast-mono">
                            {{ formatDb(channel.vu) }}
                        </span>
                    </div>
                    <div class="broadcast-meter-row is-peak">
                        <span class="broadcast-meter-label broadcast-mono">PK</span>
                        <div
                            class="broadcast-meter-track"
                            aria-hidden="true"
                        >
                            <div
                                class="broadcast-meter-fill is-peak"
                                :style="{ width: maskPercent(channel.peak) + '%' }"
                            />
                            <div
                                class="broadcast-meter-hold"
                                :style="{ left: percent(channel.peakHold) + '%' }"
                            />
                        </div>
                        <span class="broadcast-meter-readout broadcast-mono">
                            {{ formatDb(channel.peak) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, watch } from "vue";
import { ApiStationAudioMeters } from "~/entities/ApiInterfaces.ts";

const props = defineProps<{
    meters: ApiStationAudioMeters;
}>();

const FLOOR_DB = -60;
const CEILING_DB = 0;
const PEAK_HOLD_MS = 1200;

type MeterKey = "vu_l" | "vu_r" | "peak_l" | "peak_r";

const peakHold = reactive<Record<MeterKey, number>>({
    vu_l: FLOOR_DB,
    vu_r: FLOOR_DB,
    peak_l: FLOOR_DB,
    peak_r: FLOOR_DB,
});

const peakHoldTimers: Partial<Record<MeterKey, ReturnType<typeof setTimeout>>> =
    {};

const updatePeakHold = (key: MeterKey, value: number) => {
    if (value >= peakHold[key]) {
        peakHold[key] = value;
        if (peakHoldTimers[key]) {
            clearTimeout(peakHoldTimers[key]);
        }
        peakHoldTimers[key] = setTimeout(() => {
            peakHold[key] = FLOOR_DB;
        }, PEAK_HOLD_MS);
    }
};

const clearPeakHolds = () => {
    (Object.keys(peakHold) as MeterKey[]).forEach((key) => {
        peakHold[key] = FLOOR_DB;
        if (peakHoldTimers[key]) {
            clearTimeout(peakHoldTimers[key]);
            delete peakHoldTimers[key];
        }
    });
};

watch(
    () => props.meters,
    (meters) => {
        if (!meters.available) {
            clearPeakHolds();
            return;
        }

        updatePeakHold("vu_l", meters.vu_l);
        updatePeakHold("vu_r", meters.vu_r);
        updatePeakHold("peak_l", meters.peak_l);
        updatePeakHold("peak_r", meters.peak_r);
    },
    { deep: true },
);

onBeforeUnmount(() => {
    clearPeakHolds();
});

const percent = (db: number): number => {
    const clamped = Math.max(FLOOR_DB, Math.min(CEILING_DB, db));
    return ((clamped - FLOOR_DB) / (CEILING_DB - FLOOR_DB)) * 100;
};

const maskPercent = (db: number): number => 100 - percent(db);

const formatDb = (db: number): string => {
    if (db <= FLOOR_DB) {
        return "-∞";
    }
    return db.toFixed(1);
};

const channels = computed(() => [
    {
        id: "L",
        vu: props.meters.vu_l,
        peak: props.meters.peak_l,
        vuHold: peakHold.vu_l,
        peakHold: peakHold.peak_l,
    },
    {
        id: "R",
        vu: props.meters.vu_r,
        peak: props.meters.peak_r,
        vuHold: peakHold.vu_r,
        peakHold: peakHold.peak_r,
    },
]);
</script>
