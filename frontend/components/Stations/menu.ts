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
import IconIcMic from "~icons/ic/baseline-mic";
import IconIcPodcasts from "~icons/ic/baseline-podcasts";
import IconIcPublic from "~icons/ic/baseline-public";
import IconIcQueueMusic from "~icons/ic/baseline-queue-music";
import IconIcSchedule from "~icons/ic/baseline-schedule";
import IconIcSettingsApplication from "~icons/ic/baseline-settings-applications";

/**
 * MediaCP-like service console IA:
 * Overview → Configure → Media → Playlists → … → Reporting
 * Server-managed tools (ports, Liquidsoap, Stereo Tool) stay superadmin-only.
 */
export function useStationsMenu() {
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
            visible: () =>
                station.value.features.media &&
                userAllowedForStation(StationPermissions.Media),
            items: [
                {
                    key: "music_files",
                    label: $gettext("Music Library"),
                    url: {
                        name: "stations:files:index",
                    },
                },
                {
                    key: "duplicate_songs",
                    label: $gettext("Duplicate Songs"),
                    url: {
                        name: "stations:files:index",
                        params: {
                            path: "special:duplicates",
                        },
                    },
                },
                {
                    key: "unprocessable",
                    label: $gettext("Needs Attention"),
                    url: {
                        name: "stations:files:index",
                        params: {
                            path: "special:unprocessable",
                        },
                    },
                },
                {
                    key: "unassigned",
                    label: $gettext("Not in a Playlist"),
                    url: {
                        name: "stations:files:index",
                        params: {
                            path: "special:unassigned",
                        },
                    },
                },
                {
                    key: "ondemand",
                    label: $gettext("On-Demand Library"),
                    url: station.value.onDemandUrl,
                    external: true,
                    visible: () => station.value.enableOnDemand,
                },
                {
                    key: "sftp_users",
                    label: $gettext("SFTP Upload Accounts"),
                    url: {
                        name: "stations:sftp_users:index",
                    },
                    visible: () => station.value.features.sftp,
                },
                {
                    key: "bulk_media",
                    label: $gettext("Import / Export List"),
                    url: {
                        name: "stations:bulk-media",
                    },
                },
            ],
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
            key: "public_page",
            label: $gettext("Public Page"),
            icon: () => IconIcPublic,
            items: [
                {
                    key: "branding",
                    label: $gettext("Widgets & Branding"),
                    url: {
                        name: "stations:branding",
                    },
                    visible: () =>
                        userAllowedForStation(StationPermissions.Profile),
                },
                {
                    key: "public_player",
                    label: $gettext("Public Player"),
                    url: station.value.publicPageUrl,
                    external: true,
                    visible: () => station.value.enablePublicPages,
                },
                {
                    key: "public_schedule",
                    label: $gettext("Public Schedule"),
                    url: station.value.publicScheduleUrl,
                    external: true,
                    visible: () => station.value.enablePublicPages,
                },
                {
                    key: "public_on_demand",
                    label: $gettext("On-Demand Page"),
                    url: station.value.onDemandUrl,
                    external: true,
                    visible: () =>
                        station.value.enablePublicPages &&
                        station.value.enableOnDemand,
                },
                {
                    key: "public_podcasts",
                    label: $gettext("Podcasts Page"),
                    url: station.value.publicPodcastsUrl,
                    external: true,
                    visible: () => station.value.enablePublicPages,
                },
            ],
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
            items: [
                {
                    key: "mounts_list",
                    label: $gettext("Listener Streams"),
                    url: {
                        name: "stations:mounts:index",
                    },
                    visible: () =>
                        userAllowedForStation(StationPermissions.MountPoints) &&
                        station.value.features.mountPoints,
                },
                {
                    key: "hls_streams",
                    label: $gettext("HLS Streams"),
                    url: {
                        name: "stations:hls_streams:index",
                    },
                    visible: () =>
                        userAllowedForStation(StationPermissions.MountPoints) &&
                        station.value.features.hlsStreams,
                },
                {
                    key: "remotes",
                    label: $gettext("Remote Streams"),
                    url: {
                        name: "stations:remotes:index",
                    },
                    visible: () =>
                        userAllowedForStation(
                            StationPermissions.RemoteRelays,
                        ) && station.value.features.remoteRelays,
                },
                {
                    key: "fallback",
                    label: $gettext("Offline Fallback"),
                    url: {
                        name: "stations:fallback",
                    },
                    visible: () =>
                        userAllowedForStation(
                            StationPermissions.Broadcasting,
                        ) && station.value.features.media,
                },
                {
                    key: "queue",
                    label: $gettext("Upcoming Songs"),
                    url: {
                        name: "stations:queue:index",
                    },
                    visible: () =>
                        userAllowedForStation(
                            StationPermissions.Broadcasting,
                        ) && station.value.features.autoDjQueue,
                },
                {
                    key: "restart",
                    label: $gettext("Restart Service"),
                    url: {
                        name: "stations:restart:index",
                    },
                    visible: () =>
                        userAllowedForStation(StationPermissions.Broadcasting),
                },
                {
                    key: "ls_config",
                    label: $gettext("Advanced AutoDJ Config"),
                    url: {
                        name: "stations:util:ls_config",
                    },
                    visible: () =>
                        canManageServer() &&
                        station.value.features.customLiquidsoapConfig,
                },
                {
                    key: "stereo_tool",
                    label: $gettext("Stereo Tool Config"),
                    url: {
                        name: "stations:stereo_tool_config",
                    },
                    visible: () =>
                        canManageServer() && station.value.features.media,
                },
            ],
        },
        {
            key: "streaming",
            label: $gettext("DJ Manager"),
            icon: () => IconIcMic,
            visible: () =>
                userAllowedForStation(StationPermissions.Streamers) &&
                station.value.features.streamers,
            items: [
                {
                    key: "streamers",
                    label: $gettext("DJ Accounts"),
                    url: {
                        name: "stations:streamers:index",
                    },
                },
                {
                    key: "webdj",
                    label: $gettext("Browser DJ"),
                    url: station.value?.webDjUrl,
                    external: true,
                    visible: () => station.value.enablePublicPages,
                },
            ],
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
            visible: () => userAllowedForStation(StationPermissions.Reports),
            items: [
                {
                    key: "reports_overview",
                    label: $gettext("Station Statistics"),
                    url: {
                        name: "stations:reports:overview",
                    },
                },
                {
                    key: "reports_listeners",
                    label: $gettext("Listeners"),
                    url: {
                        name: "stations:reports:listeners",
                    },
                },
                {
                    key: "reports_requests",
                    label: $gettext("Song Requests"),
                    url: {
                        name: "stations:reports:requests",
                    },
                    visible: () =>
                        userAllowedForStation(
                            StationPermissions.Broadcasting,
                        ) && station.value.enableRequests,
                },
                {
                    key: "reports_timeline",
                    label: $gettext("Playback Timeline"),
                    url: {
                        name: "stations:reports:timeline",
                    },
                },
                {
                    key: "reports_soundexchange",
                    label: $gettext("SoundExchange Royalties"),
                    url: {
                        name: "stations:reports:soundexchange",
                    },
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
