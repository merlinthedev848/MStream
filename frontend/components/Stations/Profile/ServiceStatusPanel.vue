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
                    v-else-if="np.is_online"
                    class="badge broadcast-tally-onair"
                >
                    {{ $gettext('SOURCE: CONNECTED') }}
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
                <template v-else-if="np.is_online">
                    {{
                        $gettext(
                            'AutoDJ or a live encoder is connected. Share the listener URL below.',
                        )
                    }}
                </template>
                <template v-else-if="stationData.hasStarted">
                    {{
                        $gettext(
                            'Broadcasting is configured but the source is not connected. Start AutoDJ or connect an encoder.',
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
                    v-if="canManageMedia && !stationData.hasStarted"
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

const startLabel = computed(() =>
    stationData.value.hasStarted
        ? $gettext("Restart AutoDJ")
        : $gettext("Start AutoDJ"),
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
        title: stationData.value.hasStarted
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
