/**
 * Created by ModelParser
 * Date: 13-01-2023.
 * Time: 13:21.
 */
import {KeelHookQueueItemDefinition} from './definitions/KeelHookQueueItemDefinition';

export class KeelHookQueueItem extends KeelHookQueueItemDefinition {

    constructor(json?: any) {
        super(json);
    }

    public get payload(): object {
        return JSON.parse(this.data ?? '{}');
    }

}
