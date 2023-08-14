import WampService from "@/services/Wamp/WampService";

export class WampSubscription {
    private readonly event: string;
    private handler: (data: any) => void;
    private subscription?: any;
    private isUnsubscribed = false;

    constructor(event: string, handler: (data: any) => void) {
        this.event = event;
        this.handler = handler;
    }

    public subscribe(session: any) {
        session?.subscribe(this.event, (payload: any) => {
            const data: {event: string; data: any} = JSON.parse(payload);
            this.handler(data.data);
        }).then((subscription: any) => this.subscription = subscription);
    }

    public unsubscribe() {
        if (this.isUnsubscribed) {
            return;
        }
        this.isUnsubscribed = true;
        this.subscription?.unsubscribe()?.then(null);
        WampService.unsubscribe(this);
    }
}
