<template>
    <div class="admin-console">
        <card-page :title="$gettext('Stream Relays')">
            <template #info>
                <p class="card-text">
                    {{
                        $gettext('MStreamRelay is a standalone edge service that relays your stations and reports listeners back to this instance. This page lists relays that are currently connected.')
                    }}
                </p>

                <a
                    class="btn btn-sm btn-light"
                    target="_blank"
                    href="https://github.com/merlinthedev848/MStreamRelay"
                >
                    {{ $gettext('About MStreamRelay') }}
                </a>
            </template>

            <data-table
                id="relays"
                paginated
                :fields="fields"
                :provider="listItemProvider"
            >
                <template #empty>
                    <div class="admin-guided-empty">
                        <div class="admin-guided-empty-title">
                            {{ $gettext('No relays connected') }}
                        </div>
                        <p class="admin-guided-empty-text mb-0">
                            {{
                                $gettext(
                                    'When an MStreamRelay instance connects, it appears here with first-seen and last-heartbeat times.',
                                )
                            }}
                        </p>
                    </div>
                </template>
                <template #cell(name)="{ item }">
                    <h5 class="mb-0">
                        <a
                            :href="item.base_url"
                            target="_blank"
                        >
                            {{ item.name }}
                        </a>
                    </h5>
                </template>
                <template #cell(is_visible_on_public_pages)="{ item }">
                    <span
                        v-if="item.is_visible_on_public_pages"
                        class="badge broadcast-tally-live"
                    >
                        {{ $gettext('PUBLIC') }}
                    </span>
                    <span
                        v-else
                        class="badge text-bg-secondary"
                    >
                        {{ $gettext('HIDDEN') }}
                    </span>
                </template>
                <template #cell(created_at)="{ item }">
                    <span class="broadcast-mono">{{ formatRelayTime(item.created_at) }}</span>
                </template>
                <template #cell(updated_at)="{ item }">
                    <span class="broadcast-mono">{{ formatRelayTime(item.updated_at) }}</span>
                </template>
            </data-table>
        </card-page>
    </div>
</template>

<script setup lang="ts">
import CardPage from "~/components/Common/CardPage.vue";
import DataTable, { DataTableField } from "~/components/Common/DataTable.vue";
import { Relay } from "~/entities/ApiInterfaces.ts";
import { QueryKeys } from "~/entities/Queries.ts";
import { useApiItemProvider } from "~/functions/dataTable/useApiItemProvider.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import { useTranslate } from "~/vendor/gettext";
import { useLuxon } from "~/vendor/luxon";
import { useMStream } from "~/vendor/mstream";

const { getApiUrl } = useApiRouter();
const listUrl = getApiUrl("/admin/relays/list");

const { $gettext } = useTranslate();

const { timeConfig } = useMStream();

const { DateTime } = useLuxon();

const formatRelayTime = (value: number | string | null | undefined) => {
    if (value === null || value === undefined || value === "") {
        return "—";
    }

    let dt =
        typeof value === "number"
            ? DateTime.fromSeconds(value)
            : DateTime.fromISO(value);

    if (!dt.isValid && typeof value === "string" && /^\d+$/.test(value)) {
        dt = DateTime.fromSeconds(Number(value));
    }

    if (!dt.isValid) {
        return "—";
    }

    return dt.toLocaleString({
        ...DateTime.DATETIME_SHORT,
        ...timeConfig,
    });
};

const fields: DataTableField<Relay>[] = [
    {
        key: "name",
        isRowHeader: true,
        label: $gettext("Relay"),
        sortable: true,
    },
    {
        key: "is_visible_on_public_pages",
        label: $gettext("Public"),
        sortable: true,
    },
    {
        key: "created_at",
        label: $gettext("First Connected"),
        sortable: true,
    },
    {
        key: "updated_at",
        label: $gettext("Last Heartbeat"),
        sortable: true,
    },
];

const listItemProvider = useApiItemProvider<Relay>(listUrl, [
    QueryKeys.AdminRelays,
]);
</script>
