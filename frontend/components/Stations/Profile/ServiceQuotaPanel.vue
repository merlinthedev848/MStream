<template>
    <section
        id="profile-service-quotas"
        class="card mb-3"
        role="region"
        :aria-label="$gettext('Service Quotas')"
    >
        <div class="card-header text-bg-primary">
            <h3 class="card-title mb-0">
                {{ $gettext('Service Quotas') }}
            </h3>
        </div>
        <div class="card-body">
            <div class="service-quota-grid">
                <div class="service-quota-cell">
                    <div class="service-quota-label">
                        {{ $gettext('Connections') }}
                    </div>
                    <div class="service-quota-value broadcast-mono">
                        {{ connectionsLabel }}
                    </div>
                    <div
                        v-if="connectionsPercent !== null"
                        class="progress h-15 mt-2"
                        role="progressbar"
                        :aria-valuenow="connectionsPercent"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="progress-bar text-bg-primary"
                            :style="{ width: connectionsPercent + '%' }"
                        />
                    </div>
                    <div class="service-quota-meta">
                        {{ $gettext('Current listeners') }}
                    </div>
                    <router-link
                        v-if="canViewListeners"
                        class="btn btn-link btn-sm px-0 mt-1"
                        :to="{ name: 'stations:reports:listeners' }"
                    >
                        {{ $gettext('View Listeners') }}
                    </router-link>
                </div>

                <div class="service-quota-cell">
                    <div class="service-quota-label">
                        {{ $gettext('Bitrate') }}
                    </div>
                    <div class="service-quota-value broadcast-mono">
                        {{ bitrateLabel }}
                    </div>
                    <div
                        v-if="bitratePercent !== null"
                        class="progress h-15 mt-2"
                        role="progressbar"
                        :aria-valuenow="bitratePercent"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="progress-bar text-bg-info"
                            :style="{ width: bitratePercent + '%' }"
                        />
                    </div>
                    <div class="service-quota-meta">
                        {{ $gettext('Live / station max (Kbps)') }}
                    </div>
                </div>

                <div class="service-quota-cell">
                    <div class="service-quota-label">
                        {{ $gettext('Disk') }}
                    </div>
                    <div class="service-quota-value broadcast-mono">
                        {{ storageLabel }}
                    </div>
                    <div
                        class="progress h-15 mt-2"
                        role="progressbar"
                        :aria-valuenow="storagePercent"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="progress-bar"
                            :class="storageBarClass"
                            :style="{ width: storagePercent + '%' }"
                        />
                    </div>
                    <router-link
                        v-if="canManageMedia"
                        class="btn btn-link btn-sm px-0 mt-1"
                        :to="{ name: 'stations:files:index' }"
                    >
                        {{ $gettext('Manage Media') }}
                    </router-link>
                </div>

                <div class="service-quota-cell">
                    <div class="service-quota-label">
                        {{ $gettext('Status') }}
                    </div>
                    <div class="mt-1">
                        <span
                            v-if="!stationData.isEnabled"
                            class="badge text-bg-secondary"
                        >
                            {{ $gettext('SUSPENDED') }}
                        </span>
                        <span
                            v-else-if="np.is_online"
                            class="badge broadcast-tally-onair"
                        >
                            {{ $gettext('ON AIR') }}
                        </span>
                        <span
                            v-else
                            class="badge broadcast-tally-standby"
                        >
                            {{ $gettext('OFFLINE') }}
                        </span>
                    </div>
                    <div
                        v-if="profileData.frontendPort"
                        class="service-quota-meta broadcast-mono mt-2"
                    >
                        {{ $gettext('Port') }}: {{ profileData.frontendPort }}
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup lang="ts">
import { useQuery } from "@tanstack/vue-query";
import { toRefs } from "@vueuse/core";
import { computed } from "vue";
import { useStationProfileData } from "~/components/Stations/Profile/useProfileQuery.ts";
import {
    ApiStationQuota,
    StationPermissions,
    StorageLocationTypes,
} from "~/entities/ApiInterfaces.ts";
import { QueryKeys, queryKeyWithStation } from "~/entities/Queries.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import useNowPlaying from "~/functions/useNowPlaying";
import { useStationData } from "~/functions/useStationQuery.ts";
import { useUserAllowedForStation } from "~/functions/useUserallowedForStation.ts";
import { useAxios } from "~/vendor/axios";
import { useTranslate } from "~/vendor/gettext";

const { $gettext } = useTranslate();
const stationData = useStationData();
const profileData = useStationProfileData();
const { nowPlayingProps } = toRefs(profileData);
const { np } = useNowPlaying(nowPlayingProps);

const { userAllowedForStation } = useUserAllowedForStation();
const { getStationApiUrl } = useApiRouter();
const { axiosSilent } = useAxios();

const canManageMedia = computed(() =>
    userAllowedForStation(StationPermissions.Media),
);
const canViewListeners = computed(() =>
    userAllowedForStation(StationPermissions.Reports),
);

const listenersTotal = computed(() => np.value?.listeners?.total ?? 0);

const connectionsLabel = computed(() => {
    const max = stationData.value.maxListeners;
    if (max > 0) {
        return `${listenersTotal.value} / ${max}`;
    }
    return String(listenersTotal.value);
});

const connectionsPercent = computed(() => {
    const max = stationData.value.maxListeners;
    if (max <= 0) {
        return null;
    }
    return Math.min(Math.round((listenersTotal.value / max) * 100), 100);
});

const liveBitrate = computed(() => {
    const mounts = np.value?.station?.mounts ?? [];
    for (const mount of mounts) {
        if (typeof mount.bitrate === "number" && mount.bitrate > 0) {
            return mount.bitrate;
        }
    }
    return 0;
});

const bitrateLabel = computed(() => {
    const max = stationData.value.maxBitrate;
    const current = liveBitrate.value;
    if (max > 0 && current > 0) {
        return `${current} / ${max}`;
    }
    if (max > 0) {
        return `— / ${max}`;
    }
    if (current > 0) {
        return String(current);
    }
    return "—";
});

const bitratePercent = computed(() => {
    const max = stationData.value.maxBitrate;
    if (max <= 0 || liveBitrate.value <= 0) {
        return null;
    }
    return Math.min(Math.round((liveBitrate.value / max) * 100), 100);
});

const quotaUrl = getStationApiUrl(
    `/quota/${StorageLocationTypes.StationMedia}`,
);

const { data: quota } = useQuery<ApiStationQuota>({
    queryKey: queryKeyWithStation([
        QueryKeys.StationMedia,
        "quota",
        "overview",
    ]),
    queryFn: async ({ signal }) => {
        const { data } = await axiosSilent.get<ApiStationQuota>(
            quotaUrl.value,
            { signal },
        );
        return data;
    },
    enabled: computed(
        () =>
            stationData.value.isEnabled &&
            stationData.value.features.media &&
            canManageMedia.value,
    ),
    staleTime: 30 * 1000,
    refetchInterval: 60 * 1000,
});

const storageLabel = computed(() => {
    if (!quota.value) {
        return "—";
    }
    if (quota.value.quota) {
        return `${quota.value.used} / ${quota.value.quota}`;
    }
    return quota.value.used;
});

const storagePercent = computed(() =>
    Math.min(Math.round(quota.value?.used_percent ?? 0), 100),
);

const storageBarClass = computed(() => {
    if (storagePercent.value > 85) {
        return "text-bg-danger";
    }
    if (storagePercent.value > 65) {
        return "text-bg-warning";
    }
    return "text-bg-primary";
});
</script>
