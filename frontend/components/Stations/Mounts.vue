<template>
    <div class="row-of-cards">
        <card-page :title="$gettext('Mount Points')">
            <template #info>
                <p class="card-text mb-0">
                    {{
                        $gettext(
                            'Mount points are how listeners connect to your station. Each can use a different format or quality.',
                        )
                    }}
                </p>
            </template>
            <template #actions>
                <add-button
                    :text="$gettext('Add Mount Point')"
                    @click="doCreate"
                />
            </template>

            <data-table
                id="station_mounts"
                :fields="fields"
                :provider="listItemProvider"
                paginated
            >
                <template #cell(display_name)="row">
                    <h5 class="m-0">
                        <a :href="row.item.links.listen">{{ row.item.display_name }}</a>
                    </h5>
                    <div v-if="row.item.is_default">
                        <span class="badge text-bg-success">
                            {{ $gettext('Default Mount') }}
                        </span>
                    </div>
                </template>
                <template #cell(enable_autodj)="row">
                    <template v-if="row.item.enable_autodj">
                        {{ $gettext('Enabled') }} -
                        {{ showFormatAndBitrate(row.item.autodj_format, row.item.autodj_bitrate) }}
                    </template>
                    <template v-else>
                        {{ $gettext('Disabled') }}
                    </template>
                </template>
                <template #cell(actions)="row">
                    <div class="btn-group btn-group-sm">
                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="doEdit(row.item.links.self)"
                        >
                            {{ $gettext('Manage') }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-danger"
                            @click="doDelete(row.item.links.self)"
                        >
                            {{ $gettext('Delete') }}
                        </button>
                    </div>
                </template>
            </data-table>
        </card-page>

        <card-page
            v-if="advancedLinks.length > 0"
            header-id="hdr_mount_advanced"
            :title="$gettext('Advanced')"
        >
            <div class="list-group list-group-flush">
                <router-link
                    v-for="link in advancedLinks"
                    :key="link.key"
                    class="list-group-item list-group-item-action"
                    :to="link.to"
                >
                    {{ link.label }}
                </router-link>
            </div>
        </card-page>
    </div>

    <edit-modal
        ref="$editModal"
        :create-url="listUrl"
        :new-intro-url="newIntroUrl"
        @relist="() => relist()"
        @needs-restart="() => mayNeedRestart()"
    />
</template>

<script setup lang="ts">
import { computed, useTemplateRef } from "vue";
import AddButton from "~/components/Common/AddButton.vue";
import CardPage from "~/components/Common/CardPage.vue";
import DataTable, { DataTableField } from "~/components/Common/DataTable.vue";
import EditModal from "~/components/Stations/Mounts/EditModal.vue";
import { StationPermissions } from "~/entities/ApiInterfaces.ts";
import { QueryKeys, queryKeyWithStation } from "~/entities/Queries.ts";
import { useApiItemProvider } from "~/functions/dataTable/useApiItemProvider.ts";
import showFormatAndBitrate from "~/functions/showFormatAndBitrate";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import useConfirmAndDelete from "~/functions/useConfirmAndDelete";
import useHasEditModal from "~/functions/useHasEditModal";
import { useMayNeedRestart } from "~/functions/useMayNeedRestart";
import { useStationData } from "~/functions/useStationQuery.ts";
import { useUserAllowedForStation } from "~/functions/useUserallowedForStation.ts";
import { useTranslate } from "~/vendor/gettext";

const { getStationApiUrl } = useApiRouter();
const listUrl = getStationApiUrl("/mounts");
const newIntroUrl = getStationApiUrl("/mounts/intro");

const { $gettext } = useTranslate();
const stationData = useStationData();
const { userAllowedForStation } = useUserAllowedForStation();

const fields: DataTableField[] = [
    {
        key: "display_name",
        isRowHeader: true,
        label: $gettext("Name"),
        sortable: true,
    },
    { key: "enable_autodj", label: $gettext("AutoDJ"), sortable: true },
    {
        key: "actions",
        label: $gettext("Actions"),
        sortable: false,
        class: "shrink",
    },
];

const listItemProvider = useApiItemProvider(
    listUrl,
    queryKeyWithStation([QueryKeys.StationMounts]),
);

const relist = () => {
    void listItemProvider.refresh();
};

const $editModal = useTemplateRef("$editModal");
const { doCreate, doEdit } = useHasEditModal($editModal);

const { mayNeedRestart } = useMayNeedRestart();

const { doDelete } = useConfirmAndDelete(
    $gettext("Delete Mount Point?"),
    () => {
        mayNeedRestart();
        relist();
    },
);

type AdvancedLink = {
    key: string;
    label: string;
    to: { name: string };
};

const advancedLinks = computed((): AdvancedLink[] => {
    const links: AdvancedLink[] = [];

    if (
        userAllowedForStation(StationPermissions.MountPoints) &&
        stationData.value.features.hlsStreams
    ) {
        links.push({
            key: "hls",
            label: $gettext("HLS Streams"),
            to: { name: "stations:hls_streams:index" },
        });
    }

    if (
        userAllowedForStation(StationPermissions.RemoteRelays) &&
        stationData.value.features.remoteRelays
    ) {
        links.push({
            key: "remotes",
            label: $gettext("Remote Streams"),
            to: { name: "stations:remotes:index" },
        });
    }

    if (
        userAllowedForStation(StationPermissions.Broadcasting) &&
        stationData.value.features.media
    ) {
        links.push({
            key: "fallback",
            label: $gettext("Offline Fallback"),
            to: { name: "stations:fallback" },
        });
    }

    if (
        userAllowedForStation(StationPermissions.Broadcasting) &&
        stationData.value.features.autoDjQueue
    ) {
        links.push({
            key: "queue",
            label: $gettext("Upcoming Songs"),
            to: { name: "stations:queue:index" },
        });
    }

    if (userAllowedForStation(StationPermissions.Broadcasting)) {
        links.push({
            key: "restart",
            label: $gettext("Reload / Restart Configuration"),
            to: { name: "stations:restart:index" },
        });
    }

    return links;
});
</script>
