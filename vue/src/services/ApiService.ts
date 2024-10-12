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
                    AuthService.setToken(accessToken, false);
                }

                if (AuthService.getToken()) {
                    this.setHeader();
                }

                System.Instance = new System(data.system);
                System.certManagerIssuerDefaultName = data.certManagerIssuerDefaultName;
                System.imagePullSecretDefaultName = data.imagePullSecretDefaultName;
                this.pushServiceUrl = data.pushServiceUrl;

                // All done
                callback();
            })
            .catch((error: any) => {
                this.checkError(error);
            });
    }

    public setHeader() {
        this.apiAxios!.defaults.headers.common["Authorization"] = `Bearer ${AuthService.getToken()}`;
    }

    public removeHeader() {
        this.apiAxios!.defaults.headers.common = {};
    }

    public redirectToLogin(redirectUri: string, grantType: string, clientId: string,
                           scope: string, codeVerifier: string, codeChallenge: string) {
        const authUrl = this.apiAxios!.defaults.baseURL + "/authorize";
        window.location.href = `${authUrl}`
            + `?grant_type=${grantType}`
            + `&redirect_uri=${redirectUri}`
            + `&client_id=${clientId}`
            + `&code_verifier=${codeVerifier}`
            + `&code_challenge=${codeChallenge}`
            + `&code_challenge_method=S256`
            + `&response_type=code`
            + `&state=nonce`
            + `&scope=${scope}`;
    }

    public redirectToLogout(redirectUri: string) {
        const authUrl = this.apiAxios!.defaults.baseURL + "/endsession";
        window.location.href = `${authUrl}?post_logout_redirect_uri=${redirectUri}`;
    }

    public callTokenEndpoint(redirectUri: string, grantType: string, clientId: string,
                             codeVerifier: string, code: string,
                             onSuccess: (accessToken: string) => void,
                             onFailure: (error: string) => void) {
        const authUrl = this.apiAxios!.defaults.baseURL + "/oauth-agent/token";
        const body = `grant_type=${grantType}`
            + `&redirect_uri=${redirectUri}`
            + `&client_id=${clientId}`
            + `&code_verifier=${codeVerifier}`
            + `&code=${code}`;
        const xhr = new XMLHttpRequest();
        xhr.withCredentials = true;
        xhr.open('POST', authUrl);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onload = () => {
            if (xhr.readyState == 4 && xhr.status == 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.access_token) {
                    onSuccess(response.access_token);
                }
                if (response.error) {
                    onFailure(response.error);
                }
            } else {
                console.warn(`Error: ${xhr.status}`);
                onFailure(xhr.responseText);
            }
        };
        xhr.send(body);
    }

    public callRefreshEndpoint(grantType: string, clientId: string, scope: string,
                               onSuccess: (accessToken: string) => void,
                               onFailure: (error: string) => void) {
        const authUrl = this.apiAxios!.defaults.baseURL + "/oauth-agent/refresh";
        const body = `grant_type=${grantType}`
            + `&client_id=${clientId}`
            + `&scope=${scope}`;
        const xhr = new XMLHttpRequest();
        xhr.withCredentials = true;
        xhr.open('POST', authUrl);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onload = () => {
            if (xhr.readyState == 4 && xhr.status == 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.access_token) {
                    onSuccess(response.access_token);
                }
                if (response.error) {
                    onFailure(response.error);
                }
            } else {
                console.warn(`Error: ${xhr.status}`);
                onFailure(xhr.responseText);
            }
        };
        xhr.send(body);
    }

    public redirectByPost(url: string, parameters: object, inNewTab: boolean) {
        const form = document.createElement("form");
        form.id = "reg-form";
        form.name = "reg-form";
        form.action = url;
        form.method = "post";
        form.enctype = "multipart/form-data";

        if (inNewTab) {
            form.target = "_blank";
        }

        Object.keys(parameters).forEach(function (key) {
            const input = document.createElement("input");
            input.type = "text";
            input.name = key;
            input.value = (parameters as any)[key];
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);

        return false;
    }

    public me(callback: (user: User) => void, retry: number = 1) {
        this.apiAxios!
            .get("/users/me")
            .then((response: any) => {
                callback(new User(response.data.resource));
            })
            .catch((error: any) => {
                const checkError = () => {
                    if (this.checkError(error)) {
                        if (callback) {
                            callback(error.response.data);
                        }
                    }
                };

                if (retry > 0) {
                    this.checkForRetry(error, shouldRetry => {
                        if (shouldRetry) {
                            this.me(callback, retry--);
                        } else {
                            checkError();
                        }
                    });
                } else {
                    checkError();
                }
            });
    }

    public get(request: ApiRequest, axios: AxiosInstance, url: string, params: any, callback: (data: any) => void, retry: number = 1, withCredentials: boolean): ApiRequest {
        axios
            .get(url, {
                params: Object.assign({}, params),
                cancelToken: request.getCancelToken(),
                withCredentials: withCredentials,
            })
            .then((response: any) => {
                callback(response.data);
            })
            .catch((error: any) => {
                const checkError = () => {
                    if (this.checkError(error)) {
                        if (callback) {
                            callback(error.response.data);
                        }
                    }
                };

                if (retry > 0) {
                    this.checkForRetry(error, shouldRetry => {
                        if (shouldRetry) {
                            this.get(request, axios, url, params, callback, retry--, withCredentials);
                        } else {
                            checkError();
                        }
                    });
                } else {
                    checkError();
                }
            });
        return request;
    }

    public post(request: ApiRequest, axios: AxiosInstance, url: string, data: any, params: string[][], callback: (data: any) => void, retry: number = 1, withCredentials: boolean): ApiRequest {
        axios
            .post(url, data, {
                params: Object.assign({}, params),
                cancelToken: request.getCancelToken(),
                withCredentials: withCredentials,
            })
            .then((response: any) => {
                callback(response.data);
            })
            .catch((error: any) => {
                const checkError = () => {
                    if (this.checkError(error)) {
                        if (callback) {
                            callback(error.response.data);
                        }
                    }
                };

                if (retry > 0) {
                    this.checkForRetry(error, shouldRetry => {
                        if (shouldRetry) {
                            this.post(request, axios, url, data, params, callback, retry--, withCredentials);
                        } else {
                            checkError();
                        }
                    });
                } else {
                    checkError();
                }
            });
        return request;
    }

    public put(request: ApiRequest, axios: AxiosInstance, url: string, data: any, params: string[][], callback: (data: any) => void, retry: number = 1, withCredentials: boolean): ApiRequest {
        axios
            .put(url, data, {
                params: Object.assign({}, params),
                cancelToken: request.getCancelToken(),
                withCredentials: withCredentials,
            })
            .then((response: any) => {
                callback(response.data);
            })
            .catch((error: any) => {
                const checkError = () => {
                    if (this.checkError(error)) {
                        if (callback) {
                            callback(error.response.data);
                        }
                    }
                };

                if (retry > 0) {
                    this.checkForRetry(error, shouldRetry => {
                        if (shouldRetry) {
                            this.put(request, axios, url, data, params, callback, retry--, withCredentials);
                        } else {
                            checkError();
                        }
                    });
                } else {
                    checkError();
                }
            });
        return request;
    }

    public patch(request: ApiRequest, axios: AxiosInstance, url: string, data: any, params: string[][], callback: (data: any) => void, retry: number = 1, withCredentials: boolean): ApiRequest {
        axios
            .patch(url, data, {
                params: Object.assign({}, params),
                cancelToken: request.getCancelToken(),
                withCredentials: withCredentials,
            })
            .then((response: any) => {
                callback(response.data);
            })
            .catch((error: any) => {
                const checkError = () => {
                    if (this.checkError(error)) {
                        if (callback) {
                            callback(error.response.data);
                        }
                    }
                };

                if (retry > 0) {
                    this.checkForRetry(error, shouldRetry => {
                        if (shouldRetry) {
                            this.patch(request, axios, url, data, params, callback, retry--, withCredentials);
                        } else {
                            checkError();
                        }
                    });
                } else {
                    checkError();
                }
            });
        return request;
    }

    public delete(request: ApiRequest, axios: AxiosInstance, url: string, params: string[][], callback: (data: any) => void, retry: number = 1, withCredentials: boolean): ApiRequest {
        axios
            .delete(url, {
                params: Object.assign({}, params),
                cancelToken: request.getCancelToken(),
                withCredentials: withCredentials,
            })
            .then((response: any) => {
                callback(response.data);
            })
            .catch((error: any) => {
                const checkError = () => {
                    if (this.checkError(error)) {
                        if (callback) {
                            callback(error.response.data);
                        }
                    }
                };

                if (retry > 0) {
                    this.checkForRetry(error, shouldRetry => {
                        if (shouldRetry) {
                            this.delete(request, axios, url, params, callback, retry--, withCredentials);
                        } else {
                            checkError();
                        }
                    });
                } else {
                    checkError();
                }
            });
        return request;
    }

    public download(request: ApiRequest, axios: AxiosInstance, url: string, params: string[][], fileName: string, callback?: (data: any) => void, retry: number = 1): ApiRequest {
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
                const checkError = () => {
                    if (this.checkError(error)) {
                        if (callback) {
                            callback(error.response.data);
                        }
                    }
                };

                if (retry > 0) {
                    this.checkForRetry(error, shouldRetry => {
                        if (shouldRetry) {
                            this.download(request, axios, url, params, fileName, callback, retry--);
                        } else {
                            checkError();
                        }
                    });
                } else {
                    checkError();
                }
            });
        return request;
    }

    public upload(request: ApiRequest, axios: AxiosInstance, url: string, data: any, params: string[][], callback?: (data: any) => void, retry: number = 1) {
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
                const checkError = () => {
                    if (this.checkError(error)) {
                        if (callback) {
                            callback(error.response.data);
                        }
                    }
                };

                if (retry > 0) {
                    this.checkForRetry(error, shouldRetry => {
                        if (shouldRetry) {
                            this.upload(request, axios, url, data, params, callback, retry--);
                        } else {
                            checkError();
                        }
                    });
                } else {
                    checkError();
                }
            });
        return request;
    }

    private checkForRetry(error: any, callback: (value: boolean) => void): void {
        if (error.response?.status == 401) {
            AuthService.lockAndRefreshToken(callback);
        } else {
            callback(false);
        }
    }

    private checkError(error: any): boolean {
        if (error.response && error.response.status) {
            switch (error.response.status) {
                case 401:
                    if (error.response.data.error) {
                        if (error.response.data.error == 'RestExtension\\Exceptions\\UnauthorizedException') {
                            AuthService.handleLogout();
                        } else {
                            // bus.$emit('toast', error.response.data.error, 'red');
                        }
                    }
                    break;
                case 500:
                    if (error.response.data.message) {
                        // bus.$emit("toast", error.response.data.message, "red");
                    }
                    if (error.response.data.type) {
                        // 500 error, formatted by CodeIgniter
                        return true;
                    }
                    break;
            }
        }
        return false;
    }
}

export default new ApiService();
