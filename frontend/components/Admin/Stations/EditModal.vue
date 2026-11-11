<template>
    <modal
        id="station_edit_modal"
        ref="$modal"
        size="lg"
        :title="langTitle"
        :busy="false"
        @shown="resetForm"
        @hidden="clearContents"
    >
        <div
            v-if="!isEditMode"
            class="admin-console px-3 pt-3"
        >
            <div class="admin-create-guide">
                <div class="admin-create-guide-step">
                    <span class="admin-create-guide-index broadcast-mono">1</span>
                    <div>
                        <div class="admin-create-guide-title">
                            {{ $gettext('Name the station') }}
                        </div>
                        <p class="admin-create-guide-text">
                            {{
                                $gettext(
                                    'Start with a display name. Ports, media folders, and a default playlist are assigned automatically.',
                                )
                            }}
                        </p>
                    </div>
                </div>
                <div class="admin-create-guide-step">
                    <span class="admin-create-guide-index broadcast-mono">2</span>
                    <div>
                        <div class="admin-create-guide-title">
                            {{ $gettext('AutoDJ is ready') }}
                        </div>
                        <p class="admin-create-guide-text">
                            {{
                                $gettext(
                                    'New stations ship with Liquidsoap AutoDJ and a default playlist. Operators upload tracks and go on the air.',
                                )
                            }}
                        </p>
                    </div>
                </div>
                <div class="admin-create-guide-step">
                    <span class="admin-create-guide-index broadcast-mono">3</span>
                    <div>
                        <div class="admin-create-guide-title">
                            {{ $gettext('Optional fine-tuning') }}
                        </div>
                        <p class="admin-create-guide-text">
                            {{
                                $gettext(
                                    'Broadcasting, HLS, requests, and DJ settings are available in the tabs below when you need them.',
                                )
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <admin-stations-form
            v-bind="props"
            ref="$form"
            is-modal
            :create-url="createUrl"
            :edit-url="editUrl"
            :is-edit-mode="isEditMode"
            @error="hide"
            @submitted="onSubmit"
            @valid-update="onValidUpdate"
        >
            <template #submitButton>
                <invisible-submit-button />
            </template>
        </admin-stations-form>

        <template #modal-footer>
            <button
                class="btn btn-secondary"
                type="button"
                @click="hide"
            >
                {{ $gettext('Close') }}
            </button>
            <button
                class="btn"
                :class="(disableSaveButton) ? 'btn-danger' : 'btn-primary'"
                type="submit"
                @click="doSubmit"
            >
                {{ isEditMode ? $gettext('Save Changes') : $gettext('Create Station') }}
            </button>
        </template>
    </modal>
</template>

<script setup lang="ts">
import { computed, ref, useTemplateRef } from "vue";
import AdminStationsForm from "~/components/Admin/Stations/StationForm.vue";
import InvisibleSubmitButton from "~/components/Common/InvisibleSubmitButton.vue";
import Modal from "~/components/Common/Modal.vue";
import { ApiAdminVueStationsFormProps } from "~/entities/ApiInterfaces.ts";
import { HasRelistEmit } from "~/functions/useBaseEditModal.ts";
import { useHasModal } from "~/functions/useHasModal.ts";
import { useTranslate } from "~/vendor/gettext";

defineOptions({
    inheritAttrs: false,
});

interface StationEditModalProps extends ApiAdminVueStationsFormProps {
    createUrl: string;
}

const props = defineProps<StationEditModalProps>();

const emit = defineEmits<HasRelistEmit>();

const editUrl = ref<string | null>(null);
const disableSaveButton = ref(true);

const isEditMode = computed(() => {
    return editUrl.value !== null;
});

const { $gettext } = useTranslate();

const langTitle = computed(() => {
    return isEditMode.value
        ? $gettext("Edit Station")
        : $gettext("Create Station");
});

const $modal = useTemplateRef("$modal");
const { show, hide } = useHasModal($modal);

const onValidUpdate = (newValue: boolean) => {
    disableSaveButton.value = !newValue;
};

const create = () => {
    editUrl.value = null;
    show();
};

const edit = (recordUrl: string) => {
    editUrl.value = recordUrl;
    show();
};

const $form = useTemplateRef("$form");

const resetForm = () => {
    $form.value?.reset();
};

const onSubmit = () => {
    emit("relist");
    hide();
};

const doSubmit = () => {
    $form.value?.submit();
};

const clearContents = () => {
    editUrl.value = null;
};

defineExpose({
    create,
    edit,
});
</script>
