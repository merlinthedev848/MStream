<template>
    <section
        class="card mb-3"
        role="region"
        aria-labelledby="hdr_station_fleet"
    >
        <div class="card-header text-bg-primary d-flex align-items-center flex-wrap gap-2">
            <h2
                id="hdr_station_fleet"
                class="card-title mb-0 flex-fill"
            >
                {{ $gettext('Station Fleet') }}
            </h2>
            <router-link
                class="btn btn-dark btn-sm py-2"
                :to="{ name: 'admin:stations:index' }"
            >
                {{ $gettext('Manage Stations') }}
            </router-link>
        </div>

        <div class="card-body">
            <loading
                :loading="isLoading"
                lazy
            >
                <div class="admin-fleet-strip mb-0">
                    <div class="admin-fleet-cell">
                        <span class="admin-fleet-label">{{ $gettext('Stations') }}</span>
                        <span class="admin-fleet-value broadcast-mono">{{ totals.stations }}</span>
                    </div>
                    <div class="admin-fleet-cell">
                        <span class="admin-fleet-label">{{ $gettext('On Air') }}</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge broadcast-tally-onair">{{ $gettext('ON AIR') }}</span>
                            <span class="admin-fleet-value broadcast-mono">{{ totals.online }}</span>
                        </div>
                    </div>
                    <div class="admin-fleet-cell">
                        <span class="admin-fleet-label">{{ $gettext('Offline') }}</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge broadcast-tally-standby">{{ $gettext('OFFLINE') }}</span>
                            <span class="admin-fleet-value broadcast-mono">{{ totals.offline }}</span>
                        </div>
                    </div>
                    <div class="admin-fleet-cell">
                        <span class="admin-fleet-label">{{ $gettext('Listeners') }}</span>
                        <span class="admin-fleet-value broadcast-mono">{{ totals.listeners }}</span>
                    </div>
                </div>

                <p
                    v-if="totals.stations === 0"
                    class="admin-section-lead mt-3 mb-0"
                >
                    {{
                        $gettext(
                            'No stations yet. Create a station to get an AutoDJ-ready playlist and ports assigned automatically.',
                        )
                    }}
                </p>
            </loading>
        </div>
    </section>
</template>

<script setup lang="ts">
import { useQuery } from "@tanstack/vue-query";
import { computed } from "vue";
import Loading from "~/components/Common/Loading.vue";
import { ApiNowPlaying, HasLinks } from "~/entities/ApiInterfaces.ts";
import { QueryKeys } from "~/entities/Queries.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import { useAxios } from "~/vendor/axios";

type DashboardStation = ApiNowPlaying & Required<HasLinks>;

const { getApiUrl } = useApiRouter();
const stationsUrl = getApiUrl("/frontend/dashboard/stations");
const { axiosSilent } = useAxios();

const { data: stations, isLoading } = useQuery<DashboardStation[]>({
    queryKey: [QueryKeys.AdminIndex, "fleet"],
    queryFn: async ({ signal }) => {
        const { data } = await axiosSilent.get(stationsUrl.value, {
            signal,
            params: {
                rowCount: 0,
                internal: true,
            },
        });
        // Dashboard endpoint may return a paginated envelope or a bare array.
        if (Array.isArray(data)) {
            return data;
        }
        if (data && Array.isArray(data.rows)) {
            return data.rows;
        }
        return [];
    },
    placeholderData: () => [],
    refetchInterval: 15 * 1000,
});

const totals = computed(() => {
    const rows = stations.value ?? [];
    let online = 0;
    let listeners = 0;

    for (const row of rows) {
        if (row.is_online) {
            online += 1;
        }
        listeners += row.listeners?.total ?? 0;
    }

    return {
        stations: rows.length,
        online,
        offline: Math.max(rows.length - online, 0),
        listeners,
    };
});
</script>
