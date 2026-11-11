<template>
    <card-page
        id="profile-now-playing"
        header-id="hdr_now_playing"
        :title="$gettext('On the Air')"
    >
        <div class="card-body nowplaying-not-started">
            <div class="d-flex align-items-start gap-3">
                <span class="badge broadcast-tally-standby flex-shrink-0">
                    {{ $gettext('STANDBY') }}
                </span>
                <div>
                    <p class="card-text mb-2">
                        {{
                            $gettext(
                                'Broadcasting has not started yet. Upload music, then start AutoDJ to put the station on the air.',
                            )
                        }}
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <router-link
                            v-if="canManageMedia"
                            class="btn btn-primary btn-sm"
                            :to="{ name: 'stations:files:index' }"
                        >
                            {{ $gettext('Upload Music') }}
                        </router-link>
                        <router-link
                            v-if="canBroadcast"
                            class="btn btn-outline-secondary btn-sm"
                            :to="{ name: 'stations:restart:index' }"
                        >
                            {{ $gettext('Start Broadcasting') }}
                        </router-link>
                    </div>
                </div>
            </div>
        </div>
    </card-page>
</template>

<script setup lang="ts">
import { computed } from "vue";
import CardPage from "~/components/Common/CardPage.vue";
import { StationPermissions } from "~/entities/ApiInterfaces.ts";
import { useUserAllowedForStation } from "~/functions/useUserallowedForStation.ts";

const { userAllowedForStation } = useUserAllowedForStation();

const canManageMedia = computed(() =>
    userAllowedForStation(StationPermissions.Media),
);

const canBroadcast = computed(() =>
    userAllowedForStation(StationPermissions.Broadcasting),
);
</script>
