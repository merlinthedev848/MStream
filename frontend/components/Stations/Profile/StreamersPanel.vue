<template>
    <card-page header-id="hdr_streamers">
        <template #header="{id}">
            <div class="d-flex align-items-center">
                <h3
                    :id="id"
                    class="card-title flex-fill my-0"
                >
                    {{ $gettext('Streamers/DJs') }}
                </h3>
                <div class="flex-shrink-0">
                    <enabled-badge :enabled="stationData.enableStreamers"/>
                </div>
            </div>
        </template>

        <div class="card-body py-3">
            <div class="broadcast-ops-grid">
                <div class="broadcast-ops-cell">
                    <div class="broadcast-ops-label">
                        {{ $gettext('Connection') }}
                    </div>
                    <div class="broadcast-ops-value">
                        <span
                            v-if="isLive"
                            class="badge broadcast-tally-live"
                        >
                            {{ $gettext('LIVE') }}
                        </span>
                        <span
                            v-else-if="!stationData.enableStreamers"
                            class="badge broadcast-tally-standby"
                        >
                            {{ $gettext('OFF') }}
                        </span>
                        <span
                            v-else
                            class="badge broadcast-tally-standby"
                        >
                            {{ $gettext('STANDBY') }}
                        </span>
                    </div>
                </div>
                <div class="broadcast-ops-cell">
                    <div class="broadcast-ops-label">
                        {{ $gettext('Connected Streamer') }}
                    </div>
                    <div
                        class="broadcast-ops-value broadcast-ops-streamer"
                        :class="isLive ? 'text-success' : 'text-muted'"
                    >
                        {{ connectedStreamerLabel }}
                    </div>
                </div>
            </div>
        </div>

        <template
            v-if="(stationData.enableStreamers && (stationData.enablePublicPages || userAllowedForStation(StationPermissions.Streamers))) || userAllowedForStation(StationPermissions.Profile)"
            #footer_actions
        >
            <template v-if="stationData.enableStreamers">
                <a
                    v-if="stationData.enablePublicPages"
                    :href="stationData.webDjUrl"
                    target="_blank"
                    class="btn btn-link text-secondary"
                >
                    <icon-ic-mic/>

                    <span>
                        {{ $gettext('Web DJ') }}
                    </span>
                </a>
                <router-link
                    v-if="userAllowedForStation(StationPermissions.Streamers)"
                    class="btn btn-link text-primary"
                    :to="{name: 'stations:streamers:index'}"
                >
                    <icon-ic-settings/>

                    <span>
                        {{ $gettext('Manage') }}
                    </span>
                </router-link>
                <button
                    v-if="userAllowedForStation(StationPermissions.Profile)"
                    type="button"
                    class="btn btn-link text-danger"
                    @click="toggleStreamers"
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
                    @click="toggleStreamers"
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
import { useTranslate } from "~/vendor/gettext";
import IconIcCheck from "~icons/ic/baseline-check";
import IconIcClose from "~icons/ic/baseline-close";
import IconIcMic from "~icons/ic/baseline-mic";
import IconIcSettings from "~icons/ic/baseline-settings";

const stationData = useStationData();
const profileData = useStationProfileData();

const { userAllowedForStation } = useUserAllowedForStation();
const { $gettext } = useTranslate();

const isLive = computed(() => Boolean(profileData.value.ops?.isStreamerLive));

const connectedStreamerLabel = computed(() => {
    if (!isLive.value) {
        return $gettext("None");
    }

    const name = profileData.value.ops?.connectedStreamer?.trim();
    return name && name.length > 0 ? name : $gettext("Live DJ");
});

const toggleStreamers = useToggleFeature(
    "enable_streamers",
    computed(() => stationData.value.enableStreamers),
);
</script>
