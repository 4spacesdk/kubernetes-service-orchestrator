/**
 * Created by ModelParser
 * Date: 04-08-2023.
 * Time: 07:19.
 */
import {ContainerImageDefinition} from './definitions/ContainerImageDefinition';
import { System } from './System';

export class ContainerImage extends ContainerImageDefinition {

    constructor(json?: any) {
        super(json);
    }

    public static Create(): ContainerImage {
        const item = new ContainerImage();
        item.pull_secret = System.imagePullSecretDefaultName;
        return item;
    }

}
