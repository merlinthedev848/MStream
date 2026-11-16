<template>
    <modal-form
        ref="$modal"
        :loading="loading"
        :title="langTitle"
        :error="error"
        :disable-save-button="r$.$invalid"
        @submit="doSubmit"
        @hidden="clearContents"
    >
        <div class="row g-3 admin-console">
            <form-group-field
                id="edit_form_name"
                class="col-md-12"
                :field="r$.name"
                :label="$gettext('Customer Name')"
            />

            <template v-if="!isEditMode">
                <p class="admin-section-lead col-12 mb-0">
                    {{
                        $gettext(
                            'Create a login so this customer can open their service console. Roles stay under System → Roles if you need advanced ACL.',
                        )
                    }}
                </p>
                <form-group-field
                    id="edit_form_email"
                    class="col-md-6"
                    :field="r$.email"
                    input-type="email"
                    :label="$gettext('Login Email')"
                />
                <form-group-field
                    id="edit_form_password"
                    class="col-md-6"
                    :field="r$.password"
                    input-type="password"
                    :label="$gettext('Password')"
                    :description="
                        $gettext('Leave blank to auto-generate a temporary password.')
                    "
                />
            </template>

            <template v-else>
                <form-group-field
                    id="edit_form_email"
                    class="col-md-6"
                    :field="r$.email"
                    input-type="email"
                    :label="$gettext('Login Email')"
                    :description="
                        $gettext(
                            'Set or change the owner login. Leave password blank to keep the current one.',
                        )
                    "
                />
                <form-group-field
                    id="edit_form_password"
                    class="col-md-6"
                    :field="r$.password"
                    input-type="password"
                    :label="$gettext('New Password')"
                />
                <form-group-select
                    id="edit_form_owner_user"
                    class="col-md-12"
                    :field="r$.owner_user"
                    :options="userSelectOptions"
                    :label="$gettext('Owner Account')"
                    :description="
                        $gettext(
                            'Optional: link an existing panel account instead of (or in addition to) email above.',
                        )
                    "
                />
            </template>

            <form-group-select
                v-if="showResellerSelect"
                id="edit_form_reseller"
                class="col-md-12"
                :field="r$.reseller"
                :options="resellerSelectOptions"
                :label="$gettext('Reseller')"
            />
        </div>
    </modal-form>
</template>

<script setup lang="ts">
import { required, requiredIf } from "@regle/rules";
import { computed, ref, toRef, useTemplateRef, watch } from "vue";
import ModalForm from "~/components/Common/ModalForm.vue";
import FormGroupField from "~/components/Form/FormGroupField.vue";
import FormGroupSelect from "~/components/Form/FormGroupSelect.vue";
import mergeExisting from "~/functions/mergeExisting.ts";
import {
    BaseEditModalEmits,
    BaseEditModalProps,
    ModalFormTemplateRef,
    useBaseEditModal,
} from "~/functions/useBaseEditModal";
import { useTranslate } from "~/vendor/gettext";
import { useAppRegle } from "~/vendor/regle.ts";

const props = defineProps<
    BaseEditModalProps & {
        resellers: Record<string, string>;
        users: Record<string, string>;
    }
>();
const emit = defineEmits<BaseEditModalEmits>();

const $modal = useTemplateRef<ModalFormTemplateRef | null>("$modal");

// Synced from useBaseEditModal so validators can read edit mode.
const editMode = ref(false);

const { r$ } = useAppRegle(
    {
        name: "",
        reseller: "",
        owner_user: "",
        email: "",
        password: "",
    },
    {
        name: { required },
        email: {
            required: requiredIf(() => !editMode.value),
        },
    },
    {},
);

const {
    loading,
    error,
    isEditMode,
    clearContents,
    create,
    edit,
    doSubmit,
    close,
} = useBaseEditModal(
    toRef(props, "createUrl"),
    emit,
    $modal,
    () => {
        r$.$reset({
            toOriginalState: true,
        });
    },
    (data) => {
        r$.$reset({
            toState: mergeExisting(r$.$value, {
                ...data,
                reseller: data.reseller != null ? String(data.reseller) : "",
                owner_user:
                    data.owner_user != null ? String(data.owner_user) : "",
                email: String(data.owner_email ?? data.email ?? ""),
                password: "",
            }),
        });
    },
    async () => {
        const { valid, data } = await r$.$validate();
        const payload: Record<string, unknown> = {
            name: data.name,
            owner_user: data.owner_user ? data.owner_user : null,
        };
        if (data.reseller) {
            payload.reseller = data.reseller;
        }
        if (data.email) {
            payload.email = data.email;
        }
        if (data.password) {
            payload.password = data.password;
        }
        return {
            valid,
            data: payload,
        };
    },
);

watch(isEditMode, (newValue) => {
    editMode.value = newValue;
});

const resellerSelectOptions = computed(() =>
    Object.entries(props.resellers).map(([value, text]) => ({ value, text })),
);

const showResellerSelect = computed(
    () => Object.keys(props.resellers).length > 1,
);

const userSelectOptions = computed(() => [
    { value: "", text: "—" },
    ...Object.entries(props.users).map(([value, text]) => ({ value, text })),
]);

const { $gettext } = useTranslate();

const langTitle = computed(() => {
    return isEditMode.value
        ? $gettext("Manage Customer")
        : $gettext("New Customer");
});

defineExpose({
    create,
    edit,
    close,
});
</script>
