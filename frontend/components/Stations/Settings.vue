<template>
    <section
        class="card"
        role="region"
        aria-labelledby="hdr_edit_profile"
    >
        <div class="card-header text-bg-primary">
            <h3
                id="hdr_edit_profile"
                class="card-title"
            >
                {{ $gettext('Configure') }}
            </h3>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">
                {{
                    $gettext(
                        'Publish name, passwords, limits, and AutoDJ options for this service.',
                    )
                }}
            </p>
            <p
                v-if="showExternalDestinationsLink"
                class="text-muted mb-3"
            >
                {{
                    $gettext(
                        'Need to push this station elsewhere?',
                    )
                }}
                <router-link :to="{ name: 'stations:remotes:index' }">
                    {{ $gettext('External Destinations') }}
                </router-link>
            </p>
            <loading :loading="propsLoading" lazy>
                <admin-stations-form
                    v-if="props"
                    v-bind="props"
                    is-edit-mode
                    :edit-url="editUrl"
                    @submitted="onSubmitted"
                />
            </loading>
        </div>
    </section>
</template>

<script setup lang="ts">
import { useQuery } from "@tanstack/vue-query";
import { computed } from "vue";
import { useRouter } from "vue-router";
import AdminStationsForm from "~/components/Admin/Stations/StationForm.vue";
import Loading from "~/components/Common/Loading.vue";
import {
    ApiAdminVueStationsFormProps,
    StationPermissions,
} from "~/entities/ApiInterfaces.ts";
import { QueryKeys, queryKeyWithStation } from "~/entities/Queries.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import { useStationData } from "~/functions/useStationQuery.ts";
import { useUserAllowedForStation } from "~/functions/useUserallowedForStation.ts";
import { useAxios } from "~/vendor/axios.ts";

const { getStationApiUrl } = useApiRouter();
const editUrl = getStationApiUrl("/profile/edit");
const propsUrl = getStationApiUrl("/vue/profile/edit");

const { axios } = useAxios();

const { data: props, isLoading: propsLoading } =
    useQuery<ApiAdminVueStationsFormProps>({
        queryKey: queryKeyWithStation([QueryKeys.StationProfile, "edit"]),
        queryFn: async ({ signal }) => {
            const { data } = await axios.get<ApiAdminVueStationsFormProps>(
                propsUrl.value,
                { signal },
            );
            return data;
        },
    });

const station = useStationData();
const { userAllowedForStation } = useUserAllowedForStation();

const showExternalDestinationsLink = computed(
    () =>
        userAllowedForStation(StationPermissions.RemoteRelays) &&
        station.value.features.remoteRelays,
);

const router = useRouter();

const onSubmitted = async () => {
    await router.push({
        name: "stations:index",
    });

    window.location.reload();
};
</script>
