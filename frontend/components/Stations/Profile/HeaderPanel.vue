<template>
    <div class="outside-card-header mb-3">
        <h1 class="display-6 fw-bold text-white mb-1">
            {{ stationData.name }}
        </h1>
        <h5 class="fw-normal text-secondary mb-0" v-if="stationData.description">
            {{ stationData.description }}
        </h5>
    </div>
</template>

<script setup lang="ts">
import { useStationData } from "~/functions/useStationQuery.ts";

const stationData = useStationData();
</script>
