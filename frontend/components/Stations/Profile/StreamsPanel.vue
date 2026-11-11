<template>
    <card-page
        header-id="hdr_streams"
        :title="$gettext('Listener Streams')"
    >
        <table class="table table-striped table-responsive mb-0">
            <colgroup>
                <col style="width: 2%;">
                <col style="width: 78%;">
                <col style="width: 20%;">
            </colgroup>
            <template v-if="profileData.station.mounts.length > 0">
                <thead>
                    <tr>
                        <th colspan="2">
                            {{ $gettext('Local Streams') }}
                        </th>
                        <th class="text-end">
                            {{ $gettext('Listeners') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="mount in profileData.station.mounts"
                        :key="mount.id"
                        class="align-middle"
                    >
                        <td class="pe-1">
                            <play-button
                                class="btn-lg"
                                :stream="{
                                    url: mount.url,
                                    title: mount.name,
                                    isStream: true,
                                }"
                            />
                        </td>
                        <td class="ps-1">
                            <h6 class="mb-1">
                                {{ mount.name }}
                                <small
                                    v-if="showFormatAndBitrate(mount.format, mount.bitrate)"
                                    class="text-muted broadcast-mono ms-2"
                                >
                                    {{ showFormatAndBitrate(mount.format, mount.bitrate) }}
                                </small>
                            </h6>
                            <a
                                :href="mount.url"
                                target="_blank"
                            >{{ mount.url }}</a>
                        </td>
                        <td class="ps-1 text-end broadcast-mono">
                            <icon-ic-headphones class="sm align-middle"/>
                            <span class="listeners-total ps-1 font-weight-bold">{{ mount.listeners.total }}</span><br>
                            <small class="text-muted">
                                <span class="listeners-unique pe-1">{{ mount.listeners.unique }}</span>
                                {{ $gettext('Unique') }}
                            </small>
                        </td>
                    </tr>
                </tbody>
            </template>

            <template v-if="profileData.station.remotes.length > 0">
                <thead>
                    <tr>
                        <th colspan="2">
                            {{ $gettext('Remote Relays') }}
                        </th>
                        <th class="text-end">
                            {{ $gettext('Listeners') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="remote in profileData.station.remotes"
                        :key="remote.id"
                        class="align-middle"
                    >
                        <td class="pe-1">
                            <play-button
                                class="btn-lg"
                                :stream="{
                                    url: remote.url,
                                    title: remote.name,
                                    isStream: true,
                                }"
                            />
                        </td>
                        <td class="ps-1">
                            <h6 class="mb-1">
                                {{ remote.name }}
                                <small
                                    v-if="showFormatAndBitrate(remote.format, remote.bitrate)"
                                    class="text-muted broadcast-mono ms-2"
                                >
                                    {{ showFormatAndBitrate(remote.format, remote.bitrate) }}
                                </small>
                            </h6>
                            <a
                                :href="remote.url"
                                target="_blank"
                            >{{ remote.url }}</a>
                        </td>
                        <td class="ps-1 text-end broadcast-mono">
                            <icon-ic-headphones class="sm align-middle"/>
                            <span class="listeners-total ps-1 font-weight-bold">{{ remote.listeners.total }}</span><br>
                            <small class="text-muted">
                                <span class="listeners-unique pe-1">{{ remote.listeners.unique }}</span>
                                {{ $gettext('Unique') }}
                            </small>
                        </td>
                    </tr>
                </tbody>
            </template>

            <template v-if="profileData.station.hls_enabled">
                <thead>
                    <tr>
                        <th colspan="2">
                            {{ $gettext('HTTP Live Streaming (HLS)') }}
                        </th>
                        <th class="text-end">
                            {{ $gettext('Listeners') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="align-middle">
                        <td class="pe-1">
                            <play-button
                                class="btn-lg"
                                :stream="{
                                    url: profileData.station.hls_url,
                                    title: $gettext('HLS'),
                                    isStream: true,
                                    isHls: true
                                }"
                            />
                        </td>
                        <td class="ps-1">
                            <a
                                v-if="profileData.station.hls_url"
                                :href="profileData.station.hls_url"
                                target="_blank"
                            >{{ profileData.station.hls_url }}</a>
                        </td>
                        <td class="ps-1 text-end broadcast-mono">
                            <icon-ic-headphones class="sm align-middle"/>
                            <span class="listeners-total ps-1 font-weight-bold">
                                {{ profileData.station.hls_listeners }}
                            </span><br>
                            <small class="text-muted">
                                {{ $gettext('Unique') }}
                            </small>
                        </td>
                    </tr>
                </tbody>
            </template>

            <tbody v-if="!hasAnyStreams">
                <tr class="align-middle streams-empty-row">
                    <td
                        colspan="3"
                        class="text-muted py-3"
                    >
                        <span>
                            {{
                                $gettext(
                                    'No listener streams yet. Add a stream under Mount Points → Listener Streams.',
                                )
                            }}
                        </span>
                        <router-link
                            class="btn btn-link btn-sm px-1"
                            :to="{ name: 'stations:mounts:index' }"
                        >
                            {{ $gettext('Open Listener Streams') }}
                        </router-link>
                    </td>
                </tr>
            </tbody>
        </table>

        <template #footer_actions>
            <a
                class="btn btn-link text-primary"
                :href="profileData.station.playlist_pls_url"
            >
                <icon-ic-cloud-download/>
                <span>
                    {{ $gettext('Download PLS') }}
                </span>
            </a>
            <a
                class="btn btn-link text-primary"
                :href="profileData.station.playlist_m3u_url"
            >
                <icon-ic-cloud-download/>

                <span>
                    {{ $gettext('Download M3U') }}
                </span>
            </a>
        </template>
    </card-page>
</template>

<script setup lang="ts">
import { computed } from "vue";
import PlayButton from "~/components/Common/Audio/PlayButton.vue";
import CardPage from "~/components/Common/CardPage.vue";
import { useStationProfileData } from "~/components/Stations/Profile/useProfileQuery.ts";
import showFormatAndBitrate from "~/functions/showFormatAndBitrate";
import IconIcCloudDownload from "~icons/ic/baseline-cloud-download";
import IconIcHeadphones from "~icons/ic/baseline-headphones";

const profileData = useStationProfileData();

const hasAnyStreams = computed(() => {
    const station = profileData.value.station;
    return (
        station.mounts.length > 0 ||
        station.remotes.length > 0 ||
        station.hls_enabled
    );
});
</script>
