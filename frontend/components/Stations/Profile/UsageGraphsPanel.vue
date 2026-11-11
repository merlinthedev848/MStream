<template>
    <section
        id="profile-usage-graphs"
        class="card mb-3"
        role="region"
        :aria-label="$gettext('Usage Graphs')"
    >
        <div class="card-header text-bg-primary d-flex align-items-center gap-2">
            <h3 class="card-title mb-0 flex-fill">
                {{ $gettext('Usage Graphs') }}
            </h3>
            <router-link
                v-if="canViewReports"
                class="btn btn-dark btn-sm"
                :to="{ name: 'stations:reports:overview' }"
            >
                {{ $gettext('Full Reporting') }}
            </router-link>
        </div>
        <div class="card-body">
            <p class="service-quota-meta mb-3">
                {{ $gettext('Listeners — last 48 hours') }}
            </p>
            <loading
                :loading="isLoading"
                lazy
            >
                <time-series-chart
                    v-if="hasData"
                    style="width: 100%;"
                    :aspect-ratio="3.2"
                    :data="chartMetrics"
                    :alt="chartAlt"
                    :tz="timezone"
                />
                <p
                    v-else
                    class="text-muted mb-0"
                >
                    {{ $gettext('No listener history yet for this window.') }}
                </p>
            </loading>
        </div>
    </section>
</template>

<script setup lang="ts">
import { useQuery } from "@tanstack/vue-query";
import { toRefs } from "@vueuse/core";
import { computed } from "vue";
import TimeSeriesChart from "~/components/Common/Charts/TimeSeriesChart.vue";
import Loading from "~/components/Common/Loading.vue";
import { StationPermissions } from "~/entities/ApiInterfaces.ts";
import { QueryKeys, queryKeyWithStation } from "~/entities/Queries.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import { useStationData } from "~/functions/useStationQuery.ts";
import { useUserAllowedForStation } from "~/functions/useUserallowedForStation.ts";
import { useAxios } from "~/vendor/axios";
import { useTranslate } from "~/vendor/gettext";
import { useLuxon } from "~/vendor/luxon";

const { $gettext } = useTranslate();
const stationData = useStationData();
const { timezone } = toRefs(stationData);
const { userAllowedForStation } = useUserAllowedForStation();
const canViewReports = computed(() =>
    userAllowedForStation(StationPermissions.Reports),
);

const { getStationApiUrl } = useApiRouter();
const chartsUrl = getStationApiUrl("/reports/overview/charts");
const { axiosSilent } = useAxios();
const { DateTime } = useLuxon();

const { data: chartData, isLoading } = useQuery({
    queryKey: queryKeyWithStation([
        QueryKeys.StationReports,
        "overview-usage-48h",
    ]),
    queryFn: async ({ signal }) => {
        const end = DateTime.local({ zone: timezone.value });
        const start = end.minus({ hours: 48 });
        const { data } = await axiosSilent.get(chartsUrl.value, {
            signal,
            params: {
                type: "average",
                start: start.toISO(),
                end: end.toISO(),
            },
        });
        return data;
    },
    enabled: canViewReports,
    staleTime: 60 * 1000,
    refetchInterval: 5 * 60 * 1000,
    placeholderData: () => ({
        daily: { metrics: [], alt: [] },
    }),
});

const chartMetrics = computed(() => chartData.value?.daily?.metrics ?? []);
const chartAlt = computed(() => chartData.value?.daily?.alt ?? []);
const hasData = computed(
    () =>
        Array.isArray(chartMetrics.value) &&
        chartMetrics.value.some(
            (series: { data?: unknown[] }) =>
                Array.isArray(series?.data) && series.data.length > 0,
        ),
);
</script>
