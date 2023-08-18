import {createApp} from 'vue'
import type {DirectiveBinding} from 'vue'
import type {VNode} from 'vue'
import type {App} from 'vue'
import router from './router'
import moment from 'moment';
import Sortable, {SortableEvent} from 'sortablejs';
import AppComponent from './App.vue'

// Vuetify
import 'vuetify/styles'
import './scss/main.scss'
import ApiService from "@/services/ApiService";
import AuthService from "@/services/AuthService";
import { registerSW } from 'virtual:pwa-register'
import vuetify from "@/plugins/vuetify";

// VueDiff
import 'vue-diff/dist/index.css';
import VueDiff from "vue-diff";
import WampService from "@/services/Wamp/WampService";

// Font Awesome
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faUserSecret } from '@fortawesome/free-solid-svg-icons'
library.add(faUserSecret);

const setupVue = (app: App<Element>) => {

    app.use(router);
    app.use(vuetify);
    app.use(VueDiff);
    moment.locale('da');
    app.component('font-awesome-icon', FontAwesomeIcon);

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
    ApiService.initApi('http://localhost:8403/api');
} else {
    ApiService.initApi(`${location.origin}/api`);
}

ApiService.getSettings(() => {

    AuthService.refreshMe((response) => {

        const app = createApp(AppComponent);

        setupVue(app);

        app.mount('#app');

        WampService.init();

    });

});
