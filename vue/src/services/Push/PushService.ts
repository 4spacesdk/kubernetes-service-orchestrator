import type {Centrifuge, Subscription} from "centrifuge";
import ApiService from "@/services/ApiService";
import AuthService from "@/services/AuthService";
import {PushSubscription} from "@/services/Push/PushSubscription";

/**
 * The browser's end of the push connection: one Centrifugo connection, one channel per event
 * name, and any number of components listening on each.
 *
 * Centrifugo refuses a second subscription to a channel on the same connection, and two
 * components do watch the same event - the menu badge and the auto update list both want to
 * know about new updates - so the channel is shared and the handlers are counted here. The
 * channel is left when the last one unsubscribes.
 *
 * Missed messages are replayed by Centrifugo when the connection comes back, from the few it
 * keeps per channel for five minutes. After a longer break the components show what they
 * last had until they load again, as before.
 */
class PushService {

    private centrifuge?: Centrifuge;
    private handlers = new Map<string, Set<PushSubscription>>();
    private channels = new Map<string, Subscription>();

    /**
     * The client is fetched here rather than bundled with the start of the app, so it does not
     * delay the first paint. Subscriptions made before it connects are opened when it does.
     */
    public init() {
        if (!ApiService.pushServiceUrl) {
            console.warn('PushService: Missing ApiService.pushServiceUrl!');
            return;
        }
        import('centrifuge').then(({Centrifuge}) => {
            this.centrifuge = new Centrifuge(ApiService.pushServiceUrl!, {
                getToken: () => this.fetchToken(),
            });
            this.handlers.forEach((_, event) => this.open(event));
            this.centrifuge.connect();
        });
    }

    public subscribe(event: string, handler: (data: any) => void): PushSubscription {
        const subscription = new PushSubscription(event, handler);
        let handlers = this.handlers.get(event);
        if (!handlers) {
            handlers = new Set();
            this.handlers.set(event, handlers);
            this.open(event);
        }
        handlers.add(subscription);
        return subscription;
    }

    public unsubscribe(subscription: PushSubscription) {
        const handlers = this.handlers.get(subscription.event);
        if (!handlers || !handlers.delete(subscription) || handlers.size > 0) {
            return;
        }
        this.handlers.delete(subscription.event);

        const channel = this.channels.get(subscription.event);
        if (channel) {
            channel.unsubscribe();
            this.centrifuge?.removeSubscription(channel);
            this.channels.delete(subscription.event);
        }
    }

    private open(event: string) {
        if (!this.centrifuge || this.channels.has(event)) {
            return;
        }
        const channel = this.centrifuge.newSubscription(event);
        channel.on('publication', ctx => {
            this.handlers.get(event)?.forEach(subscription => subscription.handle(ctx.data?.data));
        });
        channel.subscribe();
        this.channels.set(event, channel);
    }

    /**
     * The user's own access token: Centrifugo checks its signature against kso's JWKS. The
     * client asks again when the token is about to run out, and by then the stored one may
     * be that same token - so one close to its end is renewed first, the way a request that
     * got a 401 would be.
     */
    private fetchToken(): Promise<string> {
        const token = AuthService.getToken();
        if (token && this.secondsLeft(token) > 30) {
            return Promise.resolve(token);
        }
        return new Promise((resolve, reject) => {
            AuthService.lockAndRefreshToken(renewed => renewed
                ? resolve(AuthService.getToken())
                : reject(new Error('PushService: the access token could not be renewed')));
        });
    }

    private secondsLeft(token: string): number {
        try {
            const payload = JSON.parse(atob(token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')));
            return payload.exp - Date.now() / 1000;
        } catch {
            return 0;
        }
    }
}

export default new PushService();
