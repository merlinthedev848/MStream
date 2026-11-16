<template>
    <card-page
        :header-id="headerId"
        :title="title"
    >
        <div class="card-body">
            <loading :loading="isLoading">
                <div class="row g-3">
                    <div class="col-md-7">
                        <fieldset>
                            <legend>
                                {{ $gettext('Instructions') }}
                            </legend>

                            <p class="card-text">
                                {{ intro }}
                            </p>

                            <p class="card-text">
                                {{ $gettext('In order to install:') }}
                            </p>

                            <ul>
                                <li
                                    v-for="(step, idx) in steps"
                                    :key="idx"
                                >
                                    {{ step }}
                                </li>
                            </ul>
                        </fieldset>
                    </div>
                    <div class="col-md-5">
                        <fieldset class="mb-3">
                            <legend>
                                {{ $gettext('Current Installed Version') }}
                            </legend>

                            <p
                                v-if="record.version"
                                class="text-success card-text"
                            >
                                {{ langInstalledVersion }}
                            </p>
                            <p
                                v-else
                                class="text-danger card-text"
                            >
                                {{ notInstalledText }}
                            </p>
                        </fieldset>

                        <flow-upload
                            :target-url="apiUrl"
                            :valid-mime-types="['.tar.gz', '.tgz']"
                            @complete="relist"
                        />

                        <div
                            v-if="record.version"
                            class="block-buttons buttons mt-3"
                        >
                            <button
                                type="button"
                                class="btn btn-danger"
                                @click="doDelete"
                            >
                                {{ $gettext('Uninstall') }}
                            </button>
                        </div>
                    </div>
                </div>
            </loading>
        </div>
    </card-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import CardPage from "~/components/Common/CardPage.vue";
import { useDialog } from "~/components/Common/Dialogs/useDialog.ts";
import FlowUpload from "~/components/Common/FlowUpload.vue";
import Loading from "~/components/Common/Loading.vue";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import { useAxios } from "~/vendor/axios";
import { useTranslate } from "~/vendor/gettext";

const props = defineProps<{
    headerId: string;
    title: string;
    apiPath: string;
    intro: string;
    steps: string[];
    notInstalledText: string;
    uninstallTitle: string;
}>();

const { getApiUrl } = useApiRouter();
const apiUrl = getApiUrl(props.apiPath);

type Row = { version: string | null };

const isLoading = ref(true);
const record = ref<Row>({
    version: null,
});

const { $gettext } = useTranslate();

const langInstalledVersion = computed(() => {
    return $gettext('Version "%{version}" is currently installed.', {
        version: record.value.version ?? "N/A",
    });
});

const { axios } = useAxios();

const relist = async () => {
    isLoading.value = true;

    const { data } = await axios.get<Row>(apiUrl.value);
    record.value = data;
    isLoading.value = false;
};

onMounted(relist);

const { confirmDelete } = useDialog();

const doDelete = async () => {
    const { value } = await confirmDelete({
        title: props.uninstallTitle,
        confirmButtonText: $gettext("Uninstall"),
    });

    if (!value) {
        return;
    }

    await axios.delete(apiUrl.value);
    await relist();
};
</script>
