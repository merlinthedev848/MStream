<template>
    <modal
        id="station_create_wizard_modal"
        ref="$modal"
        size="lg"
        :title="$gettext('Create Service')"
        :busy="busy"
        @hidden="clearContents"
    >
        <div class="admin-console px-3 pt-3">
            <div class="d-flex flex-wrap gap-2 mb-3 create-wizard-steps">
                <span
                    class="badge"
                    :class="step === 1 ? 'text-bg-primary' : 'text-bg-secondary'"
                >
                    1. {{ $gettext('Customer') }}
                </span>
                <span
                    class="badge"
                    :class="step === 2 ? 'text-bg-primary' : 'text-bg-secondary'"
                >
                    2. {{ $gettext('Service Type') }}
                </span>
                <span
                    class="badge"
                    :class="step === 3 ? 'text-bg-primary' : 'text-bg-secondary'"
                >
                    3. {{ $gettext('Configuration') }}
                </span>
            </div>

            <div
                v-if="error"
                class="alert alert-danger"
            >
                {{ error }}
            </div>

            <template v-if="step === 1">
                <p class="admin-section-lead">
                    {{
                        $gettext(
                            'Assign this service to a customer (create one under Customers first). Leave unassigned only for admin-owned services.',
                        )
                    }}
                </p>
                <div class="mb-3">
                    <label
                        class="form-label"
                        for="wizard_customer"
                    >
                        {{ $gettext('Customer') }}
                    </label>
                    <select
                        id="wizard_customer"
                        v-model="customer"
                        class="form-select"
                    >
                        <option value="">
                            {{ $gettext('Unassigned / Admin') }}
                        </option>
                        <option
                            v-for="opt in customerOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.text }}
                        </option>
                    </select>
                </div>
            </template>

            <template v-else-if="step === 2">
                <p class="admin-section-lead">
                    {{
                        $gettext(
                            'Please select a media server from the selection below.',
                        )
                    }}
                </p>
                <div class="row g-3">
                    <div
                        v-for="type in typeOptions"
                        :key="type.value"
                        class="col-md-4"
                    >
                        <button
                            type="button"
                            class="btn w-100 h-100 py-4 create-wizard-type text-start"
                            :class="
                                frontendType === type.value
                                    ? 'btn-primary'
                                    : 'btn-outline-secondary'
                            "
                            :aria-pressed="frontendType === type.value"
                            @click="frontendType = type.value"
                        >
                            <div class="fw-bold text-uppercase">
                                {{ type.label }}
                            </div>
                            <small class="d-block mt-1 opacity-75">
                                {{ type.help }}
                            </small>
                            <small
                                v-if="type.installHint"
                                class="d-block mt-2"
                                :class="
                                    frontendType === type.value
                                        ? 'opacity-100'
                                        : 'text-warning'
                                "
                            >
                                {{ type.installHint }}
                            </small>
                        </button>
                    </div>
                </div>
            </template>

            <template v-else>
                <p class="admin-section-lead">
                    {{
                        $gettext(
                            'Name the service. Port Base and stream passwords are assigned automatically. AutoDJ ships ready with a default playlist.',
                        )
                    }}
                </p>
                <div class="row g-3">
                    <div class="col-12">
                        <label
                            class="form-label"
                            for="wizard_name"
                        >
                            {{ $gettext('Publish Name') }}
                        </label>
                        <input
                            id="wizard_name"
                            v-model="name"
                            type="text"
                            class="form-control"
                            required
                            autocomplete="off"
                        >
                    </div>
                    <div class="col-md-6">
                        <label
                            class="form-label"
                            for="wizard_max_listeners"
                        >
                            {{ $gettext('Max Connections') }}
                        </label>
                        <input
                            id="wizard_max_listeners"
                            v-model.number="maxListeners"
                            type="range"
                            class="form-range"
                            min="1"
                            max="500"
                            step="1"
                        >
                        <div class="broadcast-mono">
                            {{ maxListeners }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label
                            class="form-label"
                            for="wizard_max_bitrate"
                        >
                            {{ $gettext('Max Bitrate (Kbps)') }}
                        </label>
                        <input
                            id="wizard_max_bitrate"
                            v-model.number="maxBitrate"
                            type="number"
                            class="form-control"
                            min="0"
                            step="32"
                        >
                    </div>
                    <div class="col-md-6">
                        <label
                            class="form-label"
                            for="wizard_max_traffic"
                        >
                            {{ $gettext('Traffic Quota (MB)') }}
                        </label>
                        <input
                            id="wizard_max_traffic"
                            v-model.number="maxTrafficMb"
                            type="number"
                            class="form-control"
                            min="0"
                            step="100"
                        >
                        <div class="form-text">
                            {{ $gettext('0 means unlimited.') }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label
                            class="form-label"
                            for="wizard_customer_label"
                        >
                            {{ $gettext('Customer Label') }}
                        </label>
                        <input
                            id="wizard_customer_label"
                            v-model="customerName"
                            type="text"
                            class="form-control"
                            autocomplete="off"
                        >
                    </div>
                </div>
            </template>
        </div>

        <template #modal-footer>
            <button
                class="btn btn-secondary"
                type="button"
                :disabled="busy"
                @click="hide"
            >
                {{ $gettext('Close') }}
            </button>
            <button
                v-if="step > 1"
                class="btn btn-outline-secondary"
                type="button"
                :disabled="busy"
                @click="step -= 1"
            >
                {{ $gettext('Back') }}
            </button>
            <button
                v-if="step < 3"
                class="btn btn-primary"
                type="button"
                :disabled="!canContinue"
                @click="step += 1"
            >
                {{ $gettext('Continue') }}
            </button>
            <button
                v-else
                class="btn btn-primary"
                type="button"
                :disabled="busy || !canCreate"
                @click="doCreate"
            >
                {{ $gettext('Create Service') }}
            </button>
        </template>
    </modal>
</template>

<script setup lang="ts">
import { computed, ref, useTemplateRef } from "vue";
import { useAdminStationsForm } from "~/components/Admin/Stations/Form/form.ts";
import Modal from "~/components/Common/Modal.vue";
import { useNotify } from "~/components/Common/Toasts/useNotify.ts";
import {
    ApiAdminVueStationsFormProps,
    FrontendAdapters,
    HasLinks,
    Station,
} from "~/entities/ApiInterfaces.ts";
import { useHasModal } from "~/functions/useHasModal.ts";
import { getErrorAsString, useAxios } from "~/vendor/axios";
import { useTranslate } from "~/vendor/gettext";

defineOptions({
    inheritAttrs: false,
});

interface CreateWizardProps extends ApiAdminVueStationsFormProps {
    createUrl: string;
}

const props = defineProps<CreateWizardProps>();

const emit = defineEmits<{
    (e: "relist"): void;
    (e: "created", station: Station & HasLinks): void;
}>();

const { $gettext } = useTranslate();
const { axios } = useAxios();
const { notifySuccess } = useNotify();
const formStore = useAdminStationsForm();

const $modal = useTemplateRef("$modal");
const { show, hide } = useHasModal($modal);

const step = ref(1);
const busy = ref(false);
const error = ref<string | null>(null);

const customer = ref("");
const frontendType = ref<FrontendAdapters>(FrontendAdapters.Icecast);
const name = ref("");
const maxListeners = ref(100);
const maxBitrate = ref(256);
const maxTrafficMb = ref(0);
const customerName = ref("");

const customerOptions = computed(() =>
    Object.entries(props.customers ?? {}).map(([value, text]) => ({
        value,
        text,
    })),
);

/**
 * MediaCP-comparable radio types only (no TV / Wowza / Flussonic / Nginx RTMP).
 * Icecast ships with the product; SHOUTcast DNAS and RSAS are proprietary but
 * fully wired once installed under Broadcast Software — always offer the cards
 * so create is not Icecast-only. MStream Icecast is already the KH lineage;
 * there is no separate "Icecast KH" adapter to invent.
 */
const typeOptions = computed(() => [
    {
        value: FrontendAdapters.Icecast,
        label: $gettext("Icecast 2"),
        help: $gettext("Audio streaming engine supporting MP3, AAC, and OGG."),
        installHint: null as string | null,
    },
    {
        value: FrontendAdapters.Shoutcast,
        label: $gettext("SHOUTcast 2"),
        help: $gettext(
            "Audio streaming service supporting MP3 and AAC with native SSL support.",
        ),
        installHint: props.isShoutcastInstalled
            ? null
            : $gettext(
                  "Shoutcast 2 DNAS is not installed yet. Create the service now, then install DNAS under Broadcast Software before going on air.",
              ),
    },
    {
        value: FrontendAdapters.Rsas,
        label: $gettext("RSAS"),
        help: $gettext(
            "Rocket Streaming Audio Server — Icecast-compatible alternative frontend.",
        ),
        installHint: props.isRsasInstalled
            ? null
            : $gettext(
                  "RSAS is not installed yet. Create the service now, then install RSAS under Broadcast Software before going on air.",
              ),
    },
]);

const canContinue = computed(() => {
    if (step.value === 2) {
        return !!frontendType.value;
    }
    return true;
});

const canCreate = computed(() => name.value.trim().length >= 2);

const clearContents = () => {
    step.value = 1;
    busy.value = false;
    error.value = null;
    customer.value = "";
    frontendType.value = FrontendAdapters.Icecast;
    name.value = "";
    maxListeners.value = 100;
    maxBitrate.value = 256;
    maxTrafficMb.value = 0;
    customerName.value = "";
    formStore.$reset();
};

const create = () => {
    clearContents();
    show();
};

const doCreate = async () => {
    if (!canCreate.value || !props.createUrl) {
        return;
    }

    busy.value = true;
    error.value = null;

    formStore.$reset();
    const base = JSON.parse(
        JSON.stringify(formStore.form),
    ) as typeof formStore.form;
    const payload = {
        ...base,
        name: name.value.trim(),
        frontend_type: frontendType.value,
        customer: customer.value || "",
        customer_name: customerName.value.trim(),
        max_bitrate: maxBitrate.value,
        max_traffic_mb: maxTrafficMb.value,
        frontend_config: {
            ...base.frontend_config,
            max_listeners: maxListeners.value,
            port: null,
            source_pw: "",
            admin_pw: "",
            relay_pw: "",
            streamer_pw: "",
        },
        backend_config: {
            ...base.backend_config,
            dj_port: null,
            telnet_port: null,
        },
    };

    try {
        const { data } = await axios.post<Station & HasLinks>(
            props.createUrl,
            payload,
        );
        notifySuccess($gettext("Service created. Opening Overview…"));
        emit("relist");
        emit("created", data);
        hide();
    } catch (e) {
        error.value = getErrorAsString(e);
    } finally {
        busy.value = false;
    }
};

defineExpose({
    create,
});
</script>
