<template>
    <card-page
        id="profile-listener-url"
        header-id="hdr_listener_url"
        :title="$gettext('Listener URL')"
    >
        <div class="card-body">
            <template v-if="primaryUrl">
                <p class="card-text text-muted mb-2">
                    {{
                        $gettext(
                            'Share this link with listeners. Copy it once — this is your primary stream URL.',
                        )
                    }}
                </p>
                <div class="d-flex flex-wrap align-items-center gap-2 listener-url-row">
                    <code class="broadcast-mono listener-url-value flex-fill">
                        {{ primaryUrl }}
                    </code>
                    <copy-to-clipboard-button :text="primaryUrl" />
                    <a
                        class="btn btn-sm btn-outline-primary"
                        :href="primaryUrl"
                        target="_blank"
                        rel="noopener"
                    >
                        {{ $gettext('Open') }}
                    </a>
                </div>
                <div
                    v-if="primaryLabel"
                    class="service-quota-meta mt-2"
                >
                    {{ primaryLabel }}
                </div>
            </template>
            <template v-else>
                <p class="card-text text-muted mb-2">
                    {{
                        $gettext(
                            'No listener stream yet. Add a mount under Mount Points → Listener Streams.',
                        )
                    }}
                </p>
                <router-link
                    class="btn btn-sm btn-outline-primary"
                    :to="{ name: 'stations:mounts:index' }"
                >
                    {{ $gettext('Manage Mounts') }}
                </router-link>
            </template>
        </div>
    </card-page>
</template>

<script setup lang="ts">
import { computed } from "vue";
import CardPage from "~/components/Common/CardPage.vue";
import CopyToClipboardButton from "~/components/Common/CopyToClipboardButton.vue";
import { useStationProfileData } from "~/components/Stations/Profile/useProfileQuery.ts";
import showFormatAndBitrate from "~/functions/showFormatAndBitrate";

const profileData = useStationProfileData();

const primaryMount = computed(() => {
    const mounts = profileData.value.station?.mounts ?? [];
    if (mounts.length === 0) {
        return null;
    }
    return mounts[0];
});

const primaryUrl = computed(() => {
    if (primaryMount.value?.url) {
        return primaryMount.value.url;
    }
    if (
        profileData.value.station?.hls_enabled &&
        profileData.value.station.hls_url
    ) {
        return profileData.value.station.hls_url;
    }
    return null;
});

const primaryLabel = computed(() => {
    const mount = primaryMount.value;
    if (!mount) {
        return null;
    }
    const detail = showFormatAndBitrate(mount.format, mount.bitrate);
    return detail ? `${mount.name} · ${detail}` : mount.name;
});
</script>
