import {OrderingItem} from "@/core/services/ApiHelpers/ApiOrdering";

export class FilterOperators {
    static Equals = "";
    static Not = "-";
    static GreaterThan = ">";
    static GreaterThanOrEqual = ">=";
    static LessThan = "<";
    static LessThanOrEqual = "<=";
    static Search = "~";
}

export class OrderDirection {
    static Ascending = "asc";
    static Descending = "desc";
}

export class ApiFilterItem {
    name: string;
    operand: string;
    value: any;

    constructor(name: string, operand: string, value: any) {
        this.name = name;
        this.operand = operand;
        this.value = value;
    }

    public toString() {
        if(this.operand) {
            return `${this.name}:${this.operand}${this.value}`;
        } else {
            return `${this.name}:${this.value}`;
        }
    }
}

export class ApiFilter {
    public filterItems: ApiFilterItem[] = [];

    orderItems: OrderingItem[] = [];

    public where(name: string, value: any): ApiFilter {
        if(typeof value === "boolean") {
            value = value ? 1 : 0;
        }
        return this.whereEquals(name, value);
    }

    public orderBy(name: string, direction: string) {
        this.orderItems.push(new OrderingItem(name, direction));
        return this;
    }

    public orderAsc(name: string) {
        this.orderItems.push(new OrderingItem(name, OrderDirection.Ascending));
        return this;
    }

    public orderDesc(name: string) {
        this.orderItems.push(new OrderingItem(name, OrderDirection.Descending));
        return this;
    }

    public whereIn(name: string, value: any[]): ApiFilter {
        return this.whereInArray(name, value);
    }

    public whereEquals(name: string, value: any) {
        this.filterItems.push(
            new ApiFilterItem(name, FilterOperators.Equals, value)
        );
        return this;
    }

    public whereInArray(name: string, value: any[]) {
        this.filterItems.push(new ApiFilterItem(name, "", `[${value.join(",")}]`));
        return this;
    }

    public whereNot(name: string, value: any) {
        this.filterItems.push(new ApiFilterItem(name, FilterOperators.Not, value));
        return this;
    }

    public whereGreaterThan(name: string, value: any) {
        this.filterItems.push(
            new ApiFilterItem(name, FilterOperators.GreaterThan, value)
        );
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any) {
        this.filterItems.push(
            new ApiFilterItem(name, FilterOperators.GreaterThanOrEqual, value)
        );
        return this;
    }

    public whereLessThan(name: string, value: any) {
        this.filterItems.push(
            new ApiFilterItem(name, FilterOperators.LessThan, value)
        );
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any) {
        this.filterItems.push(
            new ApiFilterItem(name, FilterOperators.LessThanOrEqual, value)
        );
        return this;
    }

    public search(name: string, value: any) {
        this.filterItems.push(
            new ApiFilterItem(name, FilterOperators.Search, `"${value}"`)
        );
        return this;
    }

    public whereNotIn(name: string, value: any[]): ApiFilter {
        this.filterItems.push(
            new ApiFilterItem(name, FilterOperators.Not, `[${value.join(",")}]`)
        );
        return this;
    }

    public filtersString(): string {
        const strings: string[] = [];
        this.filterItems.forEach(i => {
            strings.push(`${i.toString()}`);
        });
        return `${strings.join(",")}`;
    }

    public orderingString(): string {
        const strings: string[] = [];
        this.orderItems.forEach(i => {
            strings.push(i.toString());
        });
        return `${strings.join(",")}`;
    }

    public hasItems(): boolean {
        return this.filterItems.length > 0;
    }

    public toString(): string {
        return this.filterItems.map(item => item.toString()).join(",");
    }
}
