/**
 * Created by ModelParser
 * Date: 23-12-2024.
 * Time: 17:09.
 */
import {K8sCronJobDefinition} from './definitions/K8sCronJobDefinition';
import {
    ContainerImageTagPolicies,
    CronJobConcurrencyPolicies,
    CronJobRestartPolicies,
    ImagePullPolicies
} from "@/constants";

export class K8sCronJob extends K8sCronJobDefinition {

    constructor(json?: any) {
        super(json);
    }

    public static CreateDefault(): K8sCronJob {
        const item = new K8sCronJob();
        item.container_image_tag_policy = ContainerImageTagPolicies.Default;
        item.container_image_pull_policy = ImagePullPolicies.IfNotPresent;
        item.concurrency_policy = CronJobConcurrencyPolicies.Forbid;
        item.restart_policy = CronJobRestartPolicies.Never;
        return item;
    }

}
