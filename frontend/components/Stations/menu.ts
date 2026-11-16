import { shallowRef, watch } from "vue";
import {
    GlobalPermissions,
    StationPermissions,
} from "~/entities/ApiInterfaces.ts";
import {
    filterMenu,
    MenuCategory,
    RawMenuCategory,
} from "~/functions/filterMenu.ts";
import { useStationData } from "~/functions/useStationQuery.ts";
import { useUserAllowed } from "~/functions/useUserAllowed.ts";
import { useUserAllowedForStation } from "~/functions/useUserallowedForStation.ts";
import { useTranslate } from "~/vendor/gettext.ts";
import IconBiBroadcast from "~icons/bi/broadcast";
import IconIcAssignment from "~icons/ic/baseline-assignment";
import IconIcCode from "~icons/ic/baseline-code";
import IconIcImage from "~icons/ic/baseline-image";
import IconIcInsertChart from "~icons/ic/baseline-insert-chart";
import IconIcLibraryMusic from "~icons/ic/baseline-library-music";
import IconIcLink from "~icons/ic/baseline-link";
import IconIcMic from "~icons/ic/baseline-mic";
import IconIcPodcasts from "~icons/ic/baseline-podcasts";
import IconIcPublic from "~icons/ic/baseline-public";
import IconIcQueueMusic from "~icons/ic/baseline-queue-music";
import IconIcSchedule from "~icons/ic/baseline-schedule";
import IconIcSettingsApplication from "~icons/ic/baseline-settings-applications";
import IconIcUpdate from "~icons/ic/baseline-update";

export type StationsMenuOptions = {
    onUpdateSongTitle?: () => void;
};

/**
 * MediaCP-like service console IA:
 * Overview → Configure → Media → Playlists → … → Reporting
 * Server-managed tools (ports, Liquidsoap, Stereo Tool) stay superadmin-only.
 */
export function useStationsMenu(options: StationsMenuOptions = {}) {
    const { $gettext } = useTranslate();

    const station = useStationData();
    const { userAllowed } = useUserAllowed();
    const { userAllowedForStation } = useUserAllowedForStation();
    const canManageServer = () => userAllowed(GlobalPermissions.Stations);

    const fullMenu: RawMenuCategory[] = [
        {
            key: "profile",
            label: $gettext("Overview"),
            icon: () => IconIcImage,
            url: {
                name: "stations:index",
            },
        },
        {
            key: "edit_profile",
            label: $gettext("Configure"),
            icon: () => IconIcSettingsApplication,
            url: {
                name: "stations:settings:index",
            },
            visible: () => userAllowedForStation(StationPermissions.Profile),
        },
        {
            key: "media",
            label: $gettext("Media"),
            icon: () => IconIcLibraryMusic,
            url: {
                name: "stations:files:index",
            },
            visible: () =>
                station.value.features.media &&
                userAllowedForStation(StationPermissions.Media),
        },
        {
            key: "playlists",
            label: $gettext("Playlists"),
            icon: () => IconIcQueueMusic,
            url: {
                name: "stations:playlists:index",
            },
            visible: () =>
                userAllowedForStation(StationPermissions.Media) &&
                station.value.features.media,
        },
        {
            key: "jingles",
            label: $gettext("Jingles"),
            icon: () => IconIcQueueMusic,
            url: {
                name: "stations:playlists:index",
                query: {
                    filter: "jingle",
                },
            },
            visible: () =>
                userAllowedForStation(StationPermissions.Media) &&
                station.value.features.media,
        },
        {
            key: "commercials",
            label: $gettext("Commercials"),
            icon: () => IconIcQueueMusic,
            url: {
                name: "stations:playlists:index",
                query: {
                    filter: "commercial",
                },
            },
            visible: () =>
                userAllowedForStation(StationPermissions.Media) &&
                station.value.features.media,
        },
        {
            key: "schedule",
            label: $gettext("Schedule"),
            icon: () => IconIcSchedule,
            url: {
                name: "stations:playlists:index",
                query: {
                    tab: "schedule_view",
                },
            },
            visible: () =>
                userAllowedForStation(StationPermissions.Media) &&
                station.value.features.media,
        },
        {
            key: "widgets_links",
            label: $gettext("Widgets & Links"),
            icon: () => IconIcLink,
            url: {
                name: "stations:widgets-links",
            },
            visible: () => userAllowedForStation(StationPermissions.Profile),
        },
        {
            key: "public_page",
            label: $gettext("Public Page"),
            icon: () => IconIcPublic,
            url: {
                name: "stations:branding",
            },
            visible: () => userAllowedForStation(StationPermissions.Profile),
        },
        {
            key: "podcasts",
            label: $gettext("Podcasts"),
            icon: () => IconIcPodcasts,
            url: {
                name: "stations:podcasts:index",
            },
            visible: () =>
                userAllowedForStation(StationPermissions.Podcasts) &&
                station.value.features.podcasts,
        },
        {
            key: "mounts",
            label: $gettext("Mount Points"),
            icon: () => IconBiBroadcast,
            url: {
                name: "stations:mounts:index",
            },
            visible: () =>
                userAllowedForStation(StationPermissions.MountPoints) &&
                station.value.features.mountPoints,
        },
        {
            key: "streaming",
            label: $gettext("DJ Manager"),
            icon: () => IconIcMic,
            url: {
                name: "stations:streamers:index",
            },
            visible: () =>
                userAllowedForStation(StationPermissions.Streamers) &&
                station.value.features.streamers,
        },
        {
            key: "update_song_title",
            label: $gettext("Update Song Title"),
            icon: () => IconIcUpdate,
            click: () => options.onUpdateSongTitle?.(),
            visible: () =>
                !!options.onUpdateSongTitle &&
                userAllowedForStation(StationPermissions.Broadcasting),
        },
        {
            key: "webhooks",
            label: $gettext("Alerts & Integrations"),
            icon: () => IconIcCode,
            url: {
                name: "stations:webhooks:index",
            },
            visible: () =>
                userAllowedForStation(StationPermissions.WebHooks) &&
                station.value.features.webhooks,
        },
        {
            key: "logs",
            label: $gettext("Logs"),
            icon: () => IconIcAssignment,
            url: {
                name: "stations:logs",
            },
            visible: () => userAllowedForStation(StationPermissions.Logs),
        },
        {
            key: "reports",
            label: $gettext("Reporting"),
            icon: () => IconIcInsertChart,
            url: {
                name: "stations:reports:overview",
            },
            visible: () => userAllowedForStation(StationPermissions.Reports),
        },
        {
            key: "server_tools",
            label: $gettext("Server Tools"),
            icon: () => IconIcSettingsApplication,
            visible: () => canManageServer(),
            items: [
                {
                    key: "ls_config",
                    label: $gettext("Advanced AutoDJ Config"),
                    url: {
                        name: "stations:util:ls_config",
                    },
                    visible: () =>
                        station.value.features.customLiquidsoapConfig,
                },
                {
                    key: "stereo_tool",
                    label: $gettext("Stereo Tool Config"),
                    url: {
                        name: "stations:stereo_tool_config",
                    },
                    visible: () => station.value.features.media,
                },
            ],
        },
    ];

    const menu = shallowRef<MenuCategory[]>([]);

    watch(
        station,
        () => {
            menu.value = filterMenu(fullMenu);
        },
        {
            immediate: true,
        },
    );

    return menu;
}
