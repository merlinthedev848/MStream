<template>
    <div class="row-of-cards">
        <card-page
            header-id="hdr_widgets_links"
            :title="$gettext('Widgets & Links')"
        >
            <template #info>
                <p class="card-text mb-0">
                    {{
                        $gettext(
                            'Copy listener and public URLs, or build an embed. This is the share hub for your service.',
                        )
                    }}
                </p>
            </template>
            <template #actions>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="!stationData.enablePublicPages"
                    @click="openEmbed"
                >
                    {{ $gettext('Embed Widgets') }}
                </button>
            </template>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <tbody>
                            <tr v-if="listenerUrl">
                                <th scope="row">
                                    {{ $gettext('Listener Stream') }}
                                </th>
                                <td>
                                    <code class="broadcast-mono">{{ listenerUrl }}</code>
                                </td>
                                <td class="text-end text-nowrap">
                                    <copy-to-clipboard-button :text="listenerUrl" />
                                    <a
                                        class="btn btn-sm btn-outline-primary ms-1"
                                        :href="listenerUrl"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        {{ $gettext('Open') }}
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="stationData.enablePublicPages">
                                <th scope="row">
                                    {{ $gettext('Public Page') }}
                                </th>
                                <td>
                                    <code class="broadcast-mono">{{ stationData.publicPageUrl }}</code>
                                </td>
                                <td class="text-end text-nowrap">
                                    <copy-to-clipboard-button :text="stationData.publicPageUrl" />
                                    <a
                                        class="btn btn-sm btn-outline-primary ms-1"
                                        :href="stationData.publicPageUrl"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        {{ $gettext('Open') }}
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="stationData.enablePublicPages">
                                <th scope="row">
                                    {{ $gettext('Public Schedule') }}
                                </th>
                                <td>
                                    <code class="broadcast-mono">{{ stationData.publicScheduleUrl }}</code>
                                </td>
                                <td class="text-end text-nowrap">
                                    <copy-to-clipboard-button :text="stationData.publicScheduleUrl" />
                                    <a
                                        class="btn btn-sm btn-outline-primary ms-1"
                                        :href="stationData.publicScheduleUrl"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        {{ $gettext('Open') }}
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="stationData.enableOnDemand">
                                <th scope="row">
                                    {{ $gettext('On-Demand') }}
                                </th>
                                <td>
                                    <code class="broadcast-mono">{{ stationData.onDemandUrl }}</code>
                                </td>
                                <td class="text-end text-nowrap">
                                    <copy-to-clipboard-button :text="stationData.onDemandUrl" />
                                    <a
                                        class="btn btn-sm btn-outline-primary ms-1"
                                        :href="stationData.onDemandUrl"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        {{ $gettext('Open') }}
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="stationData.enablePublicPages">
                                <th scope="row">
                                    {{ $gettext('Podcasts') }}
                                </th>
                                <td>
                                    <code class="broadcast-mono">{{ stationData.publicPodcastsUrl }}</code>
                                </td>
                                <td class="text-end text-nowrap">
                                    <copy-to-clipboard-button :text="stationData.publicPodcastsUrl" />
                                    <a
                                        class="btn btn-sm btn-outline-primary ms-1"
                                        :href="stationData.publicPodcastsUrl"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        {{ $gettext('Open') }}
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="stationData.enableStreamers && stationData.enablePublicPages">
                                <th scope="row">
                                    {{ $gettext('Browser DJ') }}
                                </th>
                                <td>
                                    <code class="broadcast-mono">{{ stationData.webDjUrl }}</code>
                                </td>
                                <td class="text-end text-nowrap">
                                    <copy-to-clipboard-button :text="stationData.webDjUrl" />
                                    <a
                                        class="btn btn-sm btn-outline-primary ms-1"
                                        :href="stationData.webDjUrl"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        {{ $gettext('Open') }}
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p
                    v-if="!listenerUrl && !stationData.enablePublicPages"
                    class="text-muted mb-0 mt-3"
                >
                    {{
                        $gettext(
                            'No shareable URLs yet. Add a mount point or enable the public page under Configure.',
                        )
                    }}
                </p>

                <div class="mt-3">
                    <router-link
                        v-if="userAllowedForStation(StationPermissions.Profile)"
                        class="btn btn-sm btn-outline-secondary"
                        :to="{ name: 'stations:branding' }"
                    >
                        {{ $gettext('Edit Public Page Branding') }}
                    </router-link>
                    <router-link
                        v-if="userAllowedForStation(StationPermissions.MountPoints)"
                        class="btn btn-sm btn-outline-secondary ms-2"
                        :to="{ name: 'stations:mounts:index' }"
                    >
                        {{ $gettext('Manage Mount Points') }}
                    </router-link>
                </div>
            </div>
        </card-page>

        <widget-modal ref="$widgetModal" />
    </div>
</template>

<script setup lang="ts">
import { computed, useTemplateRef } from "vue";
import CardPage from "~/components/Common/CardPage.vue";
import CopyToClipboardButton from "~/components/Common/CopyToClipboardButton.vue";
import { useStationProfileData } from "~/components/Stations/Profile/useProfileQuery.ts";
import WidgetModal from "~/components/Stations/Profile/WidgetModal.vue";
import { StationPermissions } from "~/entities/ApiInterfaces.ts";
import { useStationData } from "~/functions/useStationQuery.ts";
import { useUserAllowedForStation } from "~/functions/useUserallowedForStation.ts";

const stationData = useStationData();
const profileData = useStationProfileData();
const { userAllowedForStation } = useUserAllowedForStation();

const listenerUrl = computed(() => {
    const mounts = profileData.value.station?.mounts ?? [];
    if (mounts.length > 0 && mounts[0].url) {
        return mounts[0].url;
    }
    if (
        profileData.value.station?.hls_enabled &&
        profileData.value.station.hls_url
    ) {
        return profileData.value.station.hls_url;
    }
    return null;
});

const $widgetModal = useTemplateRef("$widgetModal");

const openEmbed = () => {
    $widgetModal.value?.open();
};
</script>
