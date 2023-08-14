import * as autobahn from 'autobahn-browser';
import AuthService from "@/services/AuthService";
import {WampSubscription} from "@/services/Wamp/WampSubscription";
import ApiService from "@/services/ApiService";

class WampService {

    private connection?: any;
    private subscriptions: WampSubscription[] = [];
    private session?: any;

    public init() {
        this.connect('realm1');
    }

    private connect(realm: string): void {
        // console.warn('WampService initialize : ', environment.socketUrl);

        this.close();

        if (!ApiService.pushServiceUrl) {
            console.warn('WampService: Missing ApiService.pushServiceUrl!');
            return;
        }
        this.connection = new autobahn.Connection({
            url: ApiService.pushServiceUrl,
            realm: realm,
            onchallenge: (session: any, method: string, extra: any): string => {
                // console.warn('WampService onchallenge');
                return AuthService.getToken();
            },
            authmethods: ['boris'],
            retry_if_unreachable: true,
            auto_reconnect: true
        });

        this.connection!.onopen = (session: any, details: any) => {
            // console.warn('WampService connection open : ', session);
            this.session = session;
            this.subscribeToSession();
        };

        this.connection!.onclose = (reason: any, details: any) => {
            return false;
        };

        this.connection!.open();
    }

    private close() {
        if (this.connection) {
            this.connection.close();
        }
    }

    private subscribeToSession() {
        this.subscriptions.forEach(subscription => {
            subscription.subscribe(this.session!);
        });
    }

    public subscribe(event: string, handler: (args: any) => void): WampSubscription {
        // console.warn('WampService subscribe to', event);
        const subscription = new WampSubscription(event, handler);
        this.subscriptions.push(subscription);
        subscription.subscribe(this.session!);
        return subscription;
    }

    public unsubscribe(subscription?: WampSubscription) {
        if (subscription) {
            const index = this.subscriptions.indexOf(subscription);
            if (index !== -1) {
                this.subscriptions.splice(index, 1);
            }
        }
    }
}

export default new WampService();
