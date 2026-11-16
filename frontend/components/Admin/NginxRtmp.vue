<template>
    <binary-install-page
        header-id="hdr_install_nginx_rtmp"
        :title="$gettext('Install Nginx RTMP')"
        api-path="/admin/nginx_rtmp"
        :intro="$gettext('Nginx RTMP is open-source. MStream can use a system nginx with the RTMP module, or you can upload a prebuilt nginx-rtmp binary tree here for per-station video services.')"
        :steps="steps"
        :not-installed-text="$gettext('No Nginx RTMP binary was detected on this installation.')"
        :uninstall-title="$gettext('Remove Nginx RTMP binary tree?')"
    />
</template>

<script setup lang="ts">
import { computed } from "vue";
import BinaryInstallPage from "~/components/Admin/BinaryInstallPage.vue";
import { useTranslate } from "~/vendor/gettext";

const { $gettext } = useTranslate();

const steps = computed(() => [
    $gettext(
        "Build or download an nginx binary compiled with the nginx-rtmp-module.",
    ),
    $gettext(
        "Upload a .tar.gz that extracts to nginx or sbin/nginx in this install directory.",
    ),
]);
</script>
