/**
 * Created by ModelParser
 * Date: 09-01-2023.
 * Time: 12:33.
 */
import {CronJobDefinition} from './definitions/CronJobDefinition';

export class CronJob extends CronJobDefinition {

    constructor(json?: any) {
        super(json);
    }

}
