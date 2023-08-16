/**
 * Created by ModelParser
 * Date: 16-08-2023.
 * Time: 14:21.
 */
import {OAuthClientDefinition} from './definitions/OAuthClientDefinition';

export class OAuthClient extends OAuthClientDefinition {

    constructor(json?: any) {
        super(json);
    }

    exists(): boolean {
        return this.client_id !== undefined;
    }

}
