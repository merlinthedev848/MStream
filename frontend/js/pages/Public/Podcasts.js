import initApp from "~/layout";
import {h} from "vue";
import {createRouter, createWebHistory} from "vue-router";
import {useMStream} from "~/vendor/mstream";
import PodcastsLayout from "~/components/Public/Podcasts/PodcastsLayout.vue";
import usePodcastRoutes from "~/components/Public/Podcasts/routes";

initApp({
    setup() {
        const {componentProps} = useMStream();
        return {componentProps};
    },
    render() {
        return h(PodcastsLayout, this.componentProps);
    }
}, async (vueApp) => {
    const {componentProps} = useMStream();

    const router = createRouter({
        history: createWebHistory(componentProps.baseUrl),
        routes: usePodcastRoutes()
    });
    vueApp.use(router);
});
