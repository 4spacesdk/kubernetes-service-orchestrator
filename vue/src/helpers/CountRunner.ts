export class CountRunner {

    private count: number;
    private runner?: () => void;

    constructor(count?: number) {
        this.count = (count ?? 0) + 1;
    }

    public setCount(value: number): CountRunner {
        this.count = value + 1; // Add 1 for start
        return this;
    }

    public setRunner(value: (() => void)): CountRunner {
        this.runner = value;
        return this;
    }

    public start() {
        this.increment();
    }

    public increment() {
        if (--this.count == 0) {
            this.execute();
        }
    }

    private execute() {
        if (this.runner) {
            this.runner();
        }
    }

}
