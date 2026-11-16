<template>
    <section
        class="card h-100 mb-0"
        role="region"
        aria-labelledby="hdr_station_fleet"
    >
        <div class="card-header text-bg-primary d-flex align-items-center flex-wrap gap-2">
            <h2
                id="hdr_station_fleet"
                class="card-title mb-0 flex-fill"
            >
                {{ $gettext('Top Media Services') }}
            </h2>
            <router-link
                class="btn btn-dark btn-sm py-2"
                :to="{ name: 'admin:stations:index' }"
            >
                {{ $gettext('All Services') }}
            </router-link>
        </div>

        <div class="card-body">
            <loading
                :loading="isLoading"
                lazy
            >
                <p
                    v-if="topServices.length === 0"
                    class="admin-section-lead mb-0"
                >
                    {{
                        $gettext(
                            'No services yet. Create a customer, then create a service assigned to them.',
                        )
                    }}
                </p>
                <ul
                    v-else
                    class="list-group list-group-flush"
                >
                    <li
                        v-for="row in topServices"
                        :key="row.station.id"
                        class="list-group-item d-flex align-items-center gap-2 px-0"
                    >
                        <div class="flex-fill min-w-0">
                            <div class="fw-semibold text-truncate">
                                {{ row.station.name }}
                            </div>
                            <div class="broadcast-mono small text-muted">
                                {{ row.listeners?.total ?? 0 }}
                                {{ $gettext('listeners') }}
                            </div>
                        </div>
                        <span
                            class="badge"
                            :class="
                                row.is_online
                                    ? 'broadcast-tally-onair'
                                    : 'broadcast-tally-standby'
                            "
                        >
                            {{
                                row.is_online
                                    ? $gettext('ONLINE')
                                    : $gettext('OFFLINE')
                            }}
                        </span>
                        <router-link
                            class="btn btn-sm btn-primary"
                            :to="{
                                name: 'stations:index',
                                params: { station_id: row.station.id },
                            }"
                        >
                            {{ $gettext('Open') }}
                        </router-link>
                    </li>
                </ul>
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

const topServices = computed(() => {
    const rows = [...(stations.value ?? [])];
    rows.sort((a, b) => (b.listeners?.total ?? 0) - (a.listeners?.total ?? 0));
    return rows.slice(0, 8);
});
</script>
