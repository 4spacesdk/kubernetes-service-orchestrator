import PushService from "@/services/Push/PushService";

export class PushSubscription {
    public readonly event: string;
    private readonly handler: (data: any) => void;
    private isUnsubscribed = false;

    constructor(event: string, handler: (data: any) => void) {
        this.event = event;
        this.handler = handler;
    }

    public handle(data: any) {
        if (!this.isUnsubscribed) {
            this.handler(data);
        }
    }

    public unsubscribe() {
        if (this.isUnsubscribed) {
            return;
        }
        this.isUnsubscribed = true;
        PushService.unsubscribe(this);
    }
}
