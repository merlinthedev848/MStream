<template>
    <span
        class="badge status-pill ms-2"
        :class="badgeClass"
    >
        <span
            class="status-dot"
            :class="enabled ? 'status-dot-running' : 'status-dot-stopped'"
        />
        <span class="status-text">{{ badgeText }}</span>
    </span>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useTranslate } from "~/vendor/gettext";

const props = defineProps<{
    enabled: boolean;
}>();

const badgeClass = computed(() => {
    return props.enabled ? "badge-running" : "badge-stopped";
});

const { $gettext } = useTranslate();

const badgeText = computed(() => {
    return props.enabled ? $gettext("Enabled") : $gettext("Disabled");
});
</script>
