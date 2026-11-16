<template>
    <modal
        id="customer_overview_modal"
        ref="$modal"
        size="lg"
        :title="title"
        @hidden="clear"
    >
        <div class="admin-console px-3 pb-3">
            <p class="admin-section-lead">
                {{
                    $gettext(
                        'Customer → assign a service → open the console. Login opens the customer shell.',
                    )
                }}
            </p>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <span
                    class="badge"
                    :class="
                        customer?.user_level === 'Administrator'
                            ? 'text-bg-success'
                            : customer?.user_level === 'Customer'
                              ? 'text-bg-primary'
                              : 'text-bg-secondary'
                    "
                >
                    {{ customer?.user_level || $gettext('No Login') }}
                </span>
                <span
                    v-if="customer?.owner_email"
                    class="broadcast-mono text-muted align-self-center"
                >
                    {{ customer.owner_email }}
                </span>
            </div>

            <h3 class="h6 text-uppercase text-muted mb-2">
                {{ $gettext('Media Services') }}
            </h3>

            <div
                v-if="!customer?.stations?.length"
                class="admin-guided-empty mb-3"
            >
                <div class="admin-guided-empty-title">
                    {{ $gettext('No services yet') }}
                </div>
                <p class="admin-guided-empty-text">
                    {{
                        $gettext(
                            'Create a service and assign this customer in the wizard.',
                        )
                    }}
                </p>
                <router-link
                    class="btn btn-primary"
                    :to="{ name: 'admin:stations:index' }"
                    @click="hide"
                >
                    {{ $gettext('Create Service') }}
                </router-link>
            </div>

            <ul
                v-else
                class="list-group list-group-flush mb-3"
            >
                <li
                    v-for="station in customer.stations"
                    :key="station.id"
                    class="list-group-item d-flex align-items-center gap-2 px-0"
                >
                    <div class="flex-fill">
                        <div class="fw-semibold">
                            {{ station.name }}
                        </div>
                        <code class="broadcast-mono">{{ station.short_name }}</code>
                    </div>
                    <span
                        v-if="!station.is_enabled"
                        class="badge broadcast-tally-standby"
                    >
                        {{ $gettext('SUSPENDED') }}
                    </span>
                    <a
                        class="btn btn-sm btn-primary"
                        :href="station.links.manage"
                    >
                        {{ $gettext('Open Console') }}
                    </a>
                </li>
            </ul>
        </div>

        <template #modal-footer>
            <button
                type="button"
                class="btn btn-secondary"
                @click="hide"
            >
                {{ $gettext('Close') }}
            </button>
            <a
                v-if="customer?.links?.masquerade"
                class="btn btn-warning"
                :href="customer.links.masquerade"
                target="_blank"
                rel="noopener"
            >
                {{ $gettext('Login') }}
            </a>
            <router-link
                class="btn btn-outline-primary"
                :to="{ name: 'admin:stations:index' }"
                @click="hide"
            >
                {{ $gettext('Services') }}
            </router-link>
        </template>
    </modal>
</template>

<script setup lang="ts">
import { computed, ref, useTemplateRef } from "vue";
import Modal from "~/components/Common/Modal.vue";
import { Customer, HasLinks } from "~/entities/ApiInterfaces.ts";
import { useHasModal } from "~/functions/useHasModal.ts";
import { useTranslate } from "~/vendor/gettext";

type CustomerOverview = Customer &
    HasLinks & {
        owner_email?: string | null;
        user_level?: string;
        stations?: Array<{
            id: number;
            name: string;
            short_name: string;
            is_enabled: boolean;
            links: { manage: string };
        }>;
    };

const { $gettext } = useTranslate();
const $modal = useTemplateRef("$modal");
const { show, hide } = useHasModal($modal);

const customer = ref<CustomerOverview | null>(null);

const title = computed(() =>
    customer.value?.name
        ? String(customer.value.name)
        : $gettext("Customer Overview"),
);

const open = (row: CustomerOverview) => {
    customer.value = row;
    show();
};

const clear = () => {
    customer.value = null;
};

defineExpose({
    open,
    hide,
});
</script>
