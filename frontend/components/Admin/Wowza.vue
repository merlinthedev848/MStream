<template>
    <binary-install-page
        header-id="hdr_install_wowza"
        :title="$gettext('Install Wowza Streaming Engine (BYO license)')"
        api-path="/admin/wowza"
        :intro="$gettext('Wowza Streaming Engine is proprietary. MStream never vendors Wowza installers or licenses. Place your licensed WSE tree here for Create Service video stations.')"
        :steps="steps"
        :not-installed-text="$gettext('Wowza Streaming Engine is not currently installed on this installation.')"
        :uninstall-title="$gettext('Remove Wowza installation?')"
    />
</template>

<script setup lang="ts">
import { computed } from "vue";
import BinaryInstallPage from "~/components/Admin/BinaryInstallPage.vue";
import { useTranslate } from "~/vendor/gettext";

const { $gettext } = useTranslate();

const steps = computed(() => [
    $gettext("Obtain your licensed Wowza Streaming Engine install from Wowza."),
    $gettext(
        "Upload a .tar.gz of the WSE tree including bin/startup.sh (or equivalent).",
    ),
]);
</script>
