export class BaseModel {

    id?: number;

    public exists(): boolean {
        return this.id != null && this.id > 0;
    }

}
