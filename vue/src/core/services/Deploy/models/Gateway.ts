/**
 * Created by ModelParser
 * Date: 30-04-2026.
 * Time: 09:44.
 */
import {GatewayDefinition} from './definitions/GatewayDefinition';

export class Gateway extends GatewayDefinition {
    constructor(json?: any) {
        super(json);
    }

    public static Create(): Gateway {
        const item = new Gateway();
        return item;
    }
}
