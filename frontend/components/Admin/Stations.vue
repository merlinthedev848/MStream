<template>
    <loading :loading="propsLoading" lazy>
        <div
            id="admin-stations"
            class="admin-console"
        >
            <card-page :title="$gettext('Stations')">
                <template #info>
                    <p class="card-text mb-0">
                        {{
                            $gettext(
                                'Create and manage stations. New stations ship AutoDJ-ready — upload tracks and they go on the air.',
                            )
                        }}
                    </p>
                </template>
                <template #actions>
                    <add-button
                        :text="$gettext('New Station')"
                        @click="doCreate"
                    />
                </template>

                <data-table
                    id="stations"
                    paginated
                    :fields="fields"
                    :provider="listItemProvider"
                >
                    <template #empty>
                        <div class="admin-guided-empty">
                            <div class="admin-guided-empty-title">
                                {{ $gettext('No stations yet') }}
                            </div>
                            <p class="admin-guided-empty-text">
                                {{
                                    $gettext(
                                        'Create your first station to get a default playlist, ports, and AutoDJ configuration. After that, operators only need to upload music.',
                                    )
                                }}
                            </p>
                            <button
                                type="button"
                                class="btn btn-primary"
                                @click="doCreate"
                            >
                                {{ $gettext('Create Station') }}
                            </button>
                        </div>
                    </template>
                    <template #cell(name)="{item}">
                        <div class="typography-subheading">
                            {{ item.name }}

                            <span
                                v-if="!item.is_enabled"
                                class="badge text-bg-secondary ms-1"
                            >{{ $gettext('Disabled') }}</span>
                        </div>
                        <code class="broadcast-mono">{{ item.short_name }}</code>
                    </template>
                    <template #cell(status)="{item}">
                        <div class="station-status-cell">
                            <span
                                v-if="!item.is_enabled"
                                class="badge text-bg-secondary"
                            >
                                {{ $gettext('DISABLED') }}
                            </span>
                            <span
                                v-else-if="liveById[item.id]?.is_online"
                                class="badge broadcast-tally-onair"
                            >
                                {{ $gettext('ON AIR') }}
                            </span>
                            <span
                                v-else
                                class="badge broadcast-tally-standby"
                            >
                                {{ $gettext('OFFLINE') }}
                            </span>
                        </div>
                    </template>
                    <template #cell(listeners)="{item}">
                        <span class="broadcast-mono station-listeners-cell">
                            {{ liveById[item.id]?.listeners?.total ?? '—' }}
                        </span>
                    </template>
                    <template #cell(actions)="row">
                        <div class="btn-group btn-group-sm">
                            <a
                                class="btn btn-secondary"
                                :href="row.item.links.manage"
                                target="_blank"
                            >
                                {{ $gettext('Open Station') }}
                            </a>
                            <button
                                type="button"
                                class="btn btn-secondary"
                                @click="doClone(row.item.name, row.item.links.clone)"
                            >
                                {{ $gettext('Clone') }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="(row.item.is_enabled) ? 'btn-warning' : 'btn-success'"
                                @click="doToggle(row.item)"
                            >
                                {{ (row.item.is_enabled) ? $gettext('Disable') : $gettext('Enable') }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-primary"
                                @click="doEdit(row.item.links.self)"
                            >
                                {{ $gettext('Edit') }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-danger"
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
import { get } from "es-toolkit/compat";
import { computed, useTemplateRef } from "vue";
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
        label: $gettext("Station"),
        sortable: true,
    },
    {
        key: "status",
        label: $gettext("Status"),
        sortable: false,
        class: "shrink",
    },
    {
        key: "listeners",
        label: $gettext("Listeners"),
        sortable: false,
        class: "shrink",
    },
    {
        key: "frontend_type",
        label: $gettext("Broadcasting"),
        sortable: false,
        formatter: (value) => {
            return get(props.value?.frontendTypes, [value, "name"], "");
        },
    },
    {
        key: "backend_type",
        label: $gettext("AutoDJ"),
        sortable: false,
        formatter: (value) => {
            return get(props.value?.backendTypes, [value, "name"], "");
        },
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
const { notifySuccess } = useNotify();

const doToggle = async (station: Row) => {
    const { value } = await showAlert(
        station.is_enabled
            ? {
                  title: $gettext("Disable station?"),
                  confirmButtonText: $gettext("Disable"),
                  confirmButtonClass: "btn-warning",
                  focusCancel: true,
              }
            : {
                  title: $gettext("Enable station?"),
                  confirmButtonText: $gettext("Enable"),
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

const { doDelete } = useConfirmAndDelete($gettext("Delete Station?"), () =>
    relist(),
);
</script>
