<template>
    <setup-step :step="2"/>

    <section
        id="content"
        class="full-height-wrapper"
        role="main"
    >
        <section
            class="card"
            role="region"
            aria-labelledby="hdr_new_station"
        >
            <div class="card-header text-bg-primary d-flex align-items-center">
                <h3
                    id="hdr_new_station"
                    class="card-title flex-fill"
                >
                    {{ $gettext('Create Your First Station') }}
                </h3>
                <div class="flex-shrink-0">
                    <a
                        :href="continueUrl"
                        class="btn btn-outline-light btn-sm"
                    >
                        {{ $gettext('Skip & Use Default Station') }}
                    </a>
                </div>
            </div>

            <info-card>
                {{
                    $gettext('A default AutoDJ-ready station is already available. Create a custom station below, or skip and continue with the default — uploads will play automatically either way.')
                }}
            </info-card>

            <div class="card-body">
                <admin-stations-form
                    v-bind="formProps"
                    :is-edit-mode="false"
                    :create-url="createUrl"
                    @submitted="onSubmitted"
                >
                    <template #submitButtonText>
                        {{ $gettext('Create and Continue') }}
                    </template>
                </admin-stations-form>
            </div>
        </section>
    </section>
</template>

<script setup lang="ts">
import AdminStationsForm from "~/components/Admin/Stations/StationForm.vue";
import InfoCard from "~/components/Common/InfoCard.vue";
import SetupStep from "~/components/Setup/SetupStep.vue";
import { ApiAdminVueStationsFormProps } from "~/entities/ApiInterfaces.ts";

interface SetupStationProps {
    formProps: ApiAdminVueStationsFormProps;
    createUrl: string;
    continueUrl: string;
}

const props = defineProps<SetupStationProps>();

const onSubmitted = () => {
    window.location.href = props.continueUrl;
};
</script>
