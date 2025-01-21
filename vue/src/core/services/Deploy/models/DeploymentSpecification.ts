/**
 * Created by ModelParser
 * Date: 04-08-2023.
 * Time: 09:10.
 */
import {DeploymentSpecificationDefinition} from './definitions/DeploymentSpecificationDefinition';
import {Deployment} from "@/core/services/Deploy/models/Deployment";
import {MigrationVerificationTypes} from "@/constants";
import {Domain} from "@/core/services/Deploy/models/Domain";

export class DeploymentSpecification extends DeploymentSpecificationDefinition {

    constructor(json?: any) {
        super(json);
    }

    public static Create(type: string): DeploymentSpecification {
        const item = new DeploymentSpecification();
        item.workload_type = type;
        item.custom_resource = '';
        item.database_migration_verification_type = MigrationVerificationTypes.EndsWith;
        item.database_migration_verification_value = 'Done migrations.';
        return item;
    }

    public generateUrl(subdomain: string, domain: Domain, includeTls: boolean, includeSuffix: boolean): string {
        const tls = includeTls ? `${this.domain_tls}://` : '';
        let url = domain.name!;
        if (subdomain.length > 0) {
            url = `${subdomain}.${url}`;
        }
        const suffix = includeSuffix ? (this.domain_suffix ?? '') : '';
        return `${tls}${this.domain_prefix}${url}${suffix}`;
    }

}
