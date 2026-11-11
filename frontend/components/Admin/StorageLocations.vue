<template>
    <div class="admin-console">
    <card-page
        header-id="hdr_storage_locations"
        :title="$gettext('Storage')"
    >
        <template #info>
            <p class="card-text mb-0">
                {{
                    $gettext(
                        'Where station media, recordings, podcasts, and backups live — local disk or remote adapters.',
                    )
                }}
            </p>
        </template>
        <div class="card-body pb-0">
            <nav
                class="nav nav-tabs"
                role="tablist"
            >
                <div
                    v-for="tab in tabs"
                    :key="tab.type"
                    class="nav-item"
                    role="presentation"
                >
                    <button
                        class="nav-link"
                        :class="(activeType === tab.type) ? 'active' : ''"
                        type="button"
                        role="tab"
                        @click="setType(tab.type)"
                    >
                        {{ tab.title }}
                    </button>
                </div>
            </nav>
        </div>

        <div class="card-body buttons">
            <add-button
                :text="$gettext('Add Storage Location')"
                @click="doCreate"
            />
        </div>

        <data-table
            id="admin_storage_locations"
            :show-toolbar="false"
            :fields="fields"
            :provider="listItemProvider"
        >
            <template #empty>
                <div class="admin-guided-empty">
                    <div class="admin-guided-empty-title">
                        {{ $gettext('No storage locations in this category') }}
                    </div>
                    <p class="admin-guided-empty-text mb-0">
                        {{
                            $gettext(
                                'Add a location to hold media for this category, or switch tabs to review other storage types.',
                            )
                        }}
                    </p>
                </div>
            </template>
            <template #cell(actions)="{item}">
                <div class="btn-group btn-group-sm">
                    <button
                        type="button"
                        class="btn btn-primary"
                        @click="doEdit(item.links.self)"
                    >
                        {{ $gettext('Edit') }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-danger"
                        @click="doDelete(item.links.self)"
                    >
                        {{ $gettext('Delete') }}
                    </button>
                </div>
            </template>
            <template #cell(adapter)="{item}">
                <h5 class="m-0">
                    {{ getAdapterName(item.adapter) }}
                </h5>
                <p class="card-text">
                    {{ item.uri }}
                </p>
            </template>
            <template #cell(stations)="{item}">
                {{ item.stations?.join(', ') }}
            </template>
            <template #cell(space)="{item}">
                <template v-if="item.storageAvailable">
                    <div
                        class="progress h-20 mb-3"
                        role="progressbar"
                        :aria-label="item.storageUsedPercent+'%'"
                        :aria-valuenow="item.storageUsedPercent ?? undefined"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="progress-bar"
                            :class="getProgressVariant(item.storageUsedPercent)"
                            :style="{ width: item.storageUsedPercent+'%' }"
                        >
                            <span class="broadcast-mono">{{ item.storageUsedPercent }}%</span>
                        </div>
                    </div>
                </template>

                <span class="broadcast-mono">{{ getSpaceUsed(item) }}</span>
            </template>
        </data-table>
    </card-page>

    <edit-modal
        ref="$editModal"
        :create-url="listUrl"
        :type="activeType"
        @relist="() => relist()"
    />
    </div>
</template>

<script setup lang="ts">
import { computed, ref, useTemplateRef } from "vue";
import EditModal from "~/components/Admin/StorageLocations/EditModal.vue";
import AddButton from "~/components/Common/AddButton.vue";
import CardPage from "~/components/Common/CardPage.vue";
import DataTable, { DataTableField } from "~/components/Common/DataTable.vue";
import {
    ApiAdminStorageLocation,
    StorageLocation,
    StorageLocationAdapters,
    StorageLocationTypes,
} from "~/entities/ApiInterfaces.ts";
import { QueryKeys } from "~/entities/Queries.ts";
import { useApiItemProvider } from "~/functions/dataTable/useApiItemProvider.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import useConfirmAndDelete from "~/functions/useConfirmAndDelete";
import useHasEditModal from "~/functions/useHasEditModal";
import { useTranslate } from "~/vendor/gettext";

const activeType = ref<StorageLocationTypes>(StorageLocationTypes.StationMedia);

const { getApiUrl } = useApiRouter();
const listUrl = getApiUrl("/admin/storage_locations");
const listUrlForType = computed(() => {
    return `${listUrl.value}?type=${activeType.value}`;
});

const { $gettext } = useTranslate();

type Row = Required<StorageLocation & ApiAdminStorageLocation>;

const fields: DataTableField<Row>[] = [
    { key: "adapter", label: $gettext("Location"), sortable: false },
    {
        key: "space",
        label: $gettext("Capacity"),
        class: "text-nowrap",
        sortable: false,
    },
    { key: "stations", label: $gettext("Stations"), sortable: false },
    {
        key: "actions",
        label: $gettext("Actions"),
        class: "shrink",
        sortable: false,
    },
];

const listItemProvider = useApiItemProvider<Row>(listUrlForType, [
    QueryKeys.AdminStorageLocations,
    activeType,
]);

const relist = () => {
    void listItemProvider.refresh();
};

const tabs = [
    {
        type: StorageLocationTypes.StationMedia,
        title: $gettext("Music Library"),
    },
    {
        type: StorageLocationTypes.StationRecordings,
        title: $gettext("Live Recordings"),
    },
    {
        type: StorageLocationTypes.StationPodcasts,
        title: $gettext("Podcasts"),
    },
    {
        type: StorageLocationTypes.Backup,
        title: $gettext("Backups"),
    },
];

const $editModal = useTemplateRef("$editModal");
const { doCreate, doEdit } = useHasEditModal($editModal);

const setType = (type: StorageLocationTypes) => {
    activeType.value = type;
};

const getAdapterName = (adapter: StorageLocationAdapters) => {
    switch (adapter) {
        case StorageLocationAdapters.Local:
            return $gettext("Local");

        case StorageLocationAdapters.S3:
            return $gettext("Remote: S3 Compatible");

        case StorageLocationAdapters.Dropbox:
            return $gettext("Remote: Dropbox");

        case StorageLocationAdapters.Sftp:
            return $gettext("Remote: SFTP");
    }
};

const getSpaceUsed = (item: Row) => {
    return item.storageAvailable
        ? `${item.storageUsed} / ${item.storageAvailable}`
        : item.storageUsed;
};

const getProgressVariant = (percent: number | null) => {
    if (percent === null) {
        return "";
    } else if (percent > 85) {
        return "text-bg-danger";
    } else if (percent > 65) {
        return "text-bg-warning";
    } else {
        return "text-bg-primary";
    }
};

const { doDelete } = useConfirmAndDelete(
    $gettext("Delete Storage Location?"),
    () => relist(),
);
</script>
