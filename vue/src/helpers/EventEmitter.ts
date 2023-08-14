import {Subscription} from "@/helpers/Subscription";

export class EventEmitter<T> {

    private subscriptions: Subscription<T>[] = [];

    public subscribe(next: (value?: T) => void): Subscription<T> {
        const subscription = new Subscription(next, this);
        this.subscriptions.push(subscription);
        return subscription;
    }

    public unsubscribe(subscription: Subscription<T>) {
        const index = this.subscriptions.indexOf(subscription);
        if (index !== -1) {
            this.subscriptions.splice(index, 1);
        }
    }

    public emit(value?: T) {
        this.subscriptions.forEach(subscription => subscription.emit(value));
    }

}
