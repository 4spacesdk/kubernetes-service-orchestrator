/**
 * Created by ModelParser
 * Date: 30-06-2020.
 * Time: 11:59.
 */
import {UserDefinition} from "./definitions/UserDefinition";

export class User extends UserDefinition {

    scopes: string[] = [];

    constructor(json?: any) {
        super(json);
    }

    get name(): string {
        return `${this.first_name} ${this.last_name}`;
    }

    public get initials(): string {
        return `${this.first_name?.substr(0, 1)}${this.last_name?.substr(0, 1)}`;
    }

    get isAdmin(): boolean {
        return ['service', 'developer', 'owner', 'admin'].includes(this.type ?? '');
    }

}
