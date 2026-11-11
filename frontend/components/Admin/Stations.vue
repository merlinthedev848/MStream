<template>
    <loading :loading="propsLoading" lazy>
        <div
            id="admin-stations"
            class="admin-console"
        >
            <card-page :title="$gettext('Media Services')">
                <template #info>
                    <p class="card-text mb-0">
                        {{
                            $gettext(
                                'Fleet view of all stations. Ports, suspend, and delete are superadmin controls. New stations ship AutoDJ-ready.',
                            )
                        }}
                    </p>
                </template>
                <template #actions>
                    <add-button
                        :text="$gettext('Create Service')"
                        @click="doCreate"
                    />
                </template>

                <div
                    v-if="selectedStations.length > 0"
                    class="d-flex flex-wrap gap-2 mb-3 media-services-bulk"
                >
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-warning"
                        :disabled="bulkBusy"
                        @click="doBulkSuspend(true)"
                    >
                        {{ $gettext('Suspend Selected') }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-success"
                        :disabled="bulkBusy"
                        @click="doBulkSuspend(false)"
                    >
                        {{ $gettext('Unsuspend Selected') }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-warning"
                        :disabled="bulkBusy"
                        @click="doBulkRestart"
                    >
                        {{ $gettext('Restart Selected') }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-danger"
                        :disabled="bulkBusy"
                        @click="doBulkDelete"
                    >
                        {{ $gettext('Delete Selected') }}
                    </button>
                    <span class="align-self-center broadcast-mono text-muted">
                        {{ selectedStations.length }}
                        {{ $gettext('selected') }}
                    </span>
                </div>

                <data-table
                    id="stations"
                    paginated
                    selectable
                    :fields="fields"
                    :provider="listItemProvider"
                    @row-selected="onRowSelected"
                >
                    <template #empty>
                        <div class="admin-guided-empty">
                            <div class="admin-guided-empty-title">
                                {{ $gettext('No media services yet') }}
                            </div>
                            <p class="admin-guided-empty-text">
                                {{
                                    $gettext(
                                        'Create a station to assign ports and an AutoDJ-ready playlist. Operators then upload music and go on the air.',
                                    )
                                }}
                            </p>
                            <button
                                type="button"
                                class="btn btn-primary"
                                @click="doCreate"
                            >
                                {{ $gettext('Create Service') }}
                            </button>
                        </div>
                    </template>
                    <template #cell(name)="{item}">
                        <div class="typography-subheading">
                            {{ item.name }}
                        </div>
                        <code class="broadcast-mono">{{ item.short_name }}</code>
                    </template>
                    <template #cell(customer)="{item}">
                        <span class="broadcast-mono">
                            {{ item.customer_name || '—' }}
                        </span>
                    </template>
                    <template #cell(portbase)="{item}">
                        <span class="broadcast-mono">
                            {{ item.frontend_config?.port ?? '—' }}
                        </span>
                    </template>
                    <template #cell(connections)="{item}">
                        <span class="broadcast-mono">
                            {{ formatConnections(item) }}
                        </span>
                    </template>
                    <template #cell(status)="{item}">
                        <div class="station-status-cell">
                            <span
                                v-if="!item.is_enabled"
                                class="badge broadcast-tally-standby"
                            >
                                {{ $gettext('SUSPENDED') }}
                            </span>
                            <span
                                v-else-if="liveById[item.id]?.is_online"
                                class="badge broadcast-tally-onair"
                            >
                                {{ $gettext('ONLINE') }}
                            </span>
                            <span
                                v-else
                                class="badge broadcast-tally-standby"
                            >
                                {{ $gettext('OFFLINE') }}
                            </span>
                        </div>
                    </template>
                    <template #cell(actions)="row">
                        <div class="btn-group btn-group-sm media-services-actions">
                            <a
                                class="btn btn-secondary"
                                :href="row.item.links.manage"
                                target="_blank"
                                :title="$gettext('Overview')"
                            >
                                {{ $gettext('Open') }}
                            </a>
                            <button
                                type="button"
                                class="btn btn-primary"
                                :title="$gettext('Configure')"
                                @click="doEdit(row.item.links.self)"
                            >
                                {{ $gettext('Edit') }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-warning"
                                :title="$gettext('Restart')"
                                :disabled="restartingId === row.item.id"
                                @click="doRestart(row.item)"
                            >
                                {{ $gettext('Restart') }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="(row.item.is_enabled) ? 'btn-outline-warning' : 'btn-success'"
                                :title="(row.item.is_enabled) ? $gettext('Suspend') : $gettext('Unsuspend')"
                                @click="doToggle(row.item)"
                            >
                                {{ (row.item.is_enabled) ? $gettext('Suspend') : $gettext('Unsuspend') }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-secondary"
                                @click="doClone(row.item.name, row.item.links.clone)"
                            >
                                {{ $gettext('Clone') }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-danger"
                                :title="$gettext('Delete')"
                                @click="doDelete(row.item.links.self)"
                            >
                                {{ $gettext('Delete') }}
                            </button>
                        </div>
                    </template>
                </data-table>
            </card-page>

            <admin-stations-edit-modal
                v-if="props"
                v-bind="props.formProps"
                ref="$editModal"
                :create-url="listUrl"
                @relist="() => relist()"
            />

            <admin-stations-clone-modal
                ref="$cloneModal"
                @relist="() => relist()"
            />
        </div>
    </loading>
</template>

<script setup lang="ts">
import { useQuery } from "@tanstack/vue-query";
import { computed, ref, useTemplateRef } from "vue";
import AdminStationsCloneModal from "~/components/Admin/Stations/CloneModal.vue";
import AdminStationsEditModal from "~/components/Admin/Stations/EditModal.vue";
import AddButton from "~/components/Common/AddButton.vue";
import CardPage from "~/components/Common/CardPage.vue";
import DataTable, { DataTableField } from "~/components/Common/DataTable.vue";
import { useDialog } from "~/components/Common/Dialogs/useDialog.ts";
import Loading from "~/components/Common/Loading.vue";
import { useNotify } from "~/components/Common/Toasts/useNotify.ts";
import {
    ApiAdminVueStationsProps,
    ApiNowPlaying,
    HasLinks,
    Station,
} from "~/entities/ApiInterfaces.ts";
import { QueryKeys } from "~/entities/Queries.ts";
import { useApiItemProvider } from "~/functions/dataTable/useApiItemProvider.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import useConfirmAndDelete from "~/functions/useConfirmAndDelete";
import useHasEditModal from "~/functions/useHasEditModal";
import { useAxios } from "~/vendor/axios.ts";
import { useTranslate } from "~/vendor/gettext";

const { getApiUrl } = useApiRouter();
const listUrl = getApiUrl("/admin/stations");
const propsUrl = getApiUrl("/admin/vue/stations");
const liveUrl = getApiUrl("/frontend/dashboard/stations");

const { axios, axiosSilent } = useAxios();

const { data: props, isLoading: propsLoading } =
    useQuery<ApiAdminVueStationsProps>({
        queryKey: [QueryKeys.AdminStations, "props"],
        queryFn: async ({ signal }) => {
            const { data } = await axios.get<ApiAdminVueStationsProps>(
                propsUrl.value,
                { signal },
            );
            return data;
        },
    });

type LiveRow = ApiNowPlaying & Required<HasLinks>;

const { data: liveStations } = useQuery<LiveRow[]>({
    queryKey: [QueryKeys.AdminStations, "live"],
    queryFn: async ({ signal }) => {
        const { data } = await axiosSilent.get(liveUrl.value, {
            signal,
            params: {
                rowCount: 0,
                internal: true,
            },
        });
        if (Array.isArray(data)) {
            return data;
        }
        if (data && Array.isArray(data.rows)) {
            return data.rows;
        }
        return [];
    },
    placeholderData: () => [],
    refetchInterval: 15 * 1000,
});

const liveById = computed(() => {
    const map: Record<number, LiveRow> = {};
    for (const row of liveStations.value ?? []) {
        const id = row.station?.id;
        if (typeof id === "number") {
            map[id] = row;
        }
    }
    return map;
});

const { $gettext } = useTranslate();

type Row = Required<Station & HasLinks>;

const fields: DataTableField<Row>[] = [
    {
        key: "name",
        isRowHeader: true,
        label: $gettext("Service"),
        sortable: true,
    },
    {
        key: "customer",
        label: $gettext("Customer"),
        sortable: false,
        class: "shrink",
    },
    {
        key: "portbase",
        label: $gettext("Portbase"),
        sortable: false,
        class: "shrink",
    },
    {
        key: "connections",
        label: $gettext("Connections"),
        sortable: false,
        class: "shrink",
    },
    {
        key: "status",
        label: $gettext("Status"),
        sortable: false,
        class: "shrink",
    },
    {
        key: "actions",
        label: $gettext("Actions"),
        sortable: false,
        class: "shrink",
    },
];

const listItemProvider = useApiItemProvider<Row>(listUrl, [
    QueryKeys.AdminStations,
    "data",
]);

const relist = () => {
    void listItemProvider.refresh();
};

const $editModal = useTemplateRef("$editModal");
const { doCreate, doEdit } = useHasEditModal($editModal);

const $cloneModal = useTemplateRef("$cloneModal");

const doClone = (stationName: string, url: string) => {
    $cloneModal.value?.create(stationName, url);
};

const { showAlert } = useDialog();
const { notifySuccess, notifyError } = useNotify();
const restartingId = ref<number | null>(null);
const selectedStations = ref<Row[]>([]);
const bulkBusy = ref(false);

const onRowSelected = (rows: Row[]) => {
    selectedStations.value = rows;
};

const formatConnections = (item: Row) => {
    const live = liveById.value[item.id];
    const current = live?.listeners?.total ?? 0;
    const max = item.frontend_config?.max_listeners;
    const bitrate = item.max_bitrate;
    const base =
        max !== null && max !== undefined && Number(max) > 0
            ? `${current} / ${max}`
            : String(current);
    if (bitrate && bitrate > 0) {
        return `${base} @ ${bitrate}K`;
    }
    return base;
};

const doRestart = async (station: Row) => {
    restartingId.value = station.id;
    try {
        const { data } = await axios.post(
            getApiUrl(`/station/${station.id}/restart`).value,
        );
        notifySuccess(data.message ?? $gettext("Station restarted."));
        relist();
    } catch {
        notifyError($gettext("Could not restart station."));
    } finally {
        restartingId.value = null;
    }
};

const doToggle = async (station: Row) => {
    const { value } = await showAlert(
        station.is_enabled
            ? {
                  title: $gettext("Suspend service?"),
                  confirmButtonText: $gettext("Suspend"),
                  confirmButtonClass: "btn-warning",
                  focusCancel: true,
              }
            : {
                  title: $gettext("Unsuspend service?"),
                  confirmButtonText: $gettext("Unsuspend"),
                  confirmButtonClass: "btn-success",
                  focusCancel: false,
              },
    );

    if (!value) {
        return;
    }

    const { data } = await axios.put(station.links.self, {
        is_enabled: !station.is_enabled,
    });

    notifySuccess(data.message);
    relist();
};

const doBulkSuspend = async (suspend: boolean) => {
    const { value } = await showAlert({
        title: suspend
            ? $gettext("Suspend selected services?")
            : $gettext("Unsuspend selected services?"),
        confirmButtonText: suspend
            ? $gettext("Suspend Selected")
            : $gettext("Unsuspend Selected"),
        confirmButtonClass: suspend ? "btn-warning" : "btn-success",
        focusCancel: true,
    });
    if (!value) {
        return;
    }

    bulkBusy.value = true;
    try {
        for (const station of selectedStations.value) {
            if (station.is_enabled === !suspend) {
                continue;
            }
            await axios.put(station.links.self, {
                is_enabled: !suspend,
            });
        }
        notifySuccess(
            suspend
                ? $gettext("Selected services suspended.")
                : $gettext("Selected services unsuspended."),
        );
        selectedStations.value = [];
        relist();
    } catch {
        notifyError($gettext("Bulk suspend/unsuspend failed."));
    } finally {
        bulkBusy.value = false;
    }
};

const doBulkRestart = async () => {
    const { value } = await showAlert({
        title: $gettext("Restart selected services?"),
        confirmButtonText: $gettext("Restart Selected"),
        confirmButtonClass: "btn-warning",
        focusCancel: true,
    });
    if (!value) {
        return;
    }

    bulkBusy.value = true;
    try {
        for (const station of selectedStations.value) {
            await axios.post(getApiUrl(`/station/${station.id}/restart`).value);
        }
        notifySuccess($gettext("Selected services restarted."));
        selectedStations.value = [];
        relist();
    } catch {
        notifyError($gettext("Bulk restart failed."));
    } finally {
        bulkBusy.value = false;
    }
};

const doBulkDelete = async () => {
    const { value } = await showAlert({
        title: $gettext("Delete selected services?"),
        confirmButtonText: $gettext("Delete Selected"),
        confirmButtonClass: "btn-danger",
        focusCancel: true,
    });
    if (!value) {
        return;
    }

    bulkBusy.value = true;
    try {
        for (const station of selectedStations.value) {
            await axios.delete(station.links.self);
        }
        notifySuccess($gettext("Selected services deleted."));
        selectedStations.value = [];
        relist();
    } catch {
        notifyError($gettext("Bulk delete failed."));
    } finally {
        bulkBusy.value = false;
    }
};

const { doDelete } = useConfirmAndDelete($gettext("Delete Service?"), () =>
    relist(),
);
</script>
