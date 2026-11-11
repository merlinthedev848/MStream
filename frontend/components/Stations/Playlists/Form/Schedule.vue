<template>
    <tab :label="$gettext('Schedule')">
        <form-markup
            v-if="scheduleItems.length === 0"
            id="no_scheduled_entries"
        >
            <template #label>
                {{ $gettext('Not Scheduled') }}
            </template>
            <p>
                {{ emptyScheduleDescription }}
            </p>
        </form-markup>

        <form-markup
            v-else
            id="scheduled_entries_behavior"
        >
            <template #label>
                {{ $gettext('How Scheduling Works') }}
            </template>
            <p>
                {{ scheduledBehaviorDescription }}
            </p>
            <p v-if="isGeneralRotation">
                {{
                    $gettext('To mix this playlist with other General Rotation playlists by weight during its windows (instead of replacing them), enable "Include in general rotation while scheduled (non-exclusive)" on the Advanced tab.')
                }}
            </p>
        </form-markup>

        <playlists-form-schedule-row
            v-for="(_, index) in scheduleItems"
            :key="index"
            v-model:row="scheduleItems[index]"
            :index="index"
            @remove="remove(index)"
        />

        <div class="buttons">
            <button
                type="button"
                class="btn btn-sm btn-primary"
                @click="add"
            >
                <icon-ic-add/>
                <span>
                    {{ $gettext('Add Schedule Item') }}
                </span>
            </button>
        </div>
    </tab>
</template>

<script setup lang="ts">
import { storeToRefs } from "pinia";
import { computed } from "vue";
import Tab from "~/components/Common/Tab.vue";
import FormMarkup from "~/components/Form/FormMarkup.vue";
import { useStationsPlaylistsForm } from "~/components/Stations/Playlists/Form/form.ts";
import PlaylistsFormScheduleRow from "~/components/Stations/Playlists/Form/ScheduleRow.vue";
import { PlaylistTypes } from "~/entities/ApiInterfaces";
import { useTranslate } from "~/vendor/gettext";
import IconIcAdd from "~icons/ic/baseline-add";

const scheduleItems = defineModel<Array<any>>("scheduleItems", {
    default: () => [],
});

const { form } = storeToRefs(useStationsPlaylistsForm());
const { $gettext } = useTranslate();

const isGeneralRotation = computed(
    () => form.value.type === PlaylistTypes.Standard,
);

const emptyScheduleDescription = computed(() => {
    if (form.value.type === PlaylistTypes.OncePerXSongs) {
        return $gettext(
            "This playlist has no schedule windows. The AutoDJ will consider it at any time of day according to its once-per-x-songs rule.",
        );
    }
    if (form.value.type === PlaylistTypes.OncePerXMinutes) {
        return $gettext(
            "This playlist has no schedule windows. The AutoDJ will consider it at any time of day according to its once-per-x-minutes rule.",
        );
    }
    if (form.value.type === PlaylistTypes.OncePerHour) {
        return $gettext(
            "This playlist has no schedule windows. The AutoDJ will consider it every hour at the configured minute.",
        );
    }
    if (form.value.type === PlaylistTypes.Advanced) {
        return $gettext(
            "This playlist has no schedule windows. Advanced playlists are not scheduled by the AutoDJ; use custom Liquidsoap configuration instead.",
        );
    }

    return $gettext(
        "This playlist has no schedule windows. As General Rotation, the AutoDJ may select it at any time of day, weighted against other unscheduled General Rotation playlists. Add a schedule window below to limit when it is eligible.",
    );
});

const scheduledBehaviorDescription = computed(() => {
    if (isGeneralRotation.value) {
        return $gettext(
            "While a schedule window is active, this General Rotation playlist is eligible to play. By default it takes exclusive priority over unscheduled General Rotation playlists (they will not play until no exclusive scheduled playlist of this type is available).",
        );
    }

    return $gettext(
        "While a schedule window is active, this playlist is eligible according to its type rules and takes priority over unscheduled playlists of the same type. Outside its windows, the AutoDJ will not select it.",
    );
});

const add = () => {
    scheduleItems.value.push({
        start_time: null,
        end_time: null,
        start_date: null,
        end_date: null,
        days: [],
        loop_once: false,
        prevent_requests: false,
        reset_queue_at_start: false,
        reset_queue_recursive: false,
    });
};

const remove = (index: number) => {
    scheduleItems.value.splice(index, 1);
};
</script>
