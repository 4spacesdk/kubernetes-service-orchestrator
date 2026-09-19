import {createApp} from 'vue'
import type {DirectiveBinding} from 'vue'
import type {VNode} from 'vue'
import type {App} from 'vue'
import router from './router'
import moment from 'moment';
import Sortable from 'sortablejs';
import type {SortableEvent} from 'sortablejs';
import AppComponent from './App.vue'

// Vuetify
import 'vuetify/styles'
import './scss/main.scss'
import ApiService from "@/services/ApiService";
import AuthService from "@/services/AuthService";
import { registerSW } from 'virtual:pwa-register'
import vuetify from "@/plugins/vuetify";

import WampService from "@/services/Wamp/WampService";

const setupVue = (app: App<Element>) => {

    app.use(router);
    app.use(vuetify);
    moment.locale('da');

    app.directive('sortableDataTable', {
        created(el: HTMLElement, binding: DirectiveBinding, vnode: VNode) {
            const options = {
                animation: 150,
                onEnd: (event: SortableEvent) => {
                    el.dispatchEvent(new CustomEvent('sorted', {
                        'detail': event
                    }));
                },
            }
            Sortable.create(el.getElementsByTagName('tbody')[0], options);
        }
    });

};


registerSW({
    onRegistered(r) {
        // console.warn('service worker registrated, starting interval update check');
        r && setInterval(() => {
            r.update()
        }, 60 * 1000);
    }
});

if (location.origin.includes('localhost')) {
    ApiService.initApi('http://localhost:8950/api');
} else {
    ApiService.initApi(`${location.origin}/api`);
}

// Nothing is drawn until both are back, so they are asked for together. The token
// they need - from the url when kso runs in an iframe - is set before either goes out.
ApiService.useAccessTokenFromUrl();
Promise.all([
    new Promise<void>(resolve => ApiService.getSettings(resolve)),
    new Promise<void>(resolve => AuthService.refreshMe(() => resolve())),
]).then(() => {
    const app = createApp(AppComponent);

    setupVue(app);

    app.mount('#app');

    WampService.init();
});
