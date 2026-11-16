<template>
        <div
            id="admin-index"
            class="admin-console"
        >
            <h2 class="outside-card-header mb-1">
                {{ $gettext('Dashboard') }}
            </h2>
            <p class="admin-section-lead">
                {{
                    $gettext(
                        'Fleet health first — then customers, services, and host tools.',
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
                <div class="row row-of-cards mb-3">
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-header text-bg-primary">
                                <h3 class="card-title mb-0">
                                    {{ $gettext('Getting Started') }}
                                </h3>
                            </div>
                            <div class="card-body admin-create-guide">
                                <div class="admin-create-guide-step">
                                    <span class="admin-create-guide-index broadcast-mono">1</span>
                                    <div>
                                        <div class="admin-create-guide-title">
                                            {{ $gettext('Configure Branding') }}
                                        </div>
                                        <p class="admin-create-guide-text">
                                            {{
                                                $gettext(
                                                    'Set panel name and login artwork under Administration.',
                                                )
                                            }}
                                        </p>
                                        <router-link
                                            class="btn btn-sm btn-outline-primary mt-1"
                                            :to="{ name: 'admin:branding:index' }"
                                        >
                                            {{ $gettext('Branding') }}
                                        </router-link>
                                    </div>
                                </div>
                                <div class="admin-create-guide-step">
                                    <span class="admin-create-guide-index broadcast-mono">2</span>
                                    <div>
                                        <div class="admin-create-guide-title">
                                            {{ $gettext('Create Customer') }}
                                        </div>
                                        <p class="admin-create-guide-text">
                                            {{
                                                $gettext(
                                                    'Customers own media services. Assign an owner for Login.',
                                                )
                                            }}
                                        </p>
                                        <router-link
                                            class="btn btn-sm btn-outline-primary mt-1"
                                            :to="{ name: 'admin:customers:index' }"
                                        >
                                            {{ $gettext('Customers') }}
                                        </router-link>
                                    </div>
                                </div>
                                <div class="admin-create-guide-step">
                                    <span class="admin-create-guide-index broadcast-mono">3</span>
                                    <div>
                                        <div class="admin-create-guide-title">
                                            {{ $gettext('Create Service') }}
                                        </div>
                                        <p class="admin-create-guide-text">
                                            {{
                                                $gettext(
                                                    'Wizard assigns ports and an AutoDJ-ready playlist.',
                                                )
                                            }}
                                        </p>
                                        <router-link
                                            class="btn btn-sm btn-primary mt-1"
                                            :to="{ name: 'admin:stations:index' }"
                                        >
                                            {{ $gettext('Media Services') }}
                                        </router-link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <station-fleet-panel />
                    </div>
                </div>
            </template>

            <details
                v-if="canViewHostMetrics"
                class="admin-menu-details mb-3"
            >
                <summary class="outside-card-header mb-2">
                    {{ $gettext('Host Metrics') }}
                </summary>
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
            </details>
        </div>
</template>

<script setup lang="ts">
import { useQuery } from "@tanstack/vue-query";
import { computed } from "vue";
import CpuStatsPanel from "~/components/Admin/Index/CpuStatsPanel.vue";
import DiskUsagePanel from "~/components/Admin/Index/DiskUsagePanel.vue";
import MemoryStatsPanel from "~/components/Admin/Index/MemoryStatsPanel.vue";
import StationFleetPanel from "~/components/Admin/Index/StationFleetPanel.vue";
import Loading from "~/components/Common/Loading.vue";
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
