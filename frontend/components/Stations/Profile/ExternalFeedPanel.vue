<template>
    <card-page
        v-if="remotePlaylists.length > 0"
        header-id="hdr_external_feeds"
        :title="$gettext('External Feeds')"
    >
        <div class="card-body">
            <p class="card-text text-muted mb-3">
                {{
                    $gettext(
                        'Remote URL playlists used for news beds and third-party live ingest. Prefer interrupt + schedule for automated bulletins; use Live DJ for human readers.',
                    )
                }}
            </p>
            <ul class="list-unstyled mb-0">
                <li
                    v-for="playlist in remotePlaylists"
                    :key="playlist.id"
                    class="d-flex align-items-start justify-content-between gap-2 py-2 border-bottom border-secondary-subtle"
                >
                    <div>
                        <div class="fw-semibold">
                            {{ playlist.name }}
                        </div>
                        <div class="broadcast-mono small text-muted text-break">
                            {{ playlist.remote_url || $gettext('No URL set') }}
                        </div>
                        <div class="mt-1 d-flex flex-wrap gap-1">
                            <span
                                v-if="playlist.backend_options?.includes('interrupt')"
                                class="badge broadcast-tally-live"
                            >
                                {{ $gettext('INTERRUPT') }}
                            </span>
                            <span
                                v-if="(playlist.schedule_items?.length ?? 0) > 0"
                                class="badge text-bg-info"
                            >
                                {{ $gettext('Scheduled') }}
                            </span>
                            <span
                                v-if="!playlist.is_enabled"
                                class="badge broadcast-tally-standby"
                            >
                                {{ $gettext('Disabled') }}
                            </span>
                        </div>
                    </div>
                    <router-link
                        class="btn btn-sm btn-outline-primary flex-shrink-0"
                        :to="{
                            name: 'stations:playlists:index',
                            query: { filter: 'commercial' },
                        }"
                    >
                        {{ $gettext('Manage') }}
                    </router-link>
                </li>
            </ul>
        </div>
        <template #footer_actions>
            <router-link
                class="btn btn-link text-primary"
                :to="{ name: 'stations:playlists:index' }"
            >
                {{ $gettext('Open Playlists') }}
            </router-link>
        </template>
    </card-page>
</template>

<script setup lang="ts">
import { useQuery } from "@tanstack/vue-query";
import { computed } from "vue";
import CardPage from "~/components/Common/CardPage.vue";
import {
    PlaylistSources,
    StationPlaylist,
} from "~/entities/ApiInterfaces.ts";
import { QueryKeys, queryKeyWithStation } from "~/entities/Queries.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import { useStationData } from "~/functions/useStationQuery.ts";
import { useAxios } from "~/vendor/axios";

const stationData = useStationData();
const { getStationApiUrl } = useApiRouter();
const { axiosSilent } = useAxios();

const listUrl = getStationApiUrl("/playlists");

const { data } = useQuery<{ rows?: StationPlaylist[] }>({
    queryKey: queryKeyWithStation([QueryKeys.StationPlaylists, "feeds"]),
    queryFn: async ({ signal }) => {
        const { data: response } = await axiosSilent.get(listUrl.value, {
            signal,
            params: {
                rowCount: 0,
                internal: true,
                "filter[source]": PlaylistSources.RemoteUrl,
            },
        });
        return response;
    },
    enabled: computed(() => stationData.value.features.media),
    staleTime: 30 * 1000,
});

const remotePlaylists = computed(() =>
    (data.value?.rows ?? []).filter(
        (playlist) => playlist.source === PlaylistSources.RemoteUrl,
    ),
);
</script>
