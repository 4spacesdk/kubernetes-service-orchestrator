/**
 * Created by ModelParser
 */
import {AuditEventDefinition} from './definitions/AuditEventDefinition';

export class AuditEvent extends AuditEventDefinition {

    constructor(json?: any) {
        super(json);
    }

    /**
     * `details` as the server wrote it: `changes` (field => [before, after]), `relation`, or
     * what a named action adds.
     */
    public get parsedDetails(): {changes?: Record<string, [any, any]>, relation?: {name?: string, type?: string, id?: number}, [key: string]: any} {
        try {
            return this.details ? JSON.parse(this.details) : {};
        } catch {
            return {};
        }
    }

}
