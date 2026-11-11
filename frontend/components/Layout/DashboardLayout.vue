<template>
    <a
        class="visually-hidden-focusable"
        href="#content"
    >
        {{ $gettext('Skip to main content') }}
    </a>

    <header class="navbar bg-primary-dark shadow-sm fixed-top">
        <button
            id="navbar-toggle"
            data-bs-toggle="offcanvas"
            data-bs-target="#sidebar"
            aria-controls="sidebar"
            aria-expanded="false"
            :aria-label="$gettext('Toggle Sidebar')"
            class="navbar-toggler d-inline-flex d-lg-none me-3"
        >
            <icon-ic-menu class="lg"/>
        </button>
        
        <router-link
            class="navbar-brand ms-0 me-auto d-inline-flex align-items-center gap-2"
            :to="{ name: 'dashboard' }"
        >
            <img
                :src="'/static/img/mstream-icon.svg'"
                alt="MStream Logo"
                style="height: 26px; width: 26px;"
                class="align-middle"
            >
            <span>m<strong>stream</strong></span>
            <span
                v-if="instanceName"
                class="badge bg-secondary text-white-50 ms-2 px-2.5 py-1 fs-6"
            >{{ instanceName }}</span>
        </router-link>

        <div id="radio-player-controls">
            <inline-player class="ms-3"/>
        </div>

        <button
            type="button"
            class="btn btn-sm btn-link text-white-50 p-2 ms-2 theme-toggle-btn"
            :title="$gettext('Switch Theme')"
            :aria-label="$gettext('Switch Theme')"
            @click.prevent="toggleTheme"
        >
            <icon-ic-invert-colors class="md"/>
        </button>

        <div class="dropdown ms-3 d-inline-flex align-items-center">
            <div class="me-2">
                {{ displayName }}
            </div>

            <button
                aria-expanded="false"
                aria-haspopup="true"
                class="navbar-toggler"
                :aria-label="$gettext('Toggle Menu')"
                data-bs-toggle="dropdown"
                type="button"
            >
                <icon-ic-menu-open class="lg"/>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <router-link
                        class="dropdown-item"
                        :to="{ name: 'dashboard' }"
                    >
                        <icon-ic-home/>

                        {{ $gettext('Dashboard') }}
                    </router-link>
                </li>
                <li class="dropdown-divider">
&nbsp;
                </li>
                <li v-if="showAdmin">
                    <router-link
                        class="dropdown-item"
                        :to="{ name: 'admin:index'}"
                    >
                        <icon-ic-settings/>

                        {{ $gettext('Admin Console') }}
                    </router-link>
                </li>
                <li>
                    <router-link
                        class="dropdown-item"
                        :to="{name: 'profile:index'}"
                    >
                        <icon-ic-account-circle/>

                        {{ $gettext('My Account') }}
                    </router-link>
                </li>
                <li>
                    <a
                        class="dropdown-item theme-switcher"
                        href="#"
                        @click.prevent="toggleTheme"
                    >
                        <icon-ic-invert-colors/>
                        {{ $gettext('Switch Theme') }}
                    </a>
                </li>
                <li class="dropdown-divider">
                    &nbsp;
                </li>
                <li>
                    <a
                        class="dropdown-item"
                        href="/docs/"
                        target="_blank"
                    >
                        <icon-ic-support/>
                        {{ $gettext('Documentation') }}
                    </a>
                </li>
                <li>
                    <a
                        class="dropdown-item"
                        href="/docs/help/troubleshooting/"
                        target="_blank"
                    >
                        <icon-ic-help/>

                        {{ $gettext('Help') }}
                    </a>
                </li>
                <li class="dropdown-divider">
&nbsp;
                </li>
                <li>
                    <a
                        class="dropdown-item"
                        :href="logoutUrl"
                    >
                        <icon-ic-exit-to-app/>

                        {{ $gettext('Sign Out') }}
                    </a>
                </li>
            </ul>
        </div>
    </header>

    <slot/>

    <lightbox ref="$lightbox"/>
</template>

<script setup lang="ts">
import { useTemplateRef } from "vue";
import Lightbox from "~/components/Common/Lightbox.vue";
import InlinePlayer from "~/components/InlinePlayer.vue";
import { GlobalPermissions } from "~/entities/ApiInterfaces.ts";
import { useTheme } from "~/functions/theme.ts";
import { useUserAllowed } from "~/functions/useUserAllowed.ts";
import { useProvideLightbox } from "~/vendor/lightbox.ts";
import {
    useMStreamDashboardGlobals,
    useMStreamUser,
} from "~/vendor/mstream.ts";
import IconIcAccountCircle from "~icons/ic/baseline-account-circle";
import IconIcExitToApp from "~icons/ic/baseline-exit-to-app";
import IconIcHelp from "~icons/ic/baseline-help";
import IconIcHome from "~icons/ic/baseline-home";
import IconIcInvertColors from "~icons/ic/baseline-invert-colors";
import IconIcMenu from "~icons/ic/baseline-menu";
import IconIcMenuOpen from "~icons/ic/baseline-menu-open";
import IconIcSettings from "~icons/ic/baseline-settings";
import IconIcSupport from "~icons/ic/baseline-support";

const { instanceName, logoutUrl } = useMStreamDashboardGlobals();

const { displayName } = useMStreamUser();

const { userAllowed } = useUserAllowed();
const showAdmin = userAllowed(GlobalPermissions.View);

const { toggleTheme } = useTheme();

const $lightbox = useTemplateRef("$lightbox");
useProvideLightbox($lightbox);
</script>
