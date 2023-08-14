import {EventEmitter} from '@/helpers/EventEmitter';

export class Subscription<T = any> {

    private eventEmitter: EventEmitter<T>;
    private readonly next: (value?: T) => void;

    constructor(next: (value?: T) => void, eventEmitter: EventEmitter<T>) {
        this.next = next;
        this.eventEmitter = eventEmitter;
    }

    public emit(value?: T) {
        this.next(value);
    }

    public unsubscribe() {
        this.eventEmitter.unsubscribe(this);
    }

}
