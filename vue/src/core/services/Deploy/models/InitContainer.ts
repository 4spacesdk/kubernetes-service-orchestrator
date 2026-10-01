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

    /**
     * A sidecar - Centrifugo, a database proxy - is another product than the app, so it takes its
     * image's default tag rather than the deployment's version.
     */
    public static CreateDefault(isSidecar = false): InitContainer {
        const item = new InitContainer();
        item.is_sidecar = isSidecar;
        item.container_image_tag_policy = isSidecar ? ContainerImageTagPolicies.Default : ContainerImageTagPolicies.MatchDeployment;
        item.container_image_pull_policy = ImagePullPolicies.IfNotPresent;
        return item;
    }

}
