<template>
    <card-page
        id="profile-service-status"
        header-id="hdr_service_status"
    >
        <template #header="{id}">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h3
                    :id="id"
                    class="card-title my-0 flex-fill"
                >
                    {{ $gettext('Service Online') }}
                </h3>
                <span
                    v-if="!stationData.isEnabled"
                    class="badge broadcast-tally-standby"
                >
                    {{ $gettext('SUSPENDED') }}
                </span>
                <span
                    v-else-if="isLiveDj"
                    class="badge broadcast-tally-onair"
                >
                    {{ $gettext('LIVE DJ') }}
                </span>
                <span
                    v-else-if="backendRunning && np.is_online"
                    class="badge broadcast-tally-onair"
                >
                    {{ $gettext('AUTODJ: ON AIR') }}
                </span>
                <span
                    v-else-if="backendRunning"
                    class="badge broadcast-tally-standby"
                >
                    {{ $gettext('AUTODJ: STARTING') }}
                </span>
                <span
                    v-else-if="stationData.hasStarted"
                    class="badge broadcast-tally-standby"
                >
                    {{ $gettext('AUTODJ: STOPPED') }}
                </span>
                <span
                    v-else
                    class="badge broadcast-tally-standby"
                >
                    {{ $gettext('SOURCE: NOT CONNECTED') }}
                </span>
            </div>
        </template>

        <div class="card-body">
            <p class="card-text mb-3">
                <template v-if="!stationData.isEnabled">
                    {{
                        $gettext(
                            'This service is suspended. Ask a fleet admin to unsuspend it before starting AutoDJ.',
                        )
                    }}
                </template>
                <template v-else-if="isLiveDj">
                    {{ liveDjMessage }}
                </template>
                <template v-else-if="backendRunning && queueIsEmpty">
                    {{
                        $gettext(
                            'AutoDJ is running but the queue is empty. Upload tracks or check playlists — dead-air protection plays until songs are available.',
                        )
                    }}
                </template>
                <template v-else-if="backendRunning && np.is_online">
                    {{
                        $gettext(
                            'AutoDJ is on the air. Share the listener URL below.',
                        )
                    }}
                </template>
                <template v-else-if="backendRunning">
                    {{
                        $gettext(
                            'AutoDJ is starting. Wait a moment, or restart if the source stays disconnected.',
                        )
                    }}
                </template>
                <template v-else-if="stationData.hasStarted">
                    {{
                        $gettext(
                            'AutoDJ is stopped. Start AutoDJ to keep the station on the air 24/7.',
                        )
                    }}
                </template>
                <template v-else>
                    {{
                        $gettext(
                            'Upload music (it joins the default playlist), then start AutoDJ to go on the air.',
                        )
                    }}
                </template>
            </p>

            <div class="d-flex flex-wrap gap-2 align-items-center">
                <button
                    v-if="canBroadcast && stationData.isEnabled"
                    type="button"
                    class="btn btn-primary"
                    :disabled="starting"
                    @click="doStart"
                >
                    {{ startLabel }}
                </button>
                <router-link
                    v-if="showUploadLink"
                    class="btn btn-link btn-sm"
                    :to="{ name: 'stations:files:index' }"
                >
                    {{ $gettext('Upload Music') }}
                </router-link>
            </div>
        </div>
    </card-page>
</template>

<script setup lang="ts">
import { toRefs } from "@vueuse/core";
import { delay } from "es-toolkit";
import { computed, ref } from "vue";
import CardPage from "~/components/Common/CardPage.vue";
import { useDialog } from "~/components/Common/Dialogs/useDialog.ts";
import { useNotify } from "~/components/Common/Toasts/useNotify.ts";
import { useStationProfileData } from "~/components/Stations/Profile/useProfileQuery.ts";
import {
    ApiStatus,
    FlashLevels,
    StationPermissions,
} from "~/entities/ApiInterfaces.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import useNowPlaying from "~/functions/useNowPlaying";
import {
    useClearAllStationQueries,
    useStationData,
} from "~/functions/useStationQuery.ts";
import { useUserAllowedForStation } from "~/functions/useUserallowedForStation.ts";
import { useAxios } from "~/vendor/axios";
import { useTranslate } from "~/vendor/gettext";

const { $gettext } = useTranslate();
const stationData = useStationData();
const profileData = useStationProfileData();
const { nowPlayingProps } = toRefs(profileData);
const { np } = useNowPlaying(nowPlayingProps);

const { userAllowedForStation } = useUserAllowedForStation();
const canBroadcast = computed(() =>
    userAllowedForStation(StationPermissions.Broadcasting),
);
const canManageMedia = computed(() =>
    userAllowedForStation(StationPermissions.Media),
);

const backendRunning = computed(
    () => profileData.value.services?.backendRunning ?? false,
);
const queueIsEmpty = computed(
    () => (profileData.value.ops?.queueDepth ?? 0) === 0,
);
const isLiveDj = computed(() => profileData.value.ops?.isStreamerLive ?? false);
const connectedStreamer = computed(
    () => profileData.value.ops?.connectedStreamer ?? null,
);

const liveDjMessage = computed(() => {
    const name = connectedStreamer.value;
    if (name) {
        return $gettext(
            "%{dj} is live. AutoDJ yields until the DJ disconnects.",
            { dj: name },
        );
    }
    return $gettext(
        "A live DJ is connected. AutoDJ yields until they disconnect.",
    );
});

const startLabel = computed(() =>
    backendRunning.value
        ? $gettext("Restart AutoDJ")
        : $gettext("Start AutoDJ"),
);

const showUploadLink = computed(
    () =>
        canManageMedia.value &&
        (!stationData.value.hasStarted ||
            (backendRunning.value && queueIsEmpty.value)),
);

const { getStationApiUrl } = useApiRouter();
const restartUrl = getStationApiUrl("/restart");
const { axios } = useAxios();
const { showAlert } = useDialog();
const { notify } = useNotify();
const clearAllStationQueries = useClearAllStationQueries();
const starting = ref(false);

const doStart = async () => {
    const { value } = await showAlert({
        title: backendRunning.value
            ? $gettext("Restart AutoDJ?")
            : $gettext("Start AutoDJ?"),
        confirmButtonClass: "btn-primary",
        confirmButtonText: startLabel.value,
        focusCancel: false,
    });

    if (!value) {
        return;
    }

    starting.value = true;
    try {
        const { data } = await axios.post<ApiStatus>(restartUrl.value);
        notify(data.formatted_message, {
            variant: data.success ? FlashLevels.Success : FlashLevels.Warning,
        });
        await delay(1500);
        await clearAllStationQueries();
    } finally {
        starting.value = false;
    }
};
</script>
