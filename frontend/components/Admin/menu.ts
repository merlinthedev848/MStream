import { GlobalPermissions } from "~/entities/ApiInterfaces.ts";
import { filterMenu, RawMenuCategory } from "~/functions/filterMenu.ts";
import { useUserAllowed } from "~/functions/useUserAllowed.ts";
import { useTranslate } from "~/vendor/gettext.ts";
import IconIcDashboard from "~icons/ic/baseline-dashboard";
import IconIcGroup from "~icons/ic/baseline-group";
import IconIcRadio from "~icons/ic/baseline-radio";
import IconIcSettings from "~icons/ic/baseline-settings";

/**
 * MediaCP-like admin IA (independent implementation):
 * Dashboard → Customers → Services → System ▾
 * Path: Admin → Customers → Service → Console
 */
export function useAdminMenu() {
    const { $gettext } = useTranslate();
    const { userAllowed } = useUserAllowed();

    const fullMenu: RawMenuCategory[] = [
        {
            key: "dashboard",
            label: $gettext("Dashboard"),
            icon: () => IconIcDashboard,
            url: {
                name: "admin:index",
            },
            visible: () => userAllowed(GlobalPermissions.View),
        },
        {
            key: "customers",
            label: $gettext("Customers"),
            icon: () => IconIcGroup,
            url: {
                name: "admin:customers:index",
            },
            visible: () =>
                userAllowed(GlobalPermissions.Stations) ||
                userAllowed(GlobalPermissions.ResellerStations),
        },
        {
            key: "manage_stations",
            label: $gettext("Services"),
            icon: () => IconIcRadio,
            url: {
                name: "admin:stations:index",
            },
            visible: () =>
                userAllowed(GlobalPermissions.Stations) ||
                userAllowed(GlobalPermissions.ResellerStations),
        },
        {
            key: "system",
            label: $gettext("System"),
            icon: () => IconIcSettings,
            items: [
                {
                    key: "settings",
                    label: $gettext("System Config"),
                    url: {
                        name: "admin:settings:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Settings),
                },
                {
                    key: "branding",
                    label: $gettext("Branding"),
                    url: {
                        name: "admin:branding:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Settings),
                },
                {
                    key: "resellers",
                    label: $gettext("Reseller Plans"),
                    url: {
                        name: "admin:resellers:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Stations),
                },
                {
                    key: "billing",
                    label: $gettext("Hosting Packages"),
                    url: {
                        name: "admin:billing:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.All),
                },
                {
                    key: "api_keys",
                    label: $gettext("API Keys"),
                    url: {
                        name: "admin:api:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.ApiKeys),
                },
                {
                    key: "manage_users",
                    label: $gettext("Panel Accounts"),
                    url: {
                        name: "admin:users:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.All),
                },
                {
                    key: "permissions",
                    label: $gettext("Roles & Permissions"),
                    url: {
                        name: "admin:permissions:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.All),
                },
                {
                    key: "auditlog",
                    label: $gettext("Audit Log"),
                    url: {
                        name: "admin:auditlog:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Logs),
                },
                {
                    key: "logs",
                    label: $gettext("System Logs"),
                    url: {
                        name: "admin:logs:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Logs),
                },
                {
                    key: "storage_locations",
                    label: $gettext("Storage"),
                    url: {
                        name: "admin:storage_locations:index",
                    },
                    visible: () =>
                        userAllowed(GlobalPermissions.StorageLocations),
                },
                {
                    key: "backups",
                    label: $gettext("Backups"),
                    url: {
                        name: "admin:backups:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Backups),
                },
                {
                    key: "custom_fields",
                    label: $gettext("Custom Fields"),
                    url: {
                        name: "admin:custom_fields:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.CustomFields),
                },
                {
                    key: "relays",
                    label: $gettext("Stream Relays"),
                    url: {
                        name: "admin:relays:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Stations),
                },
                {
                    key: "shoutcast",
                    label: $gettext("SHOUTcast 2 DNAS"),
                    url: {
                        name: "admin:install_shoutcast:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Settings),
                },
                {
                    key: "shoutcast1",
                    label: $gettext("SHOUTcast 1 DNAS"),
                    url: {
                        name: "admin:install_shoutcast1:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Settings),
                },
                {
                    key: "rsas",
                    label: $gettext("Rocket Streaming Audio Server (RSAS)"),
                    url: {
                        name: "admin:install_rsas:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Settings),
                },
                {
                    key: "nginx_rtmp",
                    label: $gettext("Nginx RTMP"),
                    url: {
                        name: "admin:install_nginx_rtmp:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Settings),
                },
                {
                    key: "flussonic",
                    label: $gettext("Flussonic (BYO)"),
                    url: {
                        name: "admin:install_flussonic:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Settings),
                },
                {
                    key: "wowza",
                    label: $gettext("Wowza (BYO)"),
                    url: {
                        name: "admin:install_wowza:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Settings),
                },
                {
                    key: "stereo_tool",
                    label: $gettext("Stereo Tool"),
                    url: {
                        name: "admin:stereo_tool:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Settings),
                },
                {
                    key: "geolite",
                    label: $gettext("GeoLite IP Database"),
                    url: {
                        name: "admin:install_geolite:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.Settings),
                },
                {
                    key: "debug",
                    label: $gettext("Software Health"),
                    url: {
                        name: "admin:debug:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.All),
                },
                {
                    key: "updates",
                    label: $gettext("Software Updates"),
                    url: {
                        name: "admin:updates:index",
                    },
                    visible: () => userAllowed(GlobalPermissions.All),
                },
            ],
        },
    ];

    return filterMenu(fullMenu);
}
