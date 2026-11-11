import {
    PlaylistOrders,
    PlaylistRemoteTypes,
    PlaylistSources,
    PlaylistTypes,
} from "~/entities/ApiInterfaces.ts";
import { useStationsPlaylistsForm } from "~/components/Stations/Playlists/Form/form.ts";
import { useTranslate } from "~/vendor/gettext";

export type PlaylistPresetId =
    | "station_id"
    | "commercial_break"
    | "external_feed";

export function usePlaylistPresets() {
    const { $gettext } = useTranslate();
    const formStore = useStationsPlaylistsForm();

    const playlistPresets = [
        {
            id: "station_id" as const,
            label: $gettext("Station ID / Jingle"),
        },
        {
            id: "commercial_break" as const,
            label: $gettext("Commercial Break (Interrupt)"),
        },
        {
            id: "external_feed" as const,
            label: $gettext("External News / Feed"),
        },
    ];

    const applyPreset = (presetId: PlaylistPresetId): void => {
        formStore.$reset();

        switch (presetId) {
            case "station_id":
                formStore.form.name = $gettext("Station IDs");
                formStore.form.description = $gettext(
                    "Plays station IDs between songs. Upload short ID beds, then tune once-per-x as needed.",
                );
                formStore.form.source = PlaylistSources.Songs;
                formStore.form.type = PlaylistTypes.OncePerXSongs;
                formStore.form.play_per_songs = 3;
                formStore.form.order = PlaylistOrders.Shuffle;
                formStore.form.is_jingle = true;
                formStore.form.include_in_requests = false;
                formStore.form.weight = 3;
                formStore.form.backend_options = [];
                break;
            case "commercial_break":
                formStore.form.name = $gettext("Commercial Break");
                formStore.form.description = $gettext(
                    "Interrupting commercial/promo block. Add a schedule window on the Schedule tab after create.",
                );
                formStore.form.source = PlaylistSources.Songs;
                formStore.form.type = PlaylistTypes.Standard;
                formStore.form.order = PlaylistOrders.Sequential;
                formStore.form.is_jingle = true;
                formStore.form.include_in_requests = false;
                formStore.form.weight = 5;
                formStore.form.backend_options = ["interrupt", "single_track"];
                break;
            case "external_feed":
                formStore.form.name = $gettext("External News Feed");
                formStore.form.description = $gettext(
                    "Pulls a remote stream URL (news bed / third-party feed). Set the URL, then schedule or interrupt as needed.",
                );
                formStore.form.source = PlaylistSources.RemoteUrl;
                formStore.form.type = PlaylistTypes.Standard;
                formStore.form.remote_type = PlaylistRemoteTypes.Stream;
                formStore.form.remote_buffer = 20;
                formStore.form.is_jingle = false;
                formStore.form.include_in_requests = false;
                formStore.form.weight = 5;
                formStore.form.backend_options = ["interrupt"];
                break;
        }
    };

    return {
        playlistPresets,
        applyPreset,
    };
}
