export class ApiInclude {
    public includeItems: string[] = [];

    public include(value: string): ApiInclude {
        this.includeItems.push(value);
        return this;
    }

    public toString(): string {
        return this.includeItems.join(",");
    }

    public hasItems(): boolean {
        return this.includeItems.length > 0;
    }
}
