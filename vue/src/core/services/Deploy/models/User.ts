/**
 * Created by ModelParser
 * Date: 30-06-2020.
 * Time: 11:59.
 */
import {UserDefinition} from "./definitions/UserDefinition";
import {RbacPermission} from "@/core/services/Deploy/models/RbacPermission";
import _ from "lodash";

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

    private _allPermissions?: string[];
    get allPermissions(): string[] {
        if (!this._allPermissions) {
            const items: RbacPermission[] = [];
            this.rbac_roles?.forEach(role => {
                items.push(...role.rbac_permissions ?? []);
            });
            this._allPermissions = items.map(item => item.name!);
        }
        return this._allPermissions!;
    }

    public hasPermission(value: string | string[]): boolean {
        if (_.isArray(value)) {
            return value.some(permission => this.allPermissions.includes(permission));
        } else {
            return this.allPermissions.includes(value);
        }
    }

}
