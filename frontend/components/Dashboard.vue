<template>
    <dashboard-no-sidebar>
        <div
            id="dashboard"
            class="row-of-cards admin-console"
        >
            <div class="mb-3">
                <h2 class="outside-card-header mb-1">
                    {{ $gettext('My Services') }}
                </h2>
                <p class="admin-section-lead mb-0">
                    {{
                        $gettext(
                            'Open a streaming service console to manage Overview, Configure, Media, and more.',
                        )
                    }}
                </p>
            </div>

            <template v-if="!notificationsLoading && notifications && notifications.length > 0">
                <div
                    v-for="notification in notifications"
                    :key="notification.id"
                    :id="notification.id"
                    class="card-body d-flex align-items-center alert flex-md-row flex-column mb-3"
                    :class="'alert-'+notification.type"
                    role="alert"
                    aria-live="polite"
                >
                    <div class="flex-fill">
                        <h4 class="mb-1">{{ notification.title }}</h4>
                        <p class="card-text mb-0">
                            {{ notification.body }}
                        </p>
                    </div>
                    <div
                        v-if="notification.actionLabel && notification.actionUrl"
                        class="flex-shrink-0 ms-md-3 mt-3 mt-md-0"
                    >
                        <a
                            class="btn btn-sm"
                            :class="'btn-'+notification.type"
                            :href="notification.actionUrl"
                            target="_blank"
                        >
                            {{ notification.actionLabel }}
                        </a>
                    </div>
                </div>
            </template>

            <card-page header-id="hdr_stations">
                <template #header="{id}">
                    <div class="d-flex flex-wrap align-items-center">
                        <div class="flex-fill">
                            <h2
                                :id="id"
                                class="card-title"
                            >
                                {{ $gettext('Services') }}
                            </h2>
                        </div>
                    </div>
                </template>

                <data-table
                    id="dashboard_stations"
                    :fields="stationFields"
                    :provider="stationsItemProvider"
                    paginated
                    responsive
                    :show-toolbar="false"
                    :hide-on-loading="false"
                >
                    <template #empty>
                        <div class="admin-guided-empty">
                            <div class="admin-guided-empty-title">
                                {{ $gettext('No services assigned') }}
                            </div>
                            <p class="admin-guided-empty-text mb-0">
                                {{
                                    $gettext(
                                        'Ask your administrator to assign a media service to your account.',
                                    )
                                }}
                            </p>
                        </div>
                    </template>
                    <template #cell(name)="{ item }">
                        <div class="h5 m-0">
                            {{ item.station.name }}
                        </div>
                        <div
                            v-if="item.station.is_public"
                            class="small"
                        >
                            <a
                                :href="item.links.public"
                                target="_blank"
                            >
                                {{ $gettext('Public Page') }}
                            </a>
                        </div>
                    </template>
                    <template #cell(listeners)="{ item }">
                        <span class="broadcast-mono">{{ item.listeners.total }}</span>
                    </template>
                    <template #cell(now_playing)="{ item }">
                        <div v-if="!item.is_online">
                            <span class="badge broadcast-tally-standby">
                                {{ $gettext('OFFLINE') }}
                            </span>
                        </div>
                        <div v-else>
                            <div class="mb-1">
                                <span class="badge broadcast-tally-onair">
                                    {{ $gettext('ON AIR') }}
                                </span>
                            </div>
                            <span class="nowplaying-title">
                                {{
                                    item.now_playing?.song?.title
                                        || item.now_playing?.song?.text
                                        || '—'
                                }}
                            </span>
                        </div>
                    </template>
                    <template #cell(actions)="{ item }">
                        <router-link
                            class="btn btn-primary"
                            :to="{
                                name: 'stations:index',
                                params: {
                                    station_id: item.station.id
                                }
                            }"
                            role="button"
                        >
                            {{ $gettext('Open Console') }}
                        </router-link>
                    </template>
                </data-table>
            </card-page>
        </div>
    </dashboard-no-sidebar>
</template>

<script setup lang="ts">
import { useQuery } from "@tanstack/vue-query";
import { onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import CardPage from "~/components/Common/CardPage.vue";
import DataTable, { DataTableField } from "~/components/Common/DataTable.vue";
import DashboardNoSidebar from "~/components/Layout/DashboardNoSidebar.vue";
import {
    ApiNotification,
    ApiNowPlaying,
    GlobalPermissions,
    HasLinks,
} from "~/entities/ApiInterfaces.ts";
import { QueryKeys } from "~/entities/Queries.ts";
import { useApiItemProvider } from "~/functions/dataTable/useApiItemProvider.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import { useUserAllowed } from "~/functions/useUserAllowed.ts";
import { useAxios } from "~/vendor/axios";
import { useTranslate } from "~/vendor/gettext";

const { userAllowed } = useUserAllowed();
const router = useRouter();

// MediaCP-like: fleet admins land on KPI home, not the chart dashboard.
onMounted(() => {
    if (userAllowed(GlobalPermissions.Stations)) {
        void router.replace({ name: "admin:index" });
    }
});

const { getApiUrl } = useApiRouter();

const notificationsUrl = getApiUrl("/frontend/dashboard/notifications");
const stationsUrl = getApiUrl("/frontend/dashboard/stations");

const { $gettext } = useTranslate();
const { axios } = useAxios();

const { data: notifications, isLoading: notificationsLoading } = useQuery<
    ApiNotification[]
>({
    queryKey: [QueryKeys.Dashboard, "notifications"],
    queryFn: async ({ signal }) => {
        const { data } = await axios.get<ApiNotification[]>(
            notificationsUrl.value,
            { signal },
        );
        return data;
    },
});

type ApiDashboard = ApiNowPlaying & Required<HasLinks>;

const stationFields: DataTableField<ApiDashboard>[] = [
    {
        key: "name",
        label: $gettext("Service"),
        sortable: true,
    },
    {
        key: "listeners",
        label: $gettext("Listeners"),
        sortable: true,
        class: "shrink",
    },
    {
        key: "now_playing",
        label: $gettext("Status"),
        sortable: true,
    },
    {
        key: "actions",
        label: "",
        sortable: false,
        class: "shrink",
    },
];

const stationsItemProvider = useApiItemProvider<ApiDashboard>(
    stationsUrl,
    [QueryKeys.Dashboard, "stations"],
    {
        refetchInterval: 15 * 1000,
    },
);

// Single-service customers go straight to the console (MediaCP mental model).
watch(
    () =>
        [
            stationsItemProvider.total.value,
            stationsItemProvider.rows.value,
        ] as const,
    ([total, rows]) => {
        if (userAllowed(GlobalPermissions.Stations)) {
            return;
        }
        if (total === 1 && rows?.[0]?.station?.id) {
            void router.replace({
                name: "stations:index",
                params: { station_id: rows[0].station.id },
            });
        }
    },
    { immediate: true },
);
</script>
