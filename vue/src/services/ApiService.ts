import AuthService from "@/services/AuthService";
import {ApiRequest} from "@/core/services/ApiHelpers/ApiRequest";
import {System, User} from "@/core/services/Deploy/models";
import type {AxiosInstance} from "axios";
import axios from "axios";

class ApiService {
    public apiAxios?: AxiosInstance;
    public pushServiceUrl?: string;

    public initApi(baseURL: string) {
        this.apiAxios = axios.create({
            baseURL: baseURL
        });
    }

    public getSettings(callback: () => void) {
        this.apiAxios!
            .get("/settings")
            .then((response: any) => {
                const data = response.data;

                // Use AccessToken from uri (Used if iframe)
                const urlParams = new URLSearchParams(window.location.search);
                const accessToken = urlParams.get('access_token');
                if (accessToken) {
                    AuthService.setToken(accessToken);
                }

                if (AuthService.getToken()) {
                    this.setHeader();
                }

                System.Instance = new System(data.system);
                this.pushServiceUrl = data.pushServiceUrl;

                // All done
                callback();
            })
            .catch((error: any) => {
                this.checkError(error);
            });
    }

    public setHeader() {
        this.apiAxios!.defaults.headers.common[
            "Authorization"
            ] = `Bearer ${AuthService.getToken()}`;
    }

    public removeHeader() {
        this.apiAxios!.defaults.headers.common = {};
    }

    public redirectToLogin(redirectUri: string, grantType: string, clientId: string) {
        const authUrl = this.apiAxios!.defaults.baseURL + "/authorize";
        const scope = '';
        window.location.href = `${authUrl}?grant_type=${grantType}&redirect_uri=${redirectUri}&client_id=${clientId}&response_type=token&state=nonce&scope=${scope}`;
    }

    public redirectToLogout(redirectUri: string) {
        const authUrl = this.apiAxios!.defaults.baseURL + "/endsession";
        window.location.href = `${authUrl}?post_logout_redirect_uri=${redirectUri}`;
    }

    public me(callback: (user: User) => void) {
        this.apiAxios!
            .get("/users/me")
            .then((response: any) => {
                callback(new User(response.data.resource));
            })
            .catch((error: any) => {
                this.checkError(error);
            });
    }

    public get(axios: AxiosInstance, url: string, params: any, callback: (data: any) => void): ApiRequest {
        const request = new ApiRequest();
        axios
            .get(url, {
                params: Object.assign({}, params),
                cancelToken: request.getCancelToken()
            })
            .then((response: any) => {
                callback(response.data);
            })
            .catch((error: any) => {
                this.checkError(error);
                callback(error.response ?? error);
            });
        return request;
    }

    public post(axios: AxiosInstance, url: string, data: any, params: string[][], callback: (data: any) => void): ApiRequest {
        const request = new ApiRequest();
        axios
            .post(url, data, {
                params: Object.assign({}, params),
                cancelToken: request.getCancelToken()
            })
            .then((response: any) => {
                callback(response.data);
            })
            .catch((error: any) => {
                this.checkError(error);
                callback(error.response ?? error);
            });
        return request;
    }

    public put(axios: AxiosInstance, url: string, data: any, params: string[][], callback: (data: any) => void): ApiRequest {
        const request = new ApiRequest();
        axios
            .put(url, data, {
                params: Object.assign({}, params),
                cancelToken: request.getCancelToken()
            })
            .then((response: any) => {
                callback(response.data);
            })
            .catch((error: any) => {
                this.checkError(error);
                callback(error.response ?? error);
            });
        return request;
    }

    public patch(axios: AxiosInstance, url: string, data: any, params: string[][], callback: (data: any) => void): ApiRequest {
        const request = new ApiRequest();
        axios
            .patch(url, data, {
                params: Object.assign({}, params),
                cancelToken: request.getCancelToken()
            })
            .then((response: any) => {
                callback(response.data);
            })
            .catch((error: any) => {
                this.checkError(error);
                callback(error.response ?? error);
            });
        return request;
    }

    public delete(axios: AxiosInstance, url: string, params: string[][], callback: (data: any) => void): ApiRequest {
        const request = new ApiRequest();
        axios
            .delete(url, {
                params: Object.assign({}, params),
                cancelToken: request.getCancelToken()
            })
            .then((response: any) => {
                callback(response.data);
            })
            .catch((error: any) => {
                this.checkError(error);
                callback(error.response ?? error);
            });
        return request;
    }

    public download(axios: AxiosInstance, url: string, params: string[][], fileName: string, callback?: (data: any) => void): ApiRequest {
        const request = new ApiRequest();
        axios(
            {
                url: url,
                params: Object.assign({}, params),
                cancelToken: request.getCancelToken(),
                method: 'GET',
                responseType: 'blob',
            })
            .then((response: any) => {
                const url = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', fileName);
                document.body.appendChild(link);
                link.click();
                if (callback) {
                    callback(response.data);
                }
            })
            .catch((error: any) => {
                this.checkError(error);
                if (callback) {
                    callback(error.response ?? error);
                }
            });
        return request;
    }

    public upload(axios: AxiosInstance, url: string, data: any, params: string[][], callback?: (data: any) => void) {
        const request = new ApiRequest();
        axios.post(
            url,
            data,
            {
                headers: {
                    'Content-Type': 'multipart/form-data'
                },
                params: Object.assign({}, params),
                cancelToken: request.getCancelToken(),
            }).then((response: any) => {
            if (callback) {
                callback(response.data);
            }
        })
            .catch((error: any) => {
                this.checkError(error);
                if (callback) {
                    callback(error.response ?? error);
                }
            });
        return request;
    }

    private checkError(error: any) {
        // console.warn(error, error.response);
        if (error.response && error.response.status) {
            switch (error.response.status) {
                case 401:
                    AuthService.handleLogout();
                    break;
                case 500:
                    if (error.response.data.message) {
                        // bus.$emit("toast", error.response.data.message, "red");
                    }
                    break;
            }
        }
    }
}

export default new ApiService();
