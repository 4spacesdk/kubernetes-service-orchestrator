import ApiService from '@/services/ApiService';
import type {User} from "@/core/services/Deploy/models";

class AuthService {
    public currentAuthUser?: User;

    constructor() {
        console.info('AuthService');
    }

    public isLoggedIn() {
        return (this.getToken()?.length ?? 0) > 0;
    }

    public handleLogin() {
        const redirectUri = `${location.origin}/app/login`;
        const grantType = 'authorization_code';
        const clientId = 'webclient';
        const scope = 'openid offline_access';

        // Create Code Verifier
        const array = new Uint32Array(56 / 2);
        window.crypto.getRandomValues(array);
        const codeVerifier = Array.from(array, (dec: number) => {
            const txt = ('0' + dec.toString(16));
            return txt.substring(txt.length - 2);
        }).join('');
        localStorage.setItem('last-code-verifier', codeVerifier);

        // Create Code Challenge
        const encoder = new TextEncoder();
        const data = encoder.encode(codeVerifier);
        window.crypto.subtle.digest('SHA-256', data)
            .then(hashed => {
                let str = "";
                const bytes = new Uint8Array(hashed);
                const len = bytes.byteLength;
                for (let i = 0; i < len; i++) {
                    str += String.fromCharCode(bytes[i]);
                }
                const codeChallenge = btoa(str)
                    .replace(/\+/g, "-")
                    .replace(/\//g, "_")
                    .replace(/=+$/, "");

                ApiService.redirectToLogin(redirectUri, grantType, clientId, scope, codeVerifier, codeChallenge);
            });
    }

    public exchangeCodeForAccessToken(code: string, onFinish: () => void) {
        const redirectUri = `${location.origin}/app/login`;
        const grantType = 'authorization_code';
        const clientId = 'webclient';
        const codeVerifier = localStorage.getItem('last-code-verifier');

        ApiService.callTokenEndpoint(
            redirectUri,
            grantType,
            clientId,
            codeVerifier!,
            code,
            accessToken => {
                this.setToken(accessToken, true);
                onFinish();
            },
            error => onFinish()
        );
    }

    public checkForAccessTokenInUrl(url: string) {
        if (url.length && url.includes('access_token')) {
            const hash = url.substring(1);
            hash.split('&').forEach(value => {
                const name = value.split('=')[0];
                if (name == 'access_token') {
                    const accessToken = value.split('=')[1];
                    this.setToken(accessToken, false);
                }
            });
        }
    }

    public handleLogout() {
        // Remove token
        localStorage.removeItem('access_token');
        ApiService.removeHeader();

        const redirectUri = `${location.origin}/app/login`;
        ApiService.redirectToLogout(redirectUri);
    }

    public getToken(): string {
        return localStorage.getItem('access_token') as string;
    }

    public setToken(accessToken: string, refreshMe: boolean) {
        localStorage.setItem('access_token', accessToken);
        ApiService.setHeader();
        if (refreshMe) {
            this.refreshMe();
        }
    }

    public refreshMe(callback?: (response: User) => void) {
        const finish = () => {
            // bus.$emit(`MeRefreshed`, this.currentAuthUser);
            if (callback) {
                callback(this.currentAuthUser!);
            }
        }

        if (this.getToken()) {
            ApiService.me(me => {
                this.currentAuthUser = me;
                finish();
            });
        } else {
            finish();
        }
    }

    private isTryingRefresh = false;
    private refreshQuery: ((value: boolean) => void)[] = [];
    public lockAndRefreshToken(callback: (value: boolean) => void): void {
        if (this.isTryingRefresh) {
            // Wait while other request finishes refresh
            this.refreshQuery.push(callback);
        } else {
            this.isTryingRefresh = true;
            ApiService.callRefreshEndpoint(
                'refresh_token',
                'webclient',
                'openid offline_access',
                accessToken => {
                    this.setToken(accessToken, false);
                    this.isTryingRefresh = false;
                    callback(true);
                    this.refreshQuery.forEach(item => item(true));
                },
                error => {
                    this.isTryingRefresh = false;
                    callback(false);
                    this.refreshQuery.forEach(item => item(false));
                }
            )
        }
    }
}

export default new AuthService();
