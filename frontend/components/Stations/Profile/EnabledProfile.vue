<template>
    <profile-header/>

    <div
        id="profile"
        class="row row-of-cards"
    >
        <div class="col-lg-8">
            <service-status-panel />

            <service-quota-panel />

            <listener-url-panel />

            <getting-started-panel />

            <template v-if="stationData.hasStarted">
                <profile-now-playing/>

                <usage-graphs-panel />
            </template>
        </div>

        <div class="col-lg-4">
            <card-page
                id="profile-more-tools"
                header-id="hdr_more_tools"
                :title="$gettext('More Tools')"
            >
                <div class="list-group list-group-flush">
                    <router-link
                        class="list-group-item list-group-item-action"
                        :to="{ name: 'stations:files:index' }"
                    >
                        {{ $gettext('Media Library') }}
                    </router-link>
                    <router-link
                        class="list-group-item list-group-item-action"
                        :to="{ name: 'stations:playlists:index' }"
                    >
                        {{ $gettext('Playlists') }}
                    </router-link>
                    <router-link
                        class="list-group-item list-group-item-action"
                        :to="{ name: 'stations:widgets-links' }"
                    >
                        {{ $gettext('Widgets & Links') }}
                    </router-link>
                    <router-link
                        class="list-group-item list-group-item-action"
                        :to="{ name: 'stations:branding' }"
                    >
                        {{ $gettext('Public Page') }}
                    </router-link>
                    <router-link
                        v-if="hasActiveBackend"
                        class="list-group-item list-group-item-action"
                        :to="{ name: 'stations:streamers:index' }"
                    >
                        {{ $gettext('DJ Manager') }}
                    </router-link>
                    <router-link
                        class="list-group-item list-group-item-action"
                        :to="{ name: 'stations:reports:overview' }"
                    >
                        {{ $gettext('Reporting') }}
                    </router-link>
                    <button
                        type="button"
                        class="list-group-item list-group-item-action"
                        @click="showAdvanced = !showAdvanced"
                    >
                        {{
                            showAdvanced
                                ? $gettext('Hide advanced panels')
                                : $gettext('Show advanced panels')
                        }}
                    </button>
                </div>
            </card-page>

            <template v-if="showAdvanced">
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
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import CardPage from "~/components/Common/CardPage.vue";
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
const showAdvanced = ref(false);

const hasActiveFrontend = computed(() => {
    return stationData.value.frontendType !== FrontendAdapters.Remote;
});

const hasActiveBackend = computed(() => {
    return stationData.value.backendType !== BackendAdapters.None;
});
</script>
