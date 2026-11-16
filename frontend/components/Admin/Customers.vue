<template>
    <loading :loading="propsLoading" lazy>
        <div
            id="admin-customers"
            class="admin-console"
        >
            <card-page :title="$gettext('Customers')">
                <template #info>
                    <p class="card-text mb-0">
                        {{
                            $gettext(
                                'Customer accounts own media services. Create a login, assign a service, then open the console — or Login as the customer.',
                            )
                        }}
                    </p>
                </template>
                <template #actions>
                    <add-button
                        :text="$gettext('New Customer')"
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
                        <div class="admin-guided-empty">
                            <div class="admin-guided-empty-title">
                                {{ $gettext('No customers') }}
                            </div>
                            <p class="admin-guided-empty-text">
                                {{
                                    $gettext(
                                        'Create a customer with a login email, then create a service assigned to them.',
                                    )
                                }}
                            </p>
                            <button
                                type="button"
                                class="btn btn-primary"
                                @click="doCreate"
                            >
                                {{ $gettext('New Customer') }}
                            </button>
                        </div>
                    </template>
                    <template #cell(name)="row">
                        <div class="fw-semibold">
                            {{ row.item.name }}
                        </div>
                        <div
                            v-if="row.item.owner_email"
                            class="broadcast-mono text-muted small"
                        >
                            {{ row.item.owner_email }}
                        </div>
                    </template>
                    <template #cell(user_level)="row">
                        <span
                            class="badge"
                            :class="levelBadgeClass(row.item.user_level)"
                        >
                            {{ row.item.user_level || $gettext('No Login') }}
                        </span>
                    </template>
                    <template #cell(services_count)="row">
                        <button
                            type="button"
                            class="btn btn-link btn-sm px-0"
                            @click="doOverview(row.item)"
                        >
                            {{ row.item.services_count ?? 0 }}
                            {{ $gettext('Media Services') }}
                        </button>
                    </template>
                    <template #cell(actions)="row">
                        <div class="btn-group btn-group-sm">
                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                @click="doOverview(row.item)"
                            >
                                {{ $gettext('Overview') }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                @click="doEdit(row.item.links.self)"
                            >
                                {{ $gettext('Manage') }}
                            </button>
                            <a
                                v-if="row.item.links?.masquerade"
                                class="btn btn-warning"
                                :href="row.item.links.masquerade"
                                target="_blank"
                                rel="noopener"
                            >
                                {{ $gettext('Login') }}
                            </a>
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

                <edit-modal
                    v-if="!propsLoading"
                    ref="$editModal"
                    :create-url="listUrl"
                    :resellers="resellerOptions"
                    :users="userOptions"
                    @relist="() => relist()"
                />

                <overview-modal ref="$overviewModal" />
            </card-page>
        </div>
    </loading>
</template>

<script setup lang="ts">
import { useQuery } from "@tanstack/vue-query";
import { computed, useTemplateRef } from "vue";
import EditModal from "~/components/Admin/Customers/EditModal.vue";
import OverviewModal from "~/components/Admin/Customers/OverviewModal.vue";
import AddButton from "~/components/Common/AddButton.vue";
import CardPage from "~/components/Common/CardPage.vue";
import DataTable, { DataTableField } from "~/components/Common/DataTable.vue";
import Loading from "~/components/Common/Loading.vue";
import {
    Customer,
    HasLinks,
    Reseller,
    User,
} from "~/entities/ApiInterfaces.ts";
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
const usersUrl = getApiUrl("/admin/users");

const { axios } = useAxios();
const { $gettext } = useTranslate();

const { data: resellerRows, isLoading: resellersLoading } = useQuery({
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

const { data: userRows, isLoading: usersLoading } = useQuery({
    queryKey: [QueryKeys.AdminUsers, "customer-owner-options"],
    queryFn: async ({ signal }) => {
        const { data } = await axios.get<{ rows: User[] }>(usersUrl.value, {
            signal,
            params: { rowCount: 0 },
        });
        return data.rows ?? [];
    },
});

const propsLoading = computed(
    () => resellersLoading.value || usersLoading.value,
);

const resellerOptions = computed(() => {
    const options: Record<string, string> = {};
    for (const row of resellerRows.value ?? []) {
        if (row.id != null && row.name) {
            options[String(row.id)] = row.name;
        }
    }
    return options;
});

const userOptions = computed(() => {
    const options: Record<string, string> = {};
    for (const row of userRows.value ?? []) {
        if (row.id != null) {
            options[String(row.id)] =
                row.name?.trim() || row.email || String(row.id);
        }
    }
    return options;
});

type Row = Customer &
    Required<HasLinks> & {
        owner_email?: string | null;
        user_level?: string;
        services_count?: number;
        stations?: Array<{
            id: number;
            name: string;
            short_name: string;
            is_enabled: boolean;
            links: { manage: string };
        }>;
    };

const fields: DataTableField<Row>[] = [
    {
        key: "name",
        isRowHeader: true,
        label: $gettext("Name"),
        sortable: false,
    },
    {
        key: "user_level",
        label: $gettext("User Level"),
        sortable: false,
        class: "shrink",
    },
    {
        key: "services_count",
        label: $gettext("Media Services"),
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

const itemProvider = useApiItemProvider<Row>(listUrl, [
    QueryKeys.AdminStations,
    "customers",
]);

const relist = () => {
    void itemProvider.refresh();
};

const $editModal = useTemplateRef("$editModal");
const { doCreate, doEdit } = useHasEditModal($editModal);

const $overviewModal = useTemplateRef("$overviewModal");
const doOverview = (item: Row) => {
    $overviewModal.value?.open(item);
};

const levelBadgeClass = (level?: string) => {
    if (level === "Administrator") {
        return "text-bg-success";
    }
    if (level === "Customer") {
        return "text-bg-primary";
    }
    return "text-bg-secondary";
};

const { doDelete } = useConfirmAndDelete($gettext("Delete Customer?"), () =>
    relist(),
);
</script>
