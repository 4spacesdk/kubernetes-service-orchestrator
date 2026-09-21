/**
 * Created by ModelParser
 */
import {ContainerImage} from '../ContainerImage';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class ContainerImageScanRecordDefinition extends BaseModel {
    container_image_id?: number;
    container_image?: ContainerImage;
    tag?: string;
    digest?: string;
    critical?: number;
    high?: number;
    medium?: number;
    low?: number;
    unknown?: number;
    scanned_at?: string;
    id?: number;
    created?: string;
    updated?: string;
    created_by_id?: number;
    created_by?: User;
    updated_by_id?: number;
    updated_by?: User;
    deletion_id?: number;
    deletion?: Deletion;

    constructor(data?: any) {
        super();
        this.populate(data);
    }

    public populate(data?: any, patch = false) {
        if (!patch) {
            delete this.container_image_id;
            delete this.container_image;
            delete this.tag;
            delete this.digest;
            delete this.critical;
            delete this.high;
            delete this.medium;
            delete this.low;
            delete this.unknown;
            delete this.scanned_at;
            delete this.id;
            delete this.created;
            delete this.updated;
            delete this.created_by_id;
            delete this.created_by;
            delete this.updated_by_id;
            delete this.updated_by;
            delete this.deletion_id;
            delete this.deletion;
        }

        if (!data) return;
        if (data.container_image_id != null) {
            this.container_image_id = data.container_image_id;
        }
        if (data.container_image != null) {
            this.container_image = new ContainerImage(data.container_image);
        }
        if (data.tag != null) {
            this.tag = data.tag;
        }
        if (data.digest != null) {
            this.digest = data.digest;
        }
        if (data.critical != null) {
            this.critical = data.critical;
        }
        if (data.high != null) {
            this.high = data.high;
        }
        if (data.medium != null) {
            this.medium = data.medium;
        }
        if (data.low != null) {
            this.low = data.low;
        }
        if (data.unknown != null) {
            this.unknown = data.unknown;
        }
        if (data.scanned_at != null) {
            this.scanned_at = data.scanned_at;
        }
        if (data.id != null) {
            this.id = data.id;
        }
        if (data.created != null) {
            this.created = data.created;
        }
        if (data.updated != null) {
            this.updated = data.updated;
        }
        if (data.created_by_id != null) {
            this.created_by_id = data.created_by_id;
        }
        if (data.created_by != null) {
            this.created_by = new User(data.created_by);
        }
        if (data.updated_by_id != null) {
            this.updated_by_id = data.updated_by_id;
        }
        if (data.updated_by != null) {
            this.updated_by = new User(data.updated_by);
        }
        if (data.deletion_id != null) {
            this.deletion_id = data.deletion_id;
        }
        if (data.deletion != null) {
            this.deletion = new Deletion(data.deletion);
        }
    }

}
