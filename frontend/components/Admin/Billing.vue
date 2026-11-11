<template>
    <div
        id="admin-billing"
        class="admin-console"
    >
        <card-page :title="$gettext('Hosting Packages')">
            <template #info>
                <p class="card-text mb-0">
                    {{
                        $gettext(
                            'Provision or resize media services for billing automation (WHMCS / Blesta / FOSSBilling). Requires All permissions. Disk quota applies to media, recordings, and podcasts storage.',
                        )
                    }}
                </p>
            </template>

            <form
                class="card-body"
                @submit.prevent="doSubmit"
            >
                <div class="row g-3 mb-3">
                    <form-markup
                        id="billing_action"
                        class="col-md-4"
                        :label="$gettext('Action')"
                    >
                        <select
                            v-model="form.action"
                            class="form-select"
                        >
                            <option value="create">
                                {{ $gettext('Create / Provision') }}
                            </option>
                            <option value="modify_package">
                                {{ $gettext('Modify Package') }}
                            </option>
                            <option value="suspend">
                                {{ $gettext('Suspend') }}
                            </option>
                            <option value="unsuspend">
                                {{ $gettext('Unsuspend') }}
                            </option>
                            <option value="status">
                                {{ $gettext('Status') }}
                            </option>
                            <option value="terminate">
                                {{ $gettext('Terminate') }}
                            </option>
                        </select>
                    </form-markup>

                    <form-markup
                        v-if="needsStation"
                        id="billing_station"
                        class="col-md-4"
                        :label="$gettext('Station ID or Short Name')"
                    >
                        <input
                            v-model="form.station"
                            type="text"
                            class="form-control"
                            required
                        >
                    </form-markup>

                    <form-markup
                        v-if="form.action === 'create'"
                        id="billing_name"
                        class="col-md-4"
                        :label="$gettext('Service Name')"
                    >
                        <input
                            v-model="form.name"
                            type="text"
                            class="form-control"
                            required
                        >
                    </form-markup>
                </div>

                <div
                    v-if="showsPackageFields"
                    class="row g-3 mb-3"
                >
                    <form-markup
                        id="billing_customer"
                        class="col-md-4"
                        :label="$gettext('Customer')"
                    >
                        <input
                            v-model="form.customer_name"
                            type="text"
                            class="form-control"
                        >
                    </form-markup>
                    <form-markup
                        id="billing_listeners"
                        class="col-md-2"
                        :label="$gettext('Max Listeners')"
                    >
                        <input
                            v-model.number="form.max_listeners"
                            type="number"
                            min="0"
                            class="form-control"
                        >
                    </form-markup>
                    <form-markup
                        id="billing_bitrate"
                        class="col-md-2"
                        :label="$gettext('Max Bitrate')"
                    >
                        <input
                            v-model.number="form.max_bitrate"
                            type="number"
                            min="0"
                            class="form-control"
                        >
                    </form-markup>
                    <form-markup
                        id="billing_disk"
                        class="col-md-2"
                        :label="$gettext('Disk MB')"
                    >
                        <input
                            v-model.number="form.disk_quota_mb"
                            type="number"
                            min="0"
                            class="form-control"
                        >
                    </form-markup>
                    <form-markup
                        id="billing_traffic"
                        class="col-md-2"
                        :label="$gettext('Traffic MB')"
                    >
                        <input
                            v-model.number="form.max_traffic_mb"
                            type="number"
                            min="0"
                            class="form-control"
                        >
                    </form-markup>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary"
                    :disabled="busy"
                >
                    {{ $gettext('Run Billing Action') }}
                </button>
            </form>

            <div
                v-if="lastResponse"
                class="card-body border-top"
            >
                <h3 class="h6">
                    {{ $gettext('Last Response') }}
                </h3>
                <pre class="broadcast-mono small mb-0 text-break">{{ lastResponse }}</pre>
            </div>
        </card-page>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import CardPage from "~/components/Common/CardPage.vue";
import { useNotify } from "~/components/Common/Toasts/useNotify.ts";
import FormMarkup from "~/components/Form/FormMarkup.vue";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import { useAxios } from "~/vendor/axios.ts";
import { useTranslate } from "~/vendor/gettext";

const { getApiUrl } = useApiRouter();
const provisionUrl = getApiUrl("/admin/billing/provision");
const { axios } = useAxios();
const { notifySuccess, notifyError } = useNotify();
const { $gettext } = useTranslate();

const form = reactive({
    action: "create",
    station: "",
    name: "",
    customer_name: "",
    max_listeners: 100,
    max_bitrate: 128,
    disk_quota_mb: 10240,
    max_traffic_mb: 10240,
});

const busy = ref(false);
const lastResponse = ref("");

const needsStation = computed(() => form.action !== "create");
const showsPackageFields = computed(
    () => form.action === "create" || form.action === "modify_package",
);

const doSubmit = async () => {
    busy.value = true;
    try {
        const payload: Record<string, unknown> = {
            action: form.action,
        };

        if (needsStation.value) {
            payload.station = form.station;
        }
        if (form.action === "create") {
            payload.name = form.name;
        }
        if (showsPackageFields.value) {
            payload.customer_name = form.customer_name || null;
            payload.max_listeners = form.max_listeners;
            payload.max_bitrate = form.max_bitrate;
            payload.disk_quota_mb = form.disk_quota_mb;
            payload.max_traffic_mb = form.max_traffic_mb;
        }

        const { data } = await axios.post(provisionUrl.value, payload);
        lastResponse.value = JSON.stringify(data, null, 2);
        notifySuccess(data.message ?? $gettext("Billing action completed."));
    } catch (e: unknown) {
        lastResponse.value =
            e && typeof e === "object" && "message" in e
                ? String((e as { message: unknown }).message)
                : $gettext("Billing action failed.");
        notifyError($gettext("Billing action failed."));
    } finally {
        busy.value = false;
    }
};
</script>
