import ApiService from "@/services/ApiService";
import {ApiFilter} from "@/core/services/ApiHelpers/ApiFilter";
import {ApiInclude} from "@/core/services/ApiHelpers/ApiInclude";
import {ApiOrdering} from "@/core/services/ApiHelpers/ApiOrdering";
import AuthService from "@/services/AuthService";
import {ApiRequest} from "@/core/services/ApiHelpers/ApiRequest";
import type {AxiosInstance} from "axios";

export class SharedBaseApi<T = any> {
    protected uri?: string;
    protected topic?: string;

    protected method?: string;
    protected limitValue?: number;
    protected offsetValue?: number;
    private apiFilter = new ApiFilter();
    private apiInclude = new ApiInclude();
    private apiOrdering = new ApiOrdering();
    private apiQueryParams: any[] = [];
    public errorHandler?: (response: any) => boolean;
    public withCredentials = false;

    public setWithCredentials(value: boolean): SharedBaseApi<T> {
        this.withCredentials = value;
        return this;
    }

    public setErrorHandler(handler: (response: any) => boolean): SharedBaseApi<T> {
        this.errorHandler = handler;
        return this;
    }

    public addQueryParameter(key: string, value: any): SharedBaseApi {
        this.apiQueryParams.push({key, value});
        return this;
    }

    protected filter(): ApiFilter {
        return this.apiFilter;
    }

    protected getInclude(): ApiInclude {
        return this.apiInclude;
    }

    protected ordering(): ApiOrdering {
        return this.apiOrdering;
    }

    protected convertToResource(data: any): any {
        return data;
    }

    protected executeFind(next?: (value: T[]) => void): ApiRequest {
        return this.get(next);
    }

    public find(next?: (value: any) => void) {
        return this.executeFind(next);
    }

    public count(next?: (value: number) => void) {
        return this.executeCount(next);
    }

    protected executeSave(data: any, next?: (value: T) => void) {
        switch (this.method) {
            case "post":
                return this.post(data, next);
            case "put":
                return this.put(data, next);
            case "patch":
                return this.patch(data, next);
        }
    }

    public getAxios(): AxiosInstance {
        return ApiService.apiAxios!;
    }

    protected getParams(): any {
        const params: any = [];

        /*
         * Query Parameters
         */
        if (this.apiQueryParams && this.apiQueryParams.length > 0) {
            this.apiQueryParams.forEach(value => {
                params[value.key] = value.value;
            });
        }

        /*
         * Filtering
         */
        if (this.apiFilter?.hasItems()) {
            params["filter"] = this.apiFilter.toString();
        }

        /*
         * Include
         */
        if (this.apiInclude?.hasItems()) {
            params["include"] = this.apiInclude.toString();
        }

        /*
         * Ordering
         */
        if (this.apiOrdering?.hasItems()) {
            params["ordering"] = this.apiOrdering.toString();
        }

        /*
         * Offset & Limit
         */
        if (this.limitValue != null && this.limitValue >= 0) {
            params["limit"] = this.limitValue.toString();
        }
        if (this.offsetValue != null) {
            params["offset"] = this.offsetValue.toString();
        }

        return params;
    }

    protected get(next?: (value: T[]) => void): ApiRequest {
        const request = new ApiRequest();
        return ApiService.get(request, this.getAxios(), this.uri!, this.getParams(), (response: any) => {
            if (next) {
                if (response.resources) {
                    next(
                        response.resources.map((resource: any) =>
                            this.convertToResource(resource)
                        )
                    );
                } else if (response.resource) {
                    next([this.convertToResource(response.resource)]);
                } else {
                    if (this.errorHandler) {
                        if (!this.errorHandler(response)) {
                            return;
                        }
                    }
                    next([]);
                }
            }
        }, 1, this.withCredentials);
    }

    protected executeCount(next?: (value: number) => void): ApiRequest {
        const request = new ApiRequest();
        const params = this.getParams();
        params["count"] = 1;
        return ApiService.get(request, this.getAxios(), this.uri!, params, (response: any) => {
            if (next) {
                if (response.count) {
                    next(response.count);
                } else {
                    if (this.errorHandler) {
                        if (!this.errorHandler(response)) {
                            return;
                        }
                    }
                    next(0);
                }
            }
        }, 1, this.withCredentials);
    }

