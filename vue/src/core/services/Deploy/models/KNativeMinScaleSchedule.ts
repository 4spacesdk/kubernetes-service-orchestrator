/**
 * Created by ModelParser
 * Date: 14-10-2025.
 * Time: 17:16.
 */
import {KNativeMinScaleScheduleDefinition} from './definitions/KNativeMinScaleScheduleDefinition';

export class KNativeMinScaleSchedule extends KNativeMinScaleScheduleDefinition {

    constructor(json?: any) {
        super(json);
    }

    public static CreateDefault(priority: number): KNativeMinScaleSchedule {
        const item = new KNativeMinScaleSchedule();
        item.min_scale = 1;
        item.priority = priority;
        item.timezone = 'Europe/Copenhagen';
        item.cron_expression = '0 8 * * 1-4';
        return item;
    }

}
