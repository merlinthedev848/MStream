import { useDocumentVisibility, useIntervalFn } from "@vueuse/core";
import { computed, onScopeDispose, ref, shallowRef, watch } from "vue";
import { ApiStationAudioMeters } from "~/entities/ApiInterfaces.ts";
import { useApiRouter } from "~/functions/useApiRouter.ts";
import { useAxios } from "~/vendor/axios.ts";

const silentMeters = (): ApiStationAudioMeters => ({
    available: false,
    vu_l: -90,
    vu_r: -90,
    peak_l: -90,
    peak_r: -90,
});

const POLL_MS = 300;

export default function useStationMeters(enabled: () => boolean) {
    const meters = shallowRef<ApiStationAudioMeters>(silentMeters());
    const isFetching = ref(false);

    const { getStationApiUrl } = useApiRouter();
    const metersUrl = getStationApiUrl("/meters");
    const { axiosSilent } = useAxios();
    const visibility = useDocumentVisibility();

    const shouldPoll = computed(
        () => enabled() && visibility.value === "visible",
    );

    const fetchMeters = async () => {
        if (!shouldPoll.value || isFetching.value) {
            return;
        }

        isFetching.value = true;
        try {
            const { data } = await axiosSilent.get<ApiStationAudioMeters>(
                metersUrl.value,
            );
            meters.value = data;
        } catch {
            meters.value = silentMeters();
        } finally {
            isFetching.value = false;
        }
    };

    const { pause, resume } = useIntervalFn(
        () => {
            void fetchMeters();
        },
        POLL_MS,
        { immediate: false },
    );

    watch(
        shouldPoll,
        (active) => {
            if (active) {
                void fetchMeters();
                resume();
            } else {
                pause();
                meters.value = silentMeters();
            }
        },
        { immediate: true },
    );

    onScopeDispose(() => {
        pause();
    });

    return {
        meters,
    };
}
