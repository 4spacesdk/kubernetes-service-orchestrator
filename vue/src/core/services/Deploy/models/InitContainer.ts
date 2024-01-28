/**
 * Created by ModelParser
 * Date: 28-01-2024.
 * Time: 09:39.
 */
import {InitContainerDefinition} from './definitions/InitContainerDefinition';
import {ContainerImageTagPolicies, ImagePullPolicies} from "@/constants";

export class InitContainer extends InitContainerDefinition {

    constructor(json?: any) {
        super(json);
    }

    public static CreateDefault(): InitContainer {
        const item = new InitContainer();
        item.container_image_tag_policy = ContainerImageTagPolicies.MatchDeployment;
        item.container_image_pull_policy = ImagePullPolicies.IfNotPresent;
        return item;
    }

}
