/**
 * Created by ModelParser
 * Date: 16-01-2023.
 * Time: 12:35.
 */
import {SystemDefinition} from './definitions/SystemDefinition';

export class System extends SystemDefinition {

    constructor(json?: any) {
        super(json);
    }

    public static Instance: System;

    public static certManagerIssuerDefaultName: string;

}
