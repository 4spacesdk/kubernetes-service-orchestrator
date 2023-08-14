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
        const grantType = 'implicit';
        const clientId = 'webclient';
        ApiService.redirectToLogin(redirectUri, grantType, clientId);
    }

    public checkForAccessTokenInUrl(url: string) {
        if (url.length && url.includes('access_token')) {
            const hash = url.substr(1);
            hash.split('&').forEach(value => {
                const name = value.split('=')[0];
                if (name == 'access_token') {
                    const accessToken = value.split('=')[1];
                    this.setToken(accessToken);
                }
            });
        }
    }

    public handleLogout() {
        // Remove token
        this.setToken('');
 
        const redirectUri = `${location.origin}/app/login`;
        ApiService.redirectToLogout(redirectUri);
    }

    public getToken(): string {
        return localStorage.getItem('access_token') as string;
    }

    public setToken(accessToken: string) {
        localStorage.setItem('access_token', accessToken);
        ApiService.setHeader();
        this.refreshMe();
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
}

export default new AuthService();
