<template>
    <card-page
        v-if="showPanel"
        id="profile-getting-started"
        class="getting-started"
        header-id="hdr_getting_started"
    >
        <template #header="{id}">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h3
                    :id="id"
                    class="card-title my-0 flex-fill"
                >
                    {{ $gettext('Get On the Air') }}
                </h3>
                <span class="badge broadcast-tally-standby">
                    {{ $gettext('SETUP') }}
                </span>
            </div>
        </template>

        <div class="card-body">
            <p class="getting-started-lead mb-3">
                {{
                    $gettext(
                        'Your station is ready. Upload music and AutoDJ will start playing it for listeners.',
                    )
                }}
            </p>

            <ol class="getting-started-steps list-unstyled mb-0">
                <li class="getting-started-step">
                    <span class="getting-started-step-index broadcast-mono">1</span>
                    <div class="getting-started-step-body">
                        <div class="getting-started-step-title">
                            {{ $gettext('Upload tracks') }}
                        </div>
                        <p class="getting-started-step-text mb-2">
                            {{
                                $gettext(
                                    'Add songs to your music library. New uploads join the default playlist automatically.',
                                )
                            }}
                        </p>
                        <router-link
                            v-if="canManageMedia"
                            class="btn btn-primary btn-sm"
                            :to="{ name: 'stations:files:index' }"
                        >
                            {{ $gettext('Open Music Library') }}
                        </router-link>
                    </div>
                </li>
                <li class="getting-started-step">
                    <span class="getting-started-step-index broadcast-mono">2</span>
                    <div class="getting-started-step-body">
                        <div class="getting-started-step-title">
                            {{ $gettext('AutoDJ plays') }}
                        </div>
                        <p class="getting-started-step-text mb-0">
                            {{
                                $gettext(
                                    'Keep AutoDJ running. It builds the queue and fills the air when you are not live.',
                                )
                            }}
                        </p>
                    </div>
                </li>
                <li class="getting-started-step">
                    <span class="getting-started-step-index broadcast-mono">3</span>
                    <div class="getting-started-step-body">
                        <div class="getting-started-step-title">
                            {{ $gettext('Share your stream') }}
                        </div>
                        <template v-if="stationData.hasStarted">
                            <p class="getting-started-step-text mb-0">
                                {{
                                    $gettext(
                                        'Copy a listener link from Listener Streams below once tracks are on the air.',
                                    )
                                }}
                            </p>
                        </template>
                        <template v-else>
                            <p class="getting-started-step-text mb-2">
                                {{
                                    $gettext(
                                        'Start broadcasting to go on the air. Listener stream URLs are listed below, and you can manage mounts under Mount Points.',
                                    )
                                }}
                            </p>
                            <div class="d-flex flex-wrap gap-2">
                                <router-link
                                    v-if="canBroadcast"
                                    class="btn btn-primary btn-sm"
                                    :to="{ name: 'stations:restart:index' }"
                                >
                                    {{ $gettext('Start Station') }}
                                </router-link>
                                <router-link
                                    class="btn btn-outline-primary btn-sm"
                                    :to="{ name: 'stations:mounts:index' }"
                                >
                                    {{ $gettext('Manage Mounts') }}
                                </router-link>
                            </div>
                        </template>
                    </div>
                </li>
            </ol>
        </div>
    </card-page>
</template>

<script setup lang="ts">
import { useQuery } from "@tanstack/vue-query";
import { computed } from "vue";
import CardPage from "~/components/Common/CardPage.vue";
import { useStationProfileData } from "~/components/Stations/Profile/useProfileQuery.ts";
import {
    ApiStationQuota,
    BackendAdapters,
    StationPermissions,
    StorageLocationTypes,
} from "~/entities/ApiInterfaces.ts";
import { QueryKeys, queryKeyWithStation } from "~/entities/Queries.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import { useStationData } from "~/functions/useStationQuery.ts";
import { useUserAllowedForStation } from "~/functions/useUserallowedForStation.ts";
import { useAxios } from "~/vendor/axios";

const stationData = useStationData();
const profileData = useStationProfileData();
const { userAllowedForStation } = useUserAllowedForStation();
const { getStationApiUrl } = useApiRouter();
const { axiosSilent } = useAxios();

const canManageMedia = computed(() =>
    userAllowedForStation(StationPermissions.Media),
);

const canBroadcast = computed(() =>
    userAllowedForStation(StationPermissions.Broadcasting),
);

const hasAutoDj = computed(
    () => stationData.value.backendType !== BackendAdapters.None,
);

const quotaUrl = getStationApiUrl(
    `/quota/${StorageLocationTypes.StationMedia}`,
);

const { data: quota, isSuccess: quotaLoaded } = useQuery<ApiStationQuota>({
    queryKey: queryKeyWithStation([QueryKeys.StationMedia, "quota"]),
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

const libraryIsEmpty = computed(() => {
    if (!quotaLoaded.value || !quota.value) {
        return false;
    }
    return (quota.value.num_files ?? 0) === 0;
});

const queueIsEmpty = computed(
    () => (profileData.value.ops?.queueDepth ?? 0) === 0,
);

const showPanel = computed(() => {
    if (!hasAutoDj.value || !stationData.value.features.media) {
        return false;
    }
    if (!canManageMedia.value) {
        return false;
    }
    // Prefer explicit empty library; fall back to empty AutoDJ queue
    // when quota is unavailable so new stations still get guidance.
    if (quotaLoaded.value) {
        return libraryIsEmpty.value;
    }
    return queueIsEmpty.value && !stationData.value.hasStarted;
});
</script>
