/**
 * Created by ModelParser
 * Date: 09-01-2023.
 * Time: 12:33.
 */
import {DeploymentDefinition} from './definitions/DeploymentDefinition';

export class Deployment extends DeploymentDefinition {

    constructor(json?: any) {
        super(json);
    }

    public get canMigrate(): boolean {
        return this.deployment_specification?.enable_database ?? false;
    }

}
