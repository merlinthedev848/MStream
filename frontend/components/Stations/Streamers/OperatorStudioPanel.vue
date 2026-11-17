<template>
    <card-page
        id="dj-manager-studio"
        header-id="hdr_studio"
    >
        <template #header="{id}">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h2
                    :id="id"
                    class="card-title flex-fill my-0"
                >
                    {{ $gettext('Studio') }}
                </h2>
                <div class="flex-shrink-0">
                    <span
                        v-if="isLive"
                        class="badge broadcast-tally-live"
                    >
                        {{ $gettext('LIVE') }}
                    </span>
                    <span
                        v-else-if="backendRunning"
                        class="badge broadcast-tally-onair"
                    >
                        {{ $gettext('AutoDJ') }}
                    </span>
                    <span
                        v-else
                        class="badge broadcast-tally-standby"
                    >
                        {{ $gettext('STANDBY') }}
                    </span>
                </div>
            </div>
        </template>

        <div class="card-body py-3">
            <p class="card-text text-muted mb-3">
                {{
                    $gettext(
                        'AutoDJ is the 24/7 engine. A live DJ connection takes over the stream until disconnect — no mic talkover over AutoDJ.',
                    )
                }}
            </p>

            <div class="broadcast-ops-grid">
                <div class="broadcast-ops-cell">
                    <div class="broadcast-ops-label">
                        {{ $gettext('On Air') }}
                    </div>
                    <div
                        class="broadcast-ops-value"
                        :class="isLive ? 'text-success' : 'text-muted'"
                    >
                        {{ onAirLabel }}
                    </div>
                </div>
                <div class="broadcast-ops-cell">
                    <div class="broadcast-ops-label">
                        {{ $gettext('Queue') }}
                    </div>
                    <div
                        class="broadcast-ops-value broadcast-mono"
                        :class="queueDepthClass"
                    >
                        {{ queueDepthDisplay }}
                    </div>
                </div>
                <div class="broadcast-ops-cell">
                    <div class="broadcast-ops-label">
                        {{ $gettext('Pending Requests') }}
                    </div>
                    <div
                        class="broadcast-ops-value broadcast-mono"
                        :class="pendingClass"
                    >
                        {{ pendingDisplay }}
                    </div>
                </div>
                <div class="broadcast-ops-cell">
                    <div class="broadcast-ops-label">
                        {{ $gettext('Browser DJ') }}
                    </div>
                    <div class="broadcast-ops-value">
                        <span
                            v-if="canOpenBrowserDj"
                            class="badge broadcast-tally-onair"
                        >
                            {{ $gettext('READY') }}
                        </span>
                        <span
                            v-else
                            class="badge broadcast-tally-standby"
                        >
                            {{ $gettext('OFF') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <template
            v-if="showFooter"
            #footer_actions
        >
            <a
                v-if="canOpenBrowserDj"
                class="btn btn-primary"
                :href="stationData.webDjUrl"
                target="_blank"
                rel="noopener"
            >
                <icon-ic-mic/>

                <span>
                    {{ $gettext('Open Browser DJ') }}
                </span>
            </a>

            <router-link
                v-if="canViewQueue"
                class="btn btn-link text-primary"
                :to="{ name: 'stations:queue:index' }"
            >
                <icon-ic-queue-music/>

                <span>
                    {{ $gettext('Upcoming Queue') }}
                </span>
            </router-link>

            <router-link
                v-if="canViewRequests"
                class="btn btn-link text-primary"
                :to="{ name: 'stations:reports:requests' }"
            >
                <icon-ic-assignment/>

                <span>
                    {{ requestsLinkLabel }}
                </span>
            </router-link>

            <button
                v-if="canSkipSong"
                type="button"
                class="btn btn-link text-secondary"
                :disabled="!backendRunning || isLive"
                @click="doSkipSong()"
            >
                <icon-ic-skip-next/>

                <span>
                    {{ $gettext('Skip Song') }}
                </span>
            </button>

            <button
                v-if="canDisconnectStreamer"
                type="button"
                class="btn btn-link text-secondary"
                :disabled="!backendRunning || !isLive"
                @click="doDisconnectStreamer()"
            >
                <icon-ic-volume-off/>

                <span>
                    {{ $gettext('Disconnect Streamer') }}
                </span>
            </button>
        </template>
    </card-page>
</template>

<script setup lang="ts">
import { computed } from "vue";
import CardPage from "~/components/Common/CardPage.vue";
import useMakeApiCall from "~/components/Stations/Profile/useMakeApiCall.ts";
import { useStationProfileData } from "~/components/Stations/Profile/useProfileQuery.ts";
import {
    BackendAdapters,
    StationPermissions,
} from "~/entities/ApiInterfaces.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import { useStationData } from "~/functions/useStationQuery.ts";
import { useUserAllowedForStation } from "~/functions/useUserallowedForStation.ts";
import { useTranslate } from "~/vendor/gettext";
import IconIcAssignment from "~icons/ic/baseline-assignment";
import IconIcMic from "~icons/ic/baseline-mic";
import IconIcQueueMusic from "~icons/ic/baseline-queue-music";
import IconIcSkipNext from "~icons/ic/baseline-skip-next";
import IconIcVolumeOff from "~icons/ic/baseline-volume-off";

const stationData = useStationData();
const profileData = useStationProfileData();
const { userAllowedForStation } = useUserAllowedForStation();
const { $gettext } = useTranslate();
const { getStationApiUrl } = useApiRouter();

const isLiquidsoap = computed(
    () => stationData.value.backendType === BackendAdapters.Liquidsoap,
);

const backendRunning = computed(() =>
    Boolean(profileData.value.services?.backendRunning),
);

const isLive = computed(() => Boolean(profileData.value.ops?.isStreamerLive));

const canOpenBrowserDj = computed(
    () =>
        Boolean(stationData.value.enableStreamers) &&
        Boolean(stationData.value.webDjUrl),
);

const canViewQueue = computed(
    () =>
        userAllowedForStation(StationPermissions.Broadcasting) &&
        stationData.value.features.autoDjQueue,
);

const canViewRequests = computed(
    () =>
        userAllowedForStation(StationPermissions.Broadcasting) &&
        stationData.value.enableRequests,
);

const canSkipSong = computed(
    () =>
        isLiquidsoap.value &&
        userAllowedForStation(StationPermissions.Broadcasting) &&
        !isLive.value,
);

const canDisconnectStreamer = computed(
    () =>
        isLiquidsoap.value &&
        userAllowedForStation(StationPermissions.Broadcasting) &&
        isLive.value,
);

const showFooter = computed(
    () =>
        canOpenBrowserDj.value ||
        canViewQueue.value ||
        canViewRequests.value ||
        canSkipSong.value ||
        canDisconnectStreamer.value,
);

const onAirLabel = computed(() => {
    if (isLive.value) {
        const name = profileData.value.ops?.connectedStreamer?.trim();
        return name && name.length > 0 ? name : $gettext("Live DJ");
    }

    if (backendRunning.value) {
        return $gettext("AutoDJ");
    }

    return $gettext("Offline");
});

const queueDepth = computed(() => profileData.value.ops?.queueDepth ?? 0);

const queueDepthDisplay = computed(() =>
    String(queueDepth.value).padStart(2, "0"),
);

const queueDepthClass = computed(() => {
    if (!backendRunning.value) {
        return "text-muted";
    }
    if (queueDepth.value === 0) {
        return "text-warning";
    }
    return "text-success";
});

const pendingCount = computed(
    () => profileData.value.ops?.pendingRequests ?? 0,
);

const pendingDisplay = computed(() =>
    String(pendingCount.value).padStart(2, "0"),
);

const pendingClass = computed(() => {
    if (!stationData.value.enableRequests) {
        return "text-muted";
    }
    if (pendingCount.value > 0) {
        return "text-warning";
    }
    return "text-success";
});

const requestsLinkLabel = computed(() => {
    if (pendingCount.value > 0) {
        return $gettext("Pending Requests (%{count})", {
            count: String(pendingCount.value),
        });
    }
    return $gettext("Pending Requests");
});

const backendSkipSongUri = getStationApiUrl("/backend/skip");
const backendDisconnectStreamerUri = getStationApiUrl("/backend/disconnect");

const doSkipSong = useMakeApiCall(backendSkipSongUri, {
    title: $gettext("Skip current song?"),
    confirmButtonText: $gettext("Skip Song"),
});

const doDisconnectStreamer = useMakeApiCall(backendDisconnectStreamerUri, {
    title: $gettext("Disconnect current streamer?"),
    confirmButtonText: $gettext("Disconnect Streamer"),
});
</script>
