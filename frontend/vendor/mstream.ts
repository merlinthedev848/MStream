import { defineStore } from "pinia";
import { InjectionKey } from "vue";
import {
    VueAppGlobals,
    VueDashboardGlobals,
    VueUserGlobals,
} from "~/entities/ApiInterfaces.ts";
import injectRequired from "~/functions/injectRequired.ts";

export const globalConstantsKey = Symbol() as InjectionKey<VueAppGlobals>;

export const useMStreamStore = defineStore(
    "global-props",
    (): {
        props: VueAppGlobals;
    } => {
        return {
            props: injectRequired(globalConstantsKey),
        };
    },
);

export const useMStream = (): VueAppGlobals => {
    const { props } = useMStreamStore();
    return props;
};

export const useMStreamDashboardGlobals = (): VueDashboardGlobals => {
    const { dashboardProps } = useMStream();
    if (!dashboardProps) {
        throw new Error("Dashboard properties are undefined in this request.");
    }

    return dashboardProps;
};

export const useMStreamUser = (): VueUserGlobals => {
    const { user } = useMStream();

    if (!user) {
        throw Error("User is not logged in.");
    }

    return user;
};
