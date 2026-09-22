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

        const state = this.newLoginState();

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

                ApiService.redirectToLogin(redirectUri, grantType, clientId, scope, codeChallenge, state);
            });
    }

    /**
     * A random `state` for the sign-in, kept in this tab until the code comes back with it.
     *
     * It used to be the word `nonce` every time, so the login page could not tell a code it asked
     * for from one somebody else handed it - a link that signs the visitor in as someone else.
     * PKCE already stops such a code from being exchanged here; this stops it one step earlier,
     * and does not depend on PKCE being enforced.
     */
    private newLoginState(): string {
        const bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);
        const state = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
        try {
            sessionStorage.setItem(AuthService.LoginStateKey, state);
        } catch {
            // Without storage the check on the way back refuses, and the sign-in says so.
        }
        return state;
    }

    /**
     * Whether a code that came back with this state is the answer to the sign-in this tab
     * started. The stored state is used once, whatever the answer.
     */
    public isOwnLoginState(state: unknown): boolean {
        let expected: string | null = null;
        try {
            expected = sessionStorage.getItem(AuthService.LoginStateKey);
            sessionStorage.removeItem(AuthService.LoginStateKey);
        } catch {
            return false;
        }
        return typeof state === 'string' && !!expected && state === expected;
    }

    private static readonly LoginStateKey = 'login-state';

    public exchangeCodeForAccessToken(code: string, onFinish: (succeeded: boolean) => void) {
        const redirectUri = `${location.origin}/app/login`;
        const grantType = 'authorization_code';
        const clientId = 'webclient';
        const codeVerifier = localStorage.getItem('last-code-verifier');
        // Used once, whatever the exchange answers: a code can only be exchanged once anyway.
        localStorage.removeItem('last-code-verifier');

        ApiService.callTokenEndpoint(
            redirectUri,
            grantType,
            clientId,
            codeVerifier!,
            code,
            accessToken => {
                this.setToken(accessToken, true);
                onFinish(true);
            },
            error => onFinish(false)
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
            // Out of the url once read, as in ApiService.useAccessTokenFromUrl().
            window.history.replaceState(window.history.state, '', window.location.pathname + window.location.search);
        }
    }

    /**
     * The refresh token and the access token revoked, and the cookie that holds the refresh token
     * cleared, before the sign-out page ends the session - it only ends the session. Then on to
     * sign-out whatever kso answered: the browser is signed out either way.
     */
    public handleLogout() {
        const token = this.getToken();
        localStorage.removeItem('access_token');
        ApiService.removeHeader();

        const redirectUri = `${location.origin}/app/login`;
        ApiService.callLogoutEndpoint(token, () => ApiService.redirectToLogout(redirectUri));
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
