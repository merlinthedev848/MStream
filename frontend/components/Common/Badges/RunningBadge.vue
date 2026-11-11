<template>
    <span
        class="badge status-pill ms-2"
        :class="badgeClass"
    >
        <span
            class="status-dot"
            :class="running ? 'status-dot-running' : 'status-dot-stopped'"
        />
        <span class="status-text">{{ badgeText }}</span>
    </span>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useTranslate } from "~/vendor/gettext";

const props = defineProps<{
    running: boolean;
}>();

const badgeClass = computed(() => {
    return props.running ? "badge-running" : "badge-stopped";
});

const { $gettext } = useTranslate();

const badgeText = computed(() => {
    return props.running ? $gettext("Running") : $gettext("Not Running");
});
</script>
