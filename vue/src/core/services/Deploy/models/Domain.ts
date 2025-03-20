/**
 * Created by ModelParser
 * Date: 09-01-2023.
 * Time: 12:33.
 */
import {DomainDefinition} from './definitions/DomainDefinition';
import {System} from "@/core/services/Deploy/models/index";

export class Domain extends DomainDefinition {

    constructor(json?: any) {
        super(json);
    }

    public static Create(): Domain {
        const item = new Domain();
        item.issuer_ref_name = System.certManagerIssuerDefaultName;
        item.has_certificate_monitoring = true;
        item.certificate_monitoring_days_before_expiry = 25;
        return item;
    }

}
