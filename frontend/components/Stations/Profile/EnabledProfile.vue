<template>
    <profile-header/>

    <div
        id="profile"
        class="row row-of-cards"
    >
        <div class="col-12 col-lg-8">
            <service-status-panel />

            <listener-url-panel />

            <service-quota-panel />

            <getting-started-panel />
        </div>

        <div class="col-12">
            <div class="mb-3">
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary"
                    :aria-expanded="showDetails"
                    aria-controls="profile-overview-details"
                    @click="showDetails = !showDetails"
                >
                    {{
                        showDetails
                            ? $gettext('Hide details')
                            : $gettext('Show details')
                    }}
                </button>
            </div>

            <div
                v-show="showDetails"
                id="profile-overview-details"
                class="row row-of-cards"
            >
                <div class="col-lg-8">
                    <template v-if="stationData.hasStarted">
                        <profile-now-playing/>

                        <usage-graphs-panel />
                    </template>
                </div>

                <div class="col-lg-4">
                    <template v-if="showDetails">
                        <external-feed-panel />

                        <profile-streams/>

                        <profile-public-pages/>

                        <profile-requests v-if="hasActiveBackend"/>

                        <profile-streamers v-if="hasActiveBackend"/>

                        <template v-if="hasActiveFrontend">
                            <profile-frontend/>
                        </template>

                        <template v-if="hasActiveBackend">
                            <profile-backend/>
                        </template>
                        <template v-else>
                            <profile-backend-none />
                        </template>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import ProfileBackendNone from "~/components/Stations/Profile/BackendNonePanel.vue";
import ProfileBackend from "~/components/Stations/Profile/BackendPanel.vue";
import ExternalFeedPanel from "~/components/Stations/Profile/ExternalFeedPanel.vue";
import ProfileFrontend from "~/components/Stations/Profile/FrontendPanel.vue";
import GettingStartedPanel from "~/components/Stations/Profile/GettingStartedPanel.vue";
import ProfileHeader from "~/components/Stations/Profile/HeaderPanel.vue";
import ListenerUrlPanel from "~/components/Stations/Profile/ListenerUrlPanel.vue";
import ProfileNowPlaying from "~/components/Stations/Profile/NowPlayingPanel.vue";
import ProfilePublicPages from "~/components/Stations/Profile/PublicPagesPanel.vue";
import ProfileRequests from "~/components/Stations/Profile/RequestsPanel.vue";
import ServiceQuotaPanel from "~/components/Stations/Profile/ServiceQuotaPanel.vue";
import ServiceStatusPanel from "~/components/Stations/Profile/ServiceStatusPanel.vue";
import ProfileStreamers from "~/components/Stations/Profile/StreamersPanel.vue";
import ProfileStreams from "~/components/Stations/Profile/StreamsPanel.vue";
import UsageGraphsPanel from "~/components/Stations/Profile/UsageGraphsPanel.vue";
import { BackendAdapters, FrontendAdapters } from "~/entities/ApiInterfaces.ts";
import { useStationData } from "~/functions/useStationQuery.ts";

const stationData = useStationData();
const showDetails = ref(false);

const hasActiveFrontend = computed(() => {
    return stationData.value.frontendType !== FrontendAdapters.Remote;
});

const hasActiveBackend = computed(() => {
    return stationData.value.backendType !== BackendAdapters.None;
});
</script>
