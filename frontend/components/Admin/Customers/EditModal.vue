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
        <div class="row g-3">
            <form-group-field
                id="edit_form_name"
                class="col-md-12"
                :field="r$.name"
                :label="$gettext('Name')"
            />

            <form-group-select
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
import { required } from "@regle/rules";
import { computed, toRef, useTemplateRef } from "vue";
import ModalForm from "~/components/Common/ModalForm.vue";
import FormGroupField from "~/components/Form/FormGroupField.vue";
import FormGroupSelect from "~/components/Form/FormGroupSelect.vue";
import mergeExisting from "~/functions/mergeExisting.ts";
import {
    BaseEditModalEmits,
    BaseEditModalProps,
    useBaseEditModal,
} from "~/functions/useBaseEditModal";
import { useTranslate } from "~/vendor/gettext";
import { useAppRegle } from "~/vendor/regle.ts";

const props = defineProps<
    BaseEditModalProps & {
        resellers: Record<string, string>;
    }
>();
const emit = defineEmits<BaseEditModalEmits>();

const $modal = useTemplateRef("$modal");

const { r$ } = useAppRegle(
    {
        name: "",
        reseller: "",
    },
    {
        name: { required },
        reseller: { required },
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
            }),
        });
    },
    async () => {
        const { valid, data } = await r$.$validate();
        return { valid, data };
    },
);

const resellerSelectOptions = computed(() =>
    Object.entries(props.resellers).map(([value, text]) => ({ value, text })),
);

const { $gettext } = useTranslate();

const langTitle = computed(() => {
    return isEditMode.value
        ? $gettext("Edit Customer")
        : $gettext("Add Customer");
});

defineExpose({
    create,
    edit,
    close,
});
</script>
