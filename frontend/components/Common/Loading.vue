<template>
    <div
        v-bind="$attrs"
        class="align-items-center justify-content-center p-4 text-muted"
        :class="(loading) ? 'd-flex' : 'd-none'"
    >
        <div
            class="spinner-border text-primary me-3"
            role="status"
            aria-hidden="true"
        />
        <span>{{ $gettext('Loading...') }}</span>
    </div>
    <div
        v-if="!lazy || !loading"
        v-show="!loading"
        style="display: contents"
    >
        <slot name="default" />
    </div>
</template>

<script setup lang="ts">
import { useTranslate } from "~/vendor/gettext";

withDefaults(
    defineProps<{
        loading?: boolean;
        lazy?: boolean;
    }>(),
    {
        loading: false,
        lazy: false,
    },
);

const { $gettext } = useTranslate();
</script>
