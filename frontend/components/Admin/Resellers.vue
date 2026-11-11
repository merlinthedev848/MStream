<template>
    <card-page :title="$gettext('Resellers')">
        <template #info>
            <p class="card-text">
                {{
                    $gettext(
                        'Reseller accounts scope which media services and customers a partner can manage. Assign the “Administer Reseller Stations” permission to partner users and link their account to a reseller.',
                    )
                }}
            </p>
        </template>
        <template #actions>
            <add-button
                :text="$gettext('Add Reseller')"
                @click="doCreate"
            />
        </template>

        <data-table
            id="resellers"
            :fields="fields"
            :show-toolbar="false"
            :provider="itemProvider"
        >
            <template #empty>
                <div class="admin-guided-empty admin-console">
                    <div class="admin-guided-empty-title">
                        {{ $gettext('No resellers') }}
                    </div>
                    <p class="admin-guided-empty-text mb-0">
                        {{
                            $gettext(
                                'Create a reseller to begin MediaCP-style multi-tenant hosting.',
                            )
                        }}
                    </p>
                </div>
            </template>
            <template #cell(actions)="{ item }">
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
        </data-table>

        <edit-modal
            ref="$editModal"
            :create-url="listUrl"
            @relist="() => relist()"
        />
    </card-page>
</template>

<script setup lang="ts">
import { useTemplateRef } from "vue";
import EditModal from "~/components/Admin/Resellers/EditModal.vue";
import AddButton from "~/components/Common/AddButton.vue";
import CardPage from "~/components/Common/CardPage.vue";
import DataTable, { DataTableField } from "~/components/Common/DataTable.vue";
import { HasLinks, Reseller } from "~/entities/ApiInterfaces.ts";
import { QueryKeys } from "~/entities/Queries.ts";
import { useApiItemProvider } from "~/functions/dataTable/useApiItemProvider.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import useConfirmAndDelete from "~/functions/useConfirmAndDelete";
import useHasEditModal from "~/functions/useHasEditModal";
import { useTranslate } from "~/vendor/gettext";

const { getApiUrl } = useApiRouter();
const listUrl = getApiUrl("/admin/resellers");

const { $gettext } = useTranslate();

type Row = Required<Reseller & HasLinks>;

const fields: DataTableField<Row>[] = [
    {
        key: "name",
        isRowHeader: true,
        label: $gettext("Name"),
        sortable: false,
    },
    {
        key: "actions",
        label: $gettext("Actions"),
        sortable: false,
        class: "shrink",
    },
];

const itemProvider = useApiItemProvider(listUrl, [
    QueryKeys.AdminStations,
    "resellers",
]);

const relist = () => {
    void itemProvider.refresh();
};

const $editModal = useTemplateRef("$editModal");
const { doCreate, doEdit } = useHasEditModal($editModal);

const { doDelete } = useConfirmAndDelete($gettext("Delete Reseller?"), () =>
    relist(),
);
</script>
