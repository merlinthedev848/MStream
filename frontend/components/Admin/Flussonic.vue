<template>
    <binary-install-page
        header-id="hdr_install_flussonic"
        :title="$gettext('Install Flussonic (BYO license)')"
        api-path="/admin/flussonic"
        :intro="$gettext('Flussonic is proprietary. MStream never vendors Flussonic installers or licenses. Place your licensed binary here so Create Service can supervise Flussonic video stations.')"
        :steps="steps"
        :not-installed-text="$gettext('Flussonic is not currently installed on this installation.')"
        :uninstall-title="$gettext('Remove Flussonic installation?')"
    />
</template>

<script setup lang="ts">
import { computed } from "vue";
import BinaryInstallPage from "~/components/Admin/BinaryInstallPage.vue";
import { useTranslate } from "~/vendor/gettext";

const { $gettext } = useTranslate();

const steps = computed(() => [
    $gettext(
        "Obtain your licensed Flussonic Media Server package from Flussonic.",
    ),
    $gettext("Upload a .tar.gz that extracts to bin/flussonic or flussonic."),
]);
</script>
