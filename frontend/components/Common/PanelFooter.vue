<template>
    <footer id="footer">
        {{ $gettext('Powered by') }}
        <a
            href="https://github.com/merlinthedev848/MStream"
            target="_blank"
        >MStream</a>
        &bull;
        <span v-html="version"/>
        &bull;
        <span v-html="platform"/>
    </footer>
</template>

<script setup lang="ts">
import { useMStreamDashboardGlobals } from "~/vendor/mstream.ts";

const { version, platform } = useMStreamDashboardGlobals();
</script>
