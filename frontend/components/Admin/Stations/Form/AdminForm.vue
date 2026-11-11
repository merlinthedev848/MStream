<template>
    <tab
        :label="$gettext('Admin Limits')"
        :item-header-class="tabClass"
    >
        <div class="row g-3 mb-3">
            <form-group-checkbox
                id="edit_form_is_enabled"
                class="col-md-6"
                :field="r$.is_enabled"
                :label="$gettext('Enable Broadcasting')"
                :description="$gettext('If disabled, the station will not broadcast or shuffle its AutoDJ.')"
            />

            <form-group-field
                id="edit_form_radio_base_dir"
                class="col-md-6"
                :field="r$.radio_base_dir"
                advanced
                :label="$gettext('Station Files Directory')"
                :description="$gettext('Parent directory for playlist and configuration files. Leave blank to use the default.')"
            />
        </div>

        <div class="row g-3 mb-3">
            <form-group-field
                id="edit_form_max_bitrate"
                class="col-md-4"
                :field="r$.max_bitrate"
                :label="$gettext('Max Bitrate (Kbps)')"
                :description="$gettext('Highest bitrate allowed for this station. 0 means unlimited.')"
                input-number
            />

            <form-group-field
                id="edit_form_max_mounts"
                class="col-md-4"
                :field="r$.max_mounts"
                :label="$gettext('Max Listener Streams')"
                :description="$gettext('Maximum mount points allowed. 0 means unlimited.')"
                input-number
            />

            <form-group-field
                id="edit_form_max_hls_streams"
                class="col-md-4"
                :field="r$.max_hls_streams"
                :label="$gettext('Max HLS Streams')"
                :description="$gettext('Maximum HLS streams allowed. 0 means unlimited.')"
                input-number
            />
        </div>

        <div class="row g-3 mb-3">
            <form-group-field
                id="edit_form_customer_name"
                class="col-md-6"
                :field="r$.customer_name"
                :label="$gettext('Customer')"
                :description="$gettext('Hosting customer label shown in Media Services.')"
            />

            <form-group-field
                id="edit_form_max_traffic_mb"
                class="col-md-6"
                :field="r$.max_traffic_mb"
                :label="$gettext('Traffic Quota (MB)')"
                :description="$gettext('Monthly traffic package limit. 0 means unlimited. Metering is best-effort.')"
                input-number
            />
        </div>

        <loading :loading="storageLocationsLoading">
            <div class="row g-3">
                <form-group-select
                    id="edit_form_media_storage_location"
                    class="col-md-12"
                    :field="r$.media_storage_location"
                    :options="storageLocationOptions.media_storage_location"
                    :label="$gettext('Music Library Storage')"
                />

                <form-group-select
                    id="edit_form_recordings_storage_location"
                    class="col-md-12"
                    :field="r$.recordings_storage_location"
                    :options="storageLocationOptions.recordings_storage_location"
                    :label="$gettext('Live Recordings Storage')"
                />

                <form-group-select
                    id="edit_form_podcasts_storage_location"
                    class="col-md-12"
                    :field="r$.podcasts_storage_location"
                    :options="storageLocationOptions.podcasts_storage_location"
                    :label="$gettext('Podcasts Storage')"
                />
            </div>
        </loading>
    </tab>
</template>

<script setup lang="ts">
import { storeToRefs } from "pinia";
import { computed, onMounted, reactive, ref } from "vue";
import { useAdminStationsForm } from "~/components/Admin/Stations/Form/form.ts";
import Loading from "~/components/Common/Loading.vue";
import Tab from "~/components/Common/Tab.vue";
import FormGroupCheckbox from "~/components/Form/FormGroupCheckbox.vue";
import FormGroupField from "~/components/Form/FormGroupField.vue";
import FormGroupSelect from "~/components/Form/FormGroupSelect.vue";
import { ApiFormSimpleOptions } from "~/entities/ApiInterfaces.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import { useFormTabClass } from "~/functions/useFormTabClass.ts";
import { useAxios } from "~/vendor/axios";
import { useTranslate } from "~/vendor/gettext.ts";

const props = defineProps<{
    isEditMode: boolean;
}>();

const { getApiUrl } = useApiRouter();
const storageLocationApiUrl = getApiUrl("/admin/stations/storage-locations");

const { r$ } = storeToRefs(useAdminStationsForm());

const tabClass = useFormTabClass(computed(() => r$.value.$groups.adminTab));

interface StorageLocationOptions {
    media_storage_location: ApiFormSimpleOptions;
    recordings_storage_location: ApiFormSimpleOptions;
    podcasts_storage_location: ApiFormSimpleOptions;
}

const storageLocationsLoading = ref<boolean>(true);
const storageLocationOptions = reactive<StorageLocationOptions>({
    media_storage_location: [],
    recordings_storage_location: [],
    podcasts_storage_location: [],
});

const { $gettext } = useTranslate();

const langNewStorageLocation = $gettext(
    "Create a new storage location based on the base directory.",
);

const filterLocations = (group: ApiFormSimpleOptions): ApiFormSimpleOptions => {
    if (props.isEditMode) {
        return group;
    }

    const newGroup = group.slice();
    newGroup.push({
        value: "",
        text: langNewStorageLocation,
    });
    return newGroup;
};

const { axios } = useAxios();

const loadLocations = async () => {
    try {
        const { data } = await axios.get<StorageLocationOptions>(
            storageLocationApiUrl.value,
        );

        storageLocationOptions.media_storage_location = filterLocations(
            data.media_storage_location,
        );
        storageLocationOptions.recordings_storage_location = filterLocations(
            data.recordings_storage_location,
        );
        storageLocationOptions.podcasts_storage_location = filterLocations(
            data.podcasts_storage_location,
        );
    } finally {
        storageLocationsLoading.value = false;
    }
};

onMounted(loadLocations);
</script>
