/**
 * Created by ModelParser
 * Date: 26-09-2024.
 * Time: 20:51.
 */
import {PostUpdateActionConditionDefinition} from './definitions/PostUpdateActionConditionDefinition';

export class PostUpdateActionCondition extends PostUpdateActionConditionDefinition {

    constructor(json?: any) {
        super(json);
    }

    public static CreateDefault(): PostUpdateActionCondition {
        const item = new PostUpdateActionCondition();
        return item;
    }

}
