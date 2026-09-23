/**
 * One request, and the means to cancel it - a log watch the dialog closes over, say.
 *
 * An `AbortController` rather than axios' `CancelToken`, which axios 1 deprecates. A cancelled
 * request is rejected the way it was before (`axios.isCancel()` is true for both), so nothing that
 * handles the rejection changes.
 */
export class ApiRequest {

    private controller = new AbortController();

    public getSignal(): AbortSignal {
        return this.controller.signal;
    }

    public cancel() {
        this.controller.abort();
    }

}
