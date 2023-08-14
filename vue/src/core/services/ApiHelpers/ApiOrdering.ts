export class OrderingItem {
    name: string;
    direction: string;

    constructor(name: string, direction: string) {
        this.name = name;
        this.direction = direction;
    }

    public toString() {
        return `${this.name}:${this.direction}`;
    }
}

export class OrderDirection {
    static Ascending = "asc";
    static Descending = "desc";
}

export class ApiOrdering {
    public orderingItems: OrderingItem[] = [];

    public orderBy(name: string, direction: string): ApiOrdering {
        this.orderingItems.push(new OrderingItem(name, direction));
        return this;
    }

    public orderAsc(name: string): ApiOrdering {
        this.orderingItems.push(new OrderingItem(name, OrderDirection.Ascending));
        return this;
    }

    public orderDesc(name: string): ApiOrdering {
        this.orderingItems.push(new OrderingItem(name, OrderDirection.Descending));
        return this;
    }

    public hasItems(): boolean {
        return this.orderingItems.length > 0;
    }

    public toString(): string {
        return this.orderingItems.map(item => item.toString()).join(",");
    }
}