    private post(data: any, next?: (value: T) => void): ApiRequest {
        const request = new ApiRequest();
        return ApiService.post(request, this.getAxios(), this.uri!, data, this.getParams(), (response: any) => {
            if (next) {
                if (response.resources) {
                    next(
                        response.resources.map((resource: any) =>
                            this.convertToResource(resource)
                        )
                    );
                } else if (response.resource) {
                    next(this.convertToResource(response.resource));
                } else {
                    if (this.errorHandler) {
                        if (!this.errorHandler(response)) {
                            return;
                        }
                    }
                    next(null!);
                }
            }
        }, 1, this.withCredentials);
    }

    private put(data: any, next?: (value: T) => void): ApiRequest {
        const request = new ApiRequest();
        return ApiService.put(request, this.getAxios(), this.uri!, data, this.getParams(), (response: any) => {
            if (next) {
                if (response.resources) {
                    next(
                        response.resources.map((resource: any) =>
                            this.convertToResource(resource)
                        )
                    );
                } else if (response.resource) {
                    next(this.convertToResource(response.resource));
                } else {
                    if (this.errorHandler) {
                        if (!this.errorHandler(response)) {
                            return;
                        }
                    }
                    next(null!);
                }
            }
        }, 1, this.withCredentials);
    }

    private patch(data: any, next?: (value: T) => void): ApiRequest {
        const request = new ApiRequest();
        return ApiService.patch(request, this.getAxios(), this.uri!, data, this.getParams(), (response: any) => {
            if (next) {
                if (response.resources) {
                    next(
                        response.resources.map((resource: any) =>
                            this.convertToResource(resource)
                        )
                    );
                } else if (response.resource) {
                    next(this.convertToResource(response.resource));
                } else {
                    if (this.errorHandler) {
                        if (!this.errorHandler(response)) {
                            return;
                        }
                    }
                    next(null!);
                }
            }
        }, 1, this.withCredentials);
    }

    protected executeDelete(next?: (value: T) => void): ApiRequest {
        const request = new ApiRequest();
        return ApiService.delete(request, this.getAxios(), this.uri!, this.getParams(), (response: any) => {
            if (next) {
                if (response.resources) {
                    next(
                        response.resources.map((resource: any) =>
                            this.convertToResource(resource)
                        )
                    );
                } else if (response.resource) {
                    next(this.convertToResource(response.resource));
                } else {
                    if (this.errorHandler) {
                        if (!this.errorHandler(response)) {
                            return;
                        }
                    }
                    next(null!);
                }
            }
        }, 1, this.withCredentials);
    }

    public getUri(): string {
        return this.getAxios().defaults["baseURL"] + (this.uri ?? "");
    }

    public open(inTab: boolean, focus: boolean, appendToken: boolean) {
        let url = this.getUri();
        if (appendToken) {
            url += `?access_token=${AuthService.getToken()}`;
        }
        const tab = window.open(url, inTab ? '_blank' : '');
        if (focus) {
            tab?.focus();
        }
    }

    public download(fileName: string, next?: (data: any) => void): ApiRequest {
        const request = new ApiRequest();
        return ApiService.download(request, this.getAxios(), this.uri!, this.getParams(), fileName, (response: any) => {
            if (response.error) {
                if (this.errorHandler) {
                    if (!this.errorHandler(response)) {
                        return;
                    }
                }
            } else if (next) {
                next(response);
            }
        });
    }

    public upload(data: any, next?: (data: any) => void) {
        const request = new ApiRequest();
        ApiService.upload(request, this.getAxios(), this.uri!, data, this.getParams(), (response: any) => {
            if (response.error) {
                if (this.errorHandler) {
                    if (!this.errorHandler(response)) {
                        return;
                    }
                }
            } else if (next) {
                next(response);
            }
        });
    }

    // <editor-fold desc="RestExtension Filter options">

    public where(name: string, value: any): SharedBaseApi<T> {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): SharedBaseApi<T> {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): SharedBaseApi<T> {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): SharedBaseApi<T> {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): SharedBaseApi<T> {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): SharedBaseApi<T> {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): SharedBaseApi<T> {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): SharedBaseApi<T> {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): SharedBaseApi<T> {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): SharedBaseApi<T> {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): SharedBaseApi<T> {
        this.filter().search(name, value);
        return this;
    }

    public include(name: string): SharedBaseApi<T> {
        this.getInclude().include(name);
        return this;
    }

    public orderBy(name: string, direction: string): SharedBaseApi<T> {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): SharedBaseApi<T> {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): SharedBaseApi<T> {
        this.ordering().orderDesc(name);
        return this;
    }

    public limit(value: number): SharedBaseApi<T> {
        this.limitValue = value;
        return this;
    }

    public offset(value: number): SharedBaseApi<T> {
        this.offsetValue = value;
        return this;
    }

    // </editor-fold>
}
