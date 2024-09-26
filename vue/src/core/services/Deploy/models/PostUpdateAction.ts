/**
 * Created by ModelParser
 * Date: 26-09-2024.
 * Time: 20:51.
 */
import {PostUpdateActionDefinition} from './definitions/PostUpdateActionDefinition';

export class PostUpdateAction extends PostUpdateActionDefinition {

    constructor(json?: any) {
        super(json);
    }

    public static CreateDefault(): PostUpdateAction {
        const item = new PostUpdateAction();

        return item;
    }

}
