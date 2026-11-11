<template>
    <div class="outside-card-header d-flex align-items-center">
        <div class="flex-fill">
            <h2 class="display-6 m-0">
                {{ stationData.name }}
            </h2>
        </div>
        <div
            v-if="userAllowedForStation(StationPermissions.Profile)"
            class="flex-shrink-0 ms-3"
        >
            <router-link
                class="btn btn-primary"
                role="button"
                :to="{name: 'stations:profile:edit'}"
            >
                <icon-ic-edit/>

                <span>
                    {{ $gettext('Edit Profile') }}
                </span>
            </router-link>
        </div>
    </div>

    <card-page
        id="station-disabled"
        :title="$gettext('Station Suspended')"
    >
        <div class="card-body">
            <p class="card-text mb-0">
                <template v-if="canManageFleet">
                    {{
                        $gettext(
                            'This service is suspended. You can still manage media and configuration. Use Fleet Admin → Unsuspend in the sidebar to restore broadcasting.',
                        )
                    }}
                </template>
                <template v-else>
                    {{
                        $gettext(
                            'This service is suspended by a fleet administrator. You can still manage media, playlists, and other station settings. Ask a fleet admin to unsuspend the service to restore broadcasting.',
                        )
                    }}
                </template>
            </p>
        </div>
    </card-page>
</template>
<script setup lang="ts">
import { computed } from "vue";
import CardPage from "~/components/Common/CardPage.vue";
import {
    GlobalPermissions,
    StationPermissions,
} from "~/entities/ApiInterfaces.ts";
import { useStationData } from "~/functions/useStationQuery.ts";
import { useUserAllowed } from "~/functions/useUserAllowed.ts";
import { useUserAllowedForStation } from "~/functions/useUserallowedForStation.ts";
import IconIcEdit from "~icons/ic/baseline-edit";

const stationData = useStationData();

const { userAllowedForStation } = useUserAllowedForStation();
const { userAllowed } = useUserAllowed();
const canManageFleet = computed(() => userAllowed(GlobalPermissions.Stations));
</script>
