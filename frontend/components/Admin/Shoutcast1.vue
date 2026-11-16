<template>
    <binary-install-page
        header-id="hdr_install_shoutcast1"
        :title="$gettext('Install SHOUTcast 1 DNAS')"
        api-path="/admin/shoutcast1"
        :intro="$gettext('SHOUTcast 1 DNAS is proprietary. MStream does not distribute the binary — upload your licensed classic DNAS 1.x package here so stations can select SHOUTcast 1 as a local frontend.')"
        :steps="steps"
        :not-installed-text="$gettext('SHOUTcast 1 DNAS is not currently installed on this installation.')"
        :uninstall-title="$gettext('Remove SHOUTcast 1 DNAS?')"
    />
</template>

<script setup lang="ts">
import { computed } from "vue";
import BinaryInstallPage from "~/components/Admin/BinaryInstallPage.vue";
import { useTranslate } from "~/vendor/gettext";

const { $gettext } = useTranslate();

const steps = computed(() => [
    $gettext(
        "Obtain the classic SHOUTcast DNAS 1.x Linux binary archive containing sc_serv.",
    ),
    $gettext(
        "Upload the .tar.gz on this page to extract it into the proper directory.",
    ),
]);
</script>
