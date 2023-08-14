import axios, {CancelToken, CancelTokenSource} from 'axios';

export class ApiRequest {

    private request: CancelTokenSource;

    constructor() {
        this.request = axios.CancelToken.source();
    }

    public getCancelToken(): CancelToken {
        return this.request.token;
    }

    public cancel() {
        this.request.cancel();
    }

}
