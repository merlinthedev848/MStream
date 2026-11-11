<template>
    <card-page header-id="hdr_song_requests">
        <template #header="{id}">
            <div class="d-flex align-items-center">
                <h3
                    :id="id"
                    class="card-title flex-fill my-0"
                >
                    {{ $gettext('Song Requests') }}
                </h3>
                <div class="flex-shrink-0">
                    <enabled-badge :enabled="stationData.enableRequests"/>
                </div>
            </div>
        </template>

        <div class="card-body py-3">
            <div class="broadcast-ops-grid">
                <div class="broadcast-ops-cell">
                    <div class="broadcast-ops-label">
                        {{ $gettext('Pending') }}
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
                        {{ $gettext('Requests') }}
                    </div>
                    <div class="broadcast-ops-value">
                        <span
                            v-if="!stationData.enableRequests"
                            class="badge broadcast-tally-standby"
                        >
                            {{ $gettext('OFF') }}
                        </span>
                        <span
                            v-else-if="pendingCount > 0"
                            class="badge broadcast-tally-live"
                        >
                            {{ $gettext('PENDING') }}
                        </span>
                        <span
                            v-else
                            class="badge broadcast-tally-onair"
                        >
                            {{ $gettext('CLEAR') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <template
            v-if="userAllowedForStation(StationPermissions.Broadcasting) || userAllowedForStation(StationPermissions.Profile)"
            #footer_actions
        >
            <template v-if="stationData.enableRequests">
                <router-link
                    v-if="userAllowedForStation(StationPermissions.Broadcasting)"
                    class="btn btn-link text-primary"
                    :to="{name: 'stations:reports:requests'}"
                >
                    <icon-ic-assignment/>
                    <span>
                        {{ $gettext('View') }}
                    </span>
                </router-link>
                <button
                    v-if="userAllowedForStation(StationPermissions.Profile)"
                    type="button"
                    class="btn btn-link text-danger"
                    @click="toggleRequests"
                >
                    <icon-ic-close/>

                    <span>
                        {{ $gettext('Disable') }}
                    </span>
                </button>
            </template>
            <template v-else>
                <button
                    v-if="userAllowedForStation(StationPermissions.Profile)"
                    type="button"
                    class="btn btn-link text-success"
                    @click="toggleRequests"
                >
                    <icon-ic-check/>

                    <span>
                        {{ $gettext('Enable') }}
                    </span>
                </button>
            </template>
        </template>
    </card-page>
</template>

<script setup lang="ts">
import { computed } from "vue";
import EnabledBadge from "~/components/Common/Badges/EnabledBadge.vue";
import CardPage from "~/components/Common/CardPage.vue";
import { useStationProfileData } from "~/components/Stations/Profile/useProfileQuery.ts";
import useToggleFeature from "~/components/Stations/Profile/useToggleFeature";
import { StationPermissions } from "~/entities/ApiInterfaces.ts";
import { useStationData } from "~/functions/useStationQuery.ts";
import { useUserAllowedForStation } from "~/functions/useUserallowedForStation.ts";
import IconIcAssignment from "~icons/ic/baseline-assignment";
import IconIcCheck from "~icons/ic/baseline-check";
import IconIcClose from "~icons/ic/baseline-close";

const stationData = useStationData();
const profileData = useStationProfileData();

const { userAllowedForStation } = useUserAllowedForStation();

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

const toggleRequests = useToggleFeature(
    "enable_requests",
    computed(() => stationData.value.enableRequests),
);
</script>
