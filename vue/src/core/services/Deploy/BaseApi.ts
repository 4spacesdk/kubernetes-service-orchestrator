import {SharedBaseApi} from "../ApiHelpers/SharedBaseApi";
import ApiService from "@/services/ApiService";
import type {AxiosInstance} from "axios";

export class BaseApi<T = any> extends SharedBaseApi<T> {

    public getAxios(): AxiosInstance {
        return ApiService.apiAxios!;
    }

}
