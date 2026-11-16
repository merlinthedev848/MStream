<template>
    <dashboard-no-sidebar>
        <div
            id="admin-index"
            class="admin-console"
        >
            <h2 class="outside-card-header mb-1">
                {{ $gettext('Admin Console') }}
            </h2>
            <p class="admin-section-lead">
                {{
                    $gettext(
                        'Fleet health first — then people, packages, and host tools.',
                    )
                }}
            </p>

            <div
                v-if="canManageFleet"
                class="row row-of-cards mb-3"
            >
                <div class="col-6 col-md-3">
                    <div class="card admin-kpi-card">
                        <div class="card-body">
                            <div class="admin-fleet-label">
                                {{ $gettext('Online') }}
                            </div>
                            <div class="admin-fleet-value broadcast-mono text-success">
                                {{ fleet.online }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card admin-kpi-card">
                        <div class="card-body">
                            <div class="admin-fleet-label">
                                {{ $gettext('Offline') }}
                            </div>
                            <div class="admin-fleet-value broadcast-mono">
                                {{ fleet.offline }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card admin-kpi-card">
                        <div class="card-body">
                            <div class="admin-fleet-label">
                                {{ $gettext('Customers') }}
                            </div>
                            <div class="admin-fleet-value broadcast-mono">
                                {{ customerCount }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card admin-kpi-card">
                        <div class="card-body">
                            <div class="admin-fleet-label">
                                {{ $gettext('Listeners') }}
                            </div>
                            <div class="admin-fleet-value broadcast-mono">
                                {{ fleet.listeners }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template v-if="canManageFleet">
                <station-fleet-panel />

                <div
                    v-if="fleet.stations === 0"
                    class="card mb-3"
                >
                    <div class="card-body admin-guided-empty">
                        <div class="admin-guided-empty-title">
                            {{ $gettext('Getting Started') }}
                        </div>
                        <p class="admin-guided-empty-text">
                            {{
                                $gettext(
                                    'Create a customer, then create a media service. Ports and AutoDJ are assigned automatically.',
                                )
                            }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <router-link
                                class="btn btn-outline-primary"
                                :to="{ name: 'admin:customers:index' }"
                            >
                                {{ $gettext('Create Customer') }}
                            </router-link>
                            <router-link
                                class="btn btn-primary"
                                :to="{ name: 'admin:stations:index' }"
                            >
                                {{ $gettext('Create Service') }}
                            </router-link>
                        </div>
                    </div>
                </div>
            </template>

            <template v-if="canViewHostMetrics">
                <h2 class="outside-card-header mb-1">
                    {{ $gettext('Host Metrics') }}
                </h2>

                <div class="row row-of-cards">
                    <div class="col-sm-12 col-lg-4">
                        <loading
                            :loading="isLoading"
                            lazy
                        >
                            <memory-stats-panel
                                v-if="stats && stats.memory"
                                :memory-stats="stats.memory"
                            />
                        </loading>
                    </div>

                    <div class="col-sm-12 col-lg-4">
                        <loading
                            :loading="isLoading"
                            lazy
                        >
                            <disk-usage-panel
                                v-if="stats && stats.disk"
                                :disk-stats="stats.disk"
                            />
                        </loading>
                    </div>

                    <div class="col-sm-12 col-lg-4">
                        <loading
                            :loading="isLoading"
                            lazy
                        >
                            <cpu-stats-panel
                                v-if="stats && stats.cpu"
                                :cpu-stats="stats.cpu"
                            />
                        </loading>
                    </div>
                </div>
            </template>

            <details class="admin-menu-details mt-3 mb-3">
                <summary class="outside-card-header mb-2">
                    {{ $gettext('All Admin Tools') }}
                </summary>
                <div class="row row-of-cards">
                    <div class="col-sm-12 col-lg-4">
                        <menu-block :items="menuItems.slice(0, 1)"/>
                    </div>
                    <div class="col-sm-12 col-lg-4">
                        <menu-block :items="menuItems.slice(1, 2)"/>
                    </div>
                    <div class="col-sm-12 col-lg-4">
                        <menu-block :items="menuItems.slice(2)"/>
                    </div>
                </div>
            </details>
        </div>
    </dashboard-no-sidebar>
</template>

<script setup lang="ts">
import { useQuery } from "@tanstack/vue-query";
import { computed } from "vue";
import CpuStatsPanel from "~/components/Admin/Index/CpuStatsPanel.vue";
import DiskUsagePanel from "~/components/Admin/Index/DiskUsagePanel.vue";
import MemoryStatsPanel from "~/components/Admin/Index/MemoryStatsPanel.vue";
import MenuBlock from "~/components/Admin/Index/MenuBlock.vue";
import StationFleetPanel from "~/components/Admin/Index/StationFleetPanel.vue";
import { useAdminMenu } from "~/components/Admin/menu";
import Loading from "~/components/Common/Loading.vue";
import DashboardNoSidebar from "~/components/Layout/DashboardNoSidebar.vue";
import {
    ApiAdminServerStats,
    ApiNowPlaying,
    Customer,
    GlobalPermissions,
    HasLinks,
} from "~/entities/ApiInterfaces.ts";
import { QueryKeys } from "~/entities/Queries.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import { useUserAllowed } from "~/functions/useUserAllowed.ts";
import { useAxios } from "~/vendor/axios";

const { getApiUrl } = useApiRouter();
const statsUrl = getApiUrl("/admin/server/stats");
const stationsUrl = getApiUrl("/frontend/dashboard/stations");
const customersUrl = getApiUrl("/admin/customers");

const menuItems = useAdminMenu();

const { userAllowed } = useUserAllowed();
const canManageFleet =
    userAllowed(GlobalPermissions.Stations) ||
    userAllowed(GlobalPermissions.ResellerStations);
const canViewHostMetrics = userAllowed(GlobalPermissions.Stations);

const { axiosSilent } = useAxios();

type LiveRow = ApiNowPlaying & Required<HasLinks>;

const { data: liveStations } = useQuery<LiveRow[]>({
    queryKey: [QueryKeys.AdminIndex, "fleet-kpis"],
    queryFn: async ({ signal }) => {
        const { data } = await axiosSilent.get(stationsUrl.value, {
            signal,
            params: { rowCount: 0, internal: true },
        });
        if (Array.isArray(data)) {
            return data;
        }
        if (data && Array.isArray(data.rows)) {
            return data.rows;
        }
        return [];
    },
    enabled: canManageFleet,
    placeholderData: () => [],
    refetchInterval: 15 * 1000,
});

const fleet = computed(() => {
    const rows = liveStations.value ?? [];
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

const { data: customerRows } = useQuery<Customer[]>({
    queryKey: [QueryKeys.AdminIndex, "customer-count"],
    queryFn: async ({ signal }) => {
        const { data } = await axiosSilent.get(customersUrl.value, {
            signal,
            params: { rowCount: 0 },
        });
        return data?.rows ?? [];
    },
    enabled: canManageFleet,
    placeholderData: () => [],
});

const customerCount = computed(() => customerRows.value?.length ?? 0);

const { data: stats, isLoading } = useQuery<ApiAdminServerStats>({
    queryKey: [QueryKeys.AdminIndex, "stats"],
    queryFn: async ({ signal }) => {
        const { data } = await axiosSilent.get(statsUrl.value, { signal });
        return data;
    },
    enabled: canViewHostMetrics,
    placeholderData: () => ({
        cpu: {
            total: {
                name: "Total",
                usage: "",
                idle: "",
                io_wait: "",
                steal: "",
            },
            cores: [],
            load: [0, 0, 0],
        },
        memory: {
            total_bytes: "0",
            total_readable: "",
            free_bytes: "0",
            free_readable: "",
            buffers_bytes: "0",
            buffers_readable: "",
            cached_bytes: "0",
            cached_readable: "",
            sReclaimable_bytes: "0",
            sReclaimable_readable: "",
            shmem_bytes: "0",
            shmem_readable: "",
            used_bytes: "0",
            used_readable: "",
        },
        swap: {
            total_bytes: "0",
            total_readable: "",
            free_bytes: "0",
            free_readable: "",
            used_bytes: "0",
            used_readable: "",
        },
        disk: {
            total_bytes: "0",
            total_readable: "",
            free_bytes: "0",
            free_readable: "",
            used_bytes: "0",
            used_readable: "",
        },
        network: [],
    }),
    refetchInterval: 5 * 1000,
});
</script>
