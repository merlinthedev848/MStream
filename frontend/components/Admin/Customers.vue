<template>
    <loading :loading="propsLoading" lazy>
        <card-page :title="$gettext('Customers')">
            <template #info>
                <p class="card-text">
                    {{
                        $gettext(
                            'Customers sit under a reseller and can own one or more media services.',
                        )
                    }}
                </p>
            </template>
            <template #actions>
                <add-button
                    :text="$gettext('Add Customer')"
                    @click="doCreate"
                />
            </template>

            <data-table
                id="customers"
                :fields="fields"
                :show-toolbar="false"
                :provider="itemProvider"
            >
                <template #empty>
                    <div class="admin-guided-empty admin-console">
                        <div class="admin-guided-empty-title">
                            {{ $gettext('No customers') }}
                        </div>
                        <p class="admin-guided-empty-text mb-0">
                            {{
                                $gettext(
                                    'Create a customer after adding at least one reseller.',
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
                v-if="!propsLoading"
                ref="$editModal"
                :create-url="listUrl"
                :resellers="resellerOptions"
                @relist="() => relist()"
            />
        </card-page>
    </loading>
</template>

<script setup lang="ts">
import { useQuery } from "@tanstack/vue-query";
import { computed, useTemplateRef } from "vue";
import EditModal from "~/components/Admin/Customers/EditModal.vue";
import AddButton from "~/components/Common/AddButton.vue";
import CardPage from "~/components/Common/CardPage.vue";
import DataTable, { DataTableField } from "~/components/Common/DataTable.vue";
import Loading from "~/components/Common/Loading.vue";
import { Customer, HasLinks, Reseller } from "~/entities/ApiInterfaces.ts";
import { QueryKeys } from "~/entities/Queries.ts";
import { useApiItemProvider } from "~/functions/dataTable/useApiItemProvider.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import useConfirmAndDelete from "~/functions/useConfirmAndDelete";
import useHasEditModal from "~/functions/useHasEditModal";
import { useAxios } from "~/vendor/axios.ts";
import { useTranslate } from "~/vendor/gettext";

const { getApiUrl } = useApiRouter();
const listUrl = getApiUrl("/admin/customers");
const resellersUrl = getApiUrl("/admin/resellers");

const { axios } = useAxios();
const { $gettext } = useTranslate();

const { data: resellerRows, isLoading: propsLoading } = useQuery({
    queryKey: [QueryKeys.AdminStations, "reseller-options"],
    queryFn: async ({ signal }) => {
        const { data } = await axios.get<{ rows: Reseller[] }>(
            resellersUrl.value,
            {
                signal,
                params: { rowCount: 0 },
            },
        );
        return data.rows ?? [];
    },
});

const resellerOptions = computed(() => {
    const options: Record<string, string> = {};
    for (const row of resellerRows.value ?? []) {
        if (row.id != null && row.name) {
            options[String(row.id)] = row.name;
        }
    }
    return options;
});

type Row = Required<Customer & HasLinks>;

const fields: DataTableField<Row>[] = [
    {
        key: "name",
        isRowHeader: true,
        label: $gettext("Name"),
        sortable: false,
    },
    {
        key: "reseller",
        label: $gettext("Reseller"),
        sortable: false,
        formatter: (value) => {
            if (value == null || value === "") {
                return "—";
            }
            return resellerOptions.value[String(value)] ?? String(value);
        },
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
    "customers",
]);

const relist = () => {
    void itemProvider.refresh();
};

const $editModal = useTemplateRef("$editModal");
const { doCreate, doEdit } = useHasEditModal($editModal);

const { doDelete } = useConfirmAndDelete($gettext("Delete Customer?"), () =>
    relist(),
);
</script>
