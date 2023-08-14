export class ChangeEvent<T> {
    public previous: T;
    public next: T;

    constructor(previous: T, next: T) {
        this.previous = previous;
        this.next = next;
    }
}
