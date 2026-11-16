<template>
    <div class="navdrawer-header offcanvas-header">
        <router-link
            :to="{ name: 'stations:index' }"
            class="navbar-brand"
        >
            {{ name }}
            <div
                id="station-time"
                class="fs-6"
                :title="$gettext('Station Time')"
            >
                {{ clock }}
            </div>
        </router-link>
    </div>

    <template v-if="userAllowedForStation(StationPermissions.Broadcasting)">
        <div
            v-if="needsRestart && hasStarted"
            class="navdrawer-alert bg-warning-subtle text-warning-emphasis"
        >
            <router-link
                :to="{name: 'stations:restart:index'}"
            >
                <span class="fw-bold">{{ $gettext('Reload to Apply Changes') }}</span><br>
                <small>
                    {{ $gettext('Your station has changes that require a reload to apply.') }}
                </small>
            </router-link>
        </div>
    </template>

    <div class="offcanvas-body d-flex flex-column h-100">
        <sidebar-menu :menu="menuItems" />

        <ul
            v-if="canManageFleet"
            class="navdrawer-nav mt-auto pt-2 border-top"
        >
            <li class="nav-item">
                <button
                    type="button"
                    class="nav-link text-warning"
                    :disabled="adminBusy"
                    @click="doToggleSuspend"
                >
                    {{
                        isEnabled
                            ? $gettext('Suspend Service')
                            : $gettext('Unsuspend Service')
                    }}
                </button>
            </li>
            <li class="nav-item">
                <button
                    type="button"
                    class="nav-link text-danger"
                    :disabled="adminBusy"
                    @click="doDeleteService"
                >
                    {{ $gettext('Delete Service') }}
                </button>
            </li>
        </ul>
    </div>

    <update-metadata-modal ref="$updateMetadataModal"/>
</template>

<script setup lang="ts">
import { toRefs, useIntervalFn } from "@vueuse/core";
import { computed, ref, useTemplateRef } from "vue";
import { useRouter } from "vue-router";
import { useDialog } from "~/components/Common/Dialogs/useDialog.ts";
import SidebarMenu from "~/components/Common/SidebarMenu.vue";
import { useNotify } from "~/components/Common/Toasts/useNotify.ts";
import { useStationsMenu } from "~/components/Stations/menu";
import UpdateMetadataModal from "~/components/Stations/Profile/UpdateMetadataModal.vue";
import {
    GlobalPermissions,
    StationPermissions,
} from "~/entities/ApiInterfaces.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import useStationDateTimeFormatter from "~/functions/useStationDateTimeFormatter.ts";
import {
    useClearAllStationQueries,
    useStationData,
} from "~/functions/useStationQuery.ts";
import { useUserAllowed } from "~/functions/useUserAllowed.ts";
import { useUserAllowedForStation } from "~/functions/useUserallowedForStation.ts";
import { useAxios } from "~/vendor/axios.ts";
import { useTranslate } from "~/vendor/gettext.ts";
import { useLuxon } from "~/vendor/luxon.ts";

const $updateMetadataModal = useTemplateRef("$updateMetadataModal");

const menuItems = useStationsMenu({
    onUpdateSongTitle: () => {
        $updateMetadataModal.value?.open();
    },
});
const { userAllowedForStation } = useUserAllowedForStation();
const { userAllowed } = useUserAllowed();
const canManageFleet = computed(() => userAllowed(GlobalPermissions.Stations));

const stationData = useStationData();
const { name, hasStarted, needsRestart, timezone, isEnabled, id } =
    toRefs(stationData);

const { DateTime } = useLuxon();
const { now, formatDateTimeAsTime } = useStationDateTimeFormatter(timezone);
const { $gettext } = useTranslate();
const { showAlert } = useDialog();
const { notifySuccess, notifyError } = useNotify();
const { axios } = useAxios();
const { getApiUrl } = useApiRouter();
const router = useRouter();
const clearStationQueries = useClearAllStationQueries();

const adminStationUrl = getApiUrl(computed(() => `/admin/station/${id.value}`));

const adminBusy = ref(false);

const clock = ref("");

useIntervalFn(
    () => {
        clock.value = formatDateTimeAsTime(
            now(),
            DateTime.TIME_WITH_SHORT_OFFSET,
        );
    },
    1000,
    {
        immediate: true,
        immediateCallback: true,
    },
);

const doToggleSuspend = async () => {
    const suspending = isEnabled.value;
    const { value } = await showAlert(
        suspending
            ? {
                  title: $gettext("Suspend service?"),
                  confirmButtonText: $gettext("Suspend"),
                  confirmButtonClass: "btn-warning",
                  focusCancel: true,
              }
            : {
                  title: $gettext("Unsuspend service?"),
                  confirmButtonText: $gettext("Unsuspend"),
                  confirmButtonClass: "btn-success",
                  focusCancel: false,
              },
    );

    if (!value) {
        return;
    }

    adminBusy.value = true;
    try {
        const { data } = await axios.put(adminStationUrl.value, {
            is_enabled: !suspending,
        });
        notifySuccess(data.message ?? $gettext("Service updated."));
        await clearStationQueries();
    } catch {
        notifyError($gettext("Could not update service status."));
    } finally {
        adminBusy.value = false;
    }
};

const doDeleteService = async () => {
    const { value } = await showAlert({
        title: $gettext("Delete Service?"),
        confirmButtonText: $gettext("Delete"),
        confirmButtonClass: "btn-danger",
        focusCancel: true,
    });

    if (!value) {
        return;
    }

    adminBusy.value = true;
    try {
        const { data } = await axios.delete(adminStationUrl.value);
        notifySuccess(data.message ?? $gettext("Service deleted."));
        await router.push({ name: "admin:stations:index" });
    } catch {
        notifyError($gettext("Could not delete service."));
    } finally {
        adminBusy.value = false;
    }
};
</script>
