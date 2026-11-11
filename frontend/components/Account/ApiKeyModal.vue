<template>
    <modal
        id="api_keys_modal"
        ref="$modal"
        size="lg"
        centered
        :title="$gettext('Add API Key')"
        no-enforce-focus
        @shown="onShown"
        @hidden="clearContents"
    >
        <template #default>
            <div
                v-show="error != null"
                class="alert alert-danger"
            >
                {{ error }}
            </div>

            <form
                v-if="newKey === null"
                class="form vue-form"
                @submit.prevent="doSubmit"
            >
                <form-group-field
                    id="form_comments"
                    ref="$field"
                    :field="r$.comment"
                    autofocus
                    :label="$gettext('API Key Description/Comments')"
                />

                <div class="form-group mb-3">
                    <div class="form-check">
                        <input
                            id="form_restrict_scopes"
                            v-model="restrictScopes"
                            class="form-check-input"
                            type="checkbox"
                        >
                        <label
                            class="form-check-label"
                            for="form_restrict_scopes"
                        >
                            {{ $gettext('Limit this key to selected permissions') }}
                        </label>
                    </div>
                    <small class="form-text text-body-secondary">
                        {{
                            $gettext('Leave unchecked to inherit all permissions from your account. Selected scopes cannot exceed your own permissions.')
                        }}
                    </small>
                </div>

                <template v-if="restrictScopes">
                    <form-group-multi-check
                        v-if="globalOptions.length > 0"
                        id="form_api_key_global_permissions"
                        v-model="selectedGlobal"
                        :options="globalOptions"
                        stacked
                        :label="$gettext('Global Permissions')"
                    />

                    <div
                        v-for="stationScope in stationScopeOptions"
                        :key="stationScope.id"
                        class="mb-3"
                    >
                        <form-group-multi-check
                            :id="'form_api_key_station_' + stationScope.id"
                            v-model="selectedStation[stationScope.id]"
                            :options="stationScope.options"
                            stacked
                            :label="$gettext('Station %{id} Permissions', { id: String(stationScope.id) })"
                        />
                    </div>
                </template>

                <invisible-submit-button />
            </form>

            <div v-else>
                <account-api-key-new-key :new-key="newKey" />
            </div>
        </template>

        <template #modal-footer="slotProps">
            <slot
                name="modal-footer"
                v-bind="slotProps"
            >
                <button
                    type="button"
                    class="btn btn-secondary"
                    @click="hide"
                >
                    {{ $gettext('Close') }}
                </button>
                <button
                    v-if="newKey === null"
                    type="submit"
                    class="btn"
                    :class="(r$.$invalid) ? 'btn-danger' : 'btn-primary'"
                    @click="doSubmit"
                >
                    {{ $gettext('Create New Key') }}
                </button>
            </slot>
        </template>
    </modal>
</template>

<script setup lang="ts">
import { required } from "@regle/rules";
import { computed, nextTick, reactive, ref, useTemplateRef } from "vue";
import AccountApiKeyNewKey from "~/components/Account/ApiKeyNewKey.vue";
import InvisibleSubmitButton from "~/components/Common/InvisibleSubmitButton.vue";
import Modal from "~/components/Common/Modal.vue";
import FormGroupField from "~/components/Form/FormGroupField.vue";
import FormGroupMultiCheck from "~/components/Form/FormGroupMultiCheck.vue";
import {
    ApiAccountNewApiKey,
    GlobalPermissions,
    StationPermissions,
} from "~/entities/ApiInterfaces.ts";
import { HasRelistEmit } from "~/functions/useBaseEditModal.ts";
import { useHasModal } from "~/functions/useHasModal.ts";
import { getErrorAsString, useAxios } from "~/vendor/axios";
import { useMStreamUser } from "~/vendor/mstream.ts";
import { useAppRegle } from "~/vendor/regle.ts";

const props = defineProps<{
    createUrl: string;
}>();

const emit = defineEmits<HasRelistEmit>();

const error = ref<string | null>(null);
const newKey = ref<string | null>(null);
const restrictScopes = ref(false);
const selectedGlobal = ref<string[]>([]);
const selectedStation = reactive<Record<number, string[]>>({});

const user = useMStreamUser();

const globalOptions = computed(() =>
    (user.permissions.global ?? []).map((permission) => ({
        value: permission,
        text: permission,
    })),
);

const stationScopeOptions = computed(() =>
    (user.permissions.station ?? []).map((row) => ({
        id: row.id,
        options: (row.permissions ?? []).map((permission) => ({
            value: permission,
            text: permission,
        })),
    })),
);

const { r$ } = useAppRegle(
    {
        comment: "",
    },
    {
        comment: { required },
    },
    {},
);

const initStationSelections = () => {
    for (const key of Object.keys(selectedStation)) {
        delete selectedStation[Number(key)];
    }
    for (const station of stationScopeOptions.value) {
        selectedStation[station.id] = [];
    }
};

const clearContents = () => {
    r$.$reset({
        toOriginalState: true,
    });

    error.value = null;
    newKey.value = null;
    restrictScopes.value = false;
    selectedGlobal.value = [];
    initStationSelections();
};

const $modal = useTemplateRef("$modal");
const { show, hide } = useHasModal($modal);

const create = () => {
    clearContents();
    show();
};

const $field = useTemplateRef("$field");

const onShown = () => {
    void nextTick().then(() => {
        $field.value?.focus();
    });
};

const { axios } = useAxios();

const doSubmit = async () => {
    const { valid, data: postData } = await r$.$validate();
    if (!valid) {
        return;
    }

    error.value = null;

    const payload: {
        comment: string;
        permissions?: {
            global: GlobalPermissions[];
            station: { id: number; permissions: StationPermissions[] }[];
        };
    } = {
        comment: postData.comment,
    };

    if (restrictScopes.value) {
        payload.permissions = {
            global: selectedGlobal.value as GlobalPermissions[],
            station: Object.entries(selectedStation)
                .filter(([, permissions]) => permissions.length > 0)
                .map(([id, permissions]) => ({
                    id: Number(id),
                    permissions: permissions as StationPermissions[],
                })),
        };
    }

    try {
        const { data } = await axios.post<ApiAccountNewApiKey>(
            props.createUrl,
            payload,
        );

        newKey.value = data.key;
    } catch (e) {
        error.value = getErrorAsString(e);
    }

    emit("relist");
};

defineExpose({
    create,
});
</script>
