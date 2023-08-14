/**
 * Created by ModelParser
 * Date: 04-08-2023.
 * Time: 09:10.
 */
import {DeploymentSpecificationDefinition} from './definitions/DeploymentSpecificationDefinition';
import {Deployment} from "@/core/services/Deploy/models/Deployment";

export class DeploymentSpecification extends DeploymentSpecificationDefinition {

    constructor(json?: any) {
        super(json);
    }

    public generateUrl(deployment: Deployment, includeTls: boolean): string {
        const tls = includeTls ? `${this.domain_tls}://` : '';
        const domain = deployment.subdomain?.length
            ? `${deployment.subdomain}.${deployment.domain?.name}`
            : deployment.domain?.name;
        return `${tls}${this.domain_prefix}${domain}${this.domain_suffix}`;
    }

}
