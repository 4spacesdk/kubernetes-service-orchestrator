<?php namespace App\Controllers;

use App\Entities\User;
use App\Helpers\Client;
use App\Libraries\MFALib;
use App\Libraries\LoginThrottle;
use AuthExtension\AuthExtension;
use AuthExtension\Config\LoginResponse;
use CodeIgniter\HTTP\ResponseInterface;
use AuthExtension\Models\UserModel;
use DebugTool\Data;

class Login extends \App\Core\BaseController {

    /**
     * The session key the destination is remembered under, from the form through the second
     * factor and the renewal.
     *
     * A constant because it was spelt `requestUrl` in `index()` and `request_url` in every
     * other method here, so the two never met: an operator who followed a deep link and has
     * a second factor was always delivered to the frontend's front page instead.
     *
     * `requestUrl` because that is what the auth extension's `/authorize` writes before it
     * sends a visitor here, so that an OAuth sign-in returns to `/authorize` to finish.
     */
    private const string RememberedDestination = 'requestUrl';

    private const string WrongCredentials = 'Wrong username or password';

    private const string TooManyAttempts = 'Too many failed attempts. Try again in 15 minutes.';

    /**
     * A bcrypt hash of nothing in particular, checked against when the username is unknown
     * so that the answer takes as long as for a known one. Cost 12, PHP's default and so what
     * `encryptPassword()` writes today; it has to be a real hash, or the check returns at once.
     */
    private const string TimingHash = '$2y$12$YkTz.ciIkiXhpJBmrl28yup.D0e2KsDFRxE/DZay3UwAzDOOFBuGa';

    public function requireAuth(string $method): bool {
        return false;
    }

    public function index() {
        $data = [];

        $requestUrl = session()->getFlashdata(self::RememberedDestination);
        if ($this->request->getGet('request_uri')) {
            $requestUrl = $this->request->getGet('request_uri');
        }
        if ($this->request->getGet('redirect_uri')) {
            $requestUrl = $this->request->getGet('redirect_uri');
        }
        $requestUrl = self::SafeDestination($requestUrl);
        session()->setFlashdata(self::RememberedDestination, $requestUrl);
        $data['requestUrl'] = $requestUrl;

        if ($this->request->getPostGet('error_message')) {
            $data['loginResponse'] = $this->request->getPostGet('error_message');
        }

        if ($_POST) {

            // Check credentials
            $username = (string) $this->request->getPost('username');
            $password = (string) $this->request->getPost('password');

            // Back in the form when the attempt is refused, so only the password is typed again.
            // It is what the visitor wrote, whether or not such a user exists - the form still
            // says nothing about which ones do.
            $data['username'] = $username;

            /** @var User $user */
            $user = (new UserModel())
                ->where('username', $username)
                ->find();
            $userId = $user->exists() ? (int) $user->id : null;

            // Refused before the password is looked at, so a refused attempt cannot be used
            // to test one either.
            if (LoginThrottle::IsRefused(LoginThrottle::Password, $username)) {
                LoginThrottle::RecordRefusal(LoginThrottle::Password, $username, $userId);
                $data['loginResponse'] = self::TooManyAttempts;
                return view('Login/Login', $data);
            }

            $loginResponse = AuthExtension::checkLoginWithUsernamePassword($username, $password);
            $data['loginResponse'] = $loginResponse;
            if ($loginResponse === LoginResponse::Success || $loginResponse === LoginResponse::RenewPassword) {
                LoginThrottle::RecordSuccess(LoginThrottle::Password, $username, $userId);
            }
            switch ($loginResponse) {
                case LoginResponse::Success:
                    if ($user->hasMFASecret()) {
                        session()->set('2fa_in_progress', $username);
                        $this->response->redirect(base_url('login/twoFactor'));
                    } else {
                        AuthExtension::saveUserSession($user->id);
                        $this->response->redirect($data['requestUrl']);
                    }
                    break;

                case LoginResponse::RenewPassword:
                    if ($user->hasMFASecret()) {
                        session()->set('2fa_in_progress', $username);
                        $this->response->redirect(base_url('login/twoFactor'));
                    } else {
                        AuthExtension::saveUserSession($user->id);
                        $this->response->redirect(base_url('login/renewPassword'));
                    }
                    break;

                // One answer for both, so the form does not say which usernames exist.
                case LoginResponse::UnknownUser:
                    // A known username costs a bcrypt check and an unknown one cost nothing,
                    // so the time taken said it instead. Now both pay.
                    password_verify($password, self::TimingHash);
                    // Falls through.
                case LoginResponse::WrongPassword:
                    LoginThrottle::RecordFailure(LoginThrottle::Password, $username, $userId);
                    $data['loginResponse'] = self::WrongCredentials;
                    break;

                // kso asks for no scope at all - the argument above used to be an empty
                // string that was never assigned - so the authorisation check inside
                // `checkLoginWithUsernamePassword()` does not run and this cannot be
                // reached today. Kept so that asking for one later is a one-line change
                // rather than a raw constant shown to the user.
                case LoginResponse::WrongScope:
                    $data['loginResponse'] = "You don't have access to this site";
                    break;
            }

        }

        return view('Login/Login', $data);
    }

    /**
     * Where a sign-in may send the visitor afterwards: a path on this host, or an address
     * on the API's or the frontend's own origin. Anything else - including nothing - is the
     * frontend's front page.
     *
     * The destination comes from the query string of a link anyone can write, and the
     * visitor has just typed their password into kso's own page, so without this the link
     * could hand a freshly signed-in operator to any site at all.
     *
     * Checked where it is read rather than where it is stored, so a destination that got
     * into the session some other way is held to the same rule.
     */
    private static function SafeDestination(mixed $url): string {
        if (!is_string($url) || $url === '' || preg_match('/[\\\\\x00-\x20\x7f]/', $url)) {
            return getFrontendUrl();
        }

        // A path. `//host` is not one: a browser reads it as an address on another host.
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        $origin = static function(string $url): ?string {
            $parts = parse_url($url);
            if (!is_array($parts) || !isset($parts['host'])
                || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
                return null;
            }
            return strtolower("{$parts['scheme']}://{$parts['host']}") . (isset($parts['port']) ? ":{$parts['port']}" : '');
        };

        $allowed = [$origin(base_url()), $origin(getFrontendUrl())];
        $wanted = $origin($url);

        return $wanted !== null && in_array($wanted, $allowed, true) ? $url : getFrontendUrl();
    }

    public function twoFactor(): string|ResponseInterface {
        $requestUrl = self::SafeDestination(session()->getFlashdata(self::RememberedDestination));

        $username = session()->get('2fa_in_progress');
        if (!$username) {
            // Returned rather than sent and `exit`ed. Ending the process here skips
            // CodeIgniter's shutdown, and with it `post_system` - which is what writes
            // RestExtension's access log entry, so exactly these responses were missing
            // from it. Same reason as in `BaseController::fail()`.
            return $this->response->redirect(base_url('login') . '?error_message=Two factor authentication not initialized.');
        }

        /** @var User $user */
        $user = (new UserModel())
            ->where('username', $username)
            ->find();
        if (!$user->exists()) {
            return $this->response->redirect(base_url('login') . "?error_message=Unknown username ({$username}).");
        }


        $data = [

        ];

        if ($_POST && LoginThrottle::IsRefused(LoginThrottle::Code, $username)) {
            LoginThrottle::RecordRefusal(LoginThrottle::Code, $username, (int) $user->id);
            $data['error'] = self::TooManyAttempts;
        } else if ($_POST) {

            $code = $this->request->getPost('code');
            $mfaLib = new MFALib();
            $verify = $mfaLib->verifyCode($user->getMFASSecret(), $code);
            if ($verify) {
                LoginThrottle::RecordSuccess(LoginThrottle::Code, $username, (int) $user->id);
                // Through the session library rather than `unset($_SESSION[...])`, which
                // reaches around whatever handler is configured and leaves the session's
                // own bookkeeping thinking the key is still there.
                session()->remove('2fa_in_progress');
                AuthExtension::saveUserSession($user->id);
                if ($user->renew_password) {
                    $this->response->redirect(base_url('login/renewPassword'));
                } else {
                    $this->response->redirect($requestUrl);
                }
            } else {
                LoginThrottle::RecordFailure(LoginThrottle::Code, $username, (int) $user->id);
                $data['error'] = 'Failed to verify code. Try again.';
            }
        }

        session()->setFlashdata(self::RememberedDestination, $requestUrl);
        return view('Login/MFA', $data);
    }

    public function success(): void {
        $user = AuthExtension::checkSession();
        if ($user) {
            echo 'Success - Welcome ' . esc($user->name());
        } else {
            $this->response->redirect(base_url('/login'));
        }
    }

    public function renewPassword(): string|ResponseInterface {
        $requestUrl = self::SafeDestination(session()->getFlashdata(self::RememberedDestination));

        if ($_POST) {

            $password = $this->request->getPost('password');
            $passwordConfirm = $this->request->getPost('password_confirm');

            if ($password == $passwordConfirm) {

                $passError = User::FirstUnsatisfiedPasswordRule((string) $password);

                if ($passError === null) {

                    $user = AuthExtension::checkSession();
                    if ($user) {
                        $user->password = User::encryptPassword($password);
                        $user->renew_password = false;
                        $user->save();

                        // See the note in `twoFactor()`: returned rather than sent and
                        // `exit`ed, so the framework shuts down and the response is logged.
                        return $this->response->redirect($requestUrl);
                    }

                    // Nobody to change the password of. The form used to come back with no
                    // message at all, which is indistinguishable from a password that was
                    // accepted - so a session that had quietly expired looked like a
                    // renewal that had quietly worked.
                    Data::set('description', 'Your sign-in has expired. Sign in again.');

                } else {
                    Data::set('description', $passError);
                }

            } else {
                Data::set('description', 'Must be identical');
            }
        }

        session()->setFlashdata(self::RememberedDestination, $requestUrl);

        return view('Login/PasswordRenewal', Data::getStore());
    }

    /**
     * The same answer whether or not the address has an account, so the form cannot be used
     * to find out which do.
     */
    private const string PasswordResetRequested = 'If the address belongs to an account, a link to choose a new password is on its way.';

    public function forgotPassword(): string {
        session()->setFlashdata(self::RememberedDestination, session()->getFlashdata(self::RememberedDestination));

        if ($this->request->getPost('username')) {
            /** @var User $user */
            $user = (new \App\Models\UserModel())
                ->where('username', $this->request->getPost('username'))
                ->find();

            if ($user->exists()) {
                // Logged rather than shown: saying the mail could not be sent would say
                // that there was someone to send it to.
                try {
                    $user->sendPasswordResetEmail();
                } catch (\Throwable $e) {
                    log_message('error', 'Password reset mail for user {id} not sent: {message}', [
                        'id' => $user->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            Data::set('success', true);
            Data::set('message', self::PasswordResetRequested);
        } else {
            Data::set('success', false);
        }

        return view('Login/ForgotPassword');
    }

    /**
     * Where the link in the mail leads: choose a new password, twice, held to the same rules
     * as a renewal. The token is spent when the password is set, and the visitor is sent to
     * sign in with it - through the second factor, if they have one.
     */
    public function resetPassword(): string|ResponseInterface {
        $user = User::FindByPasswordResetToken((string) $this->request->getGet('token'));
        if ($user === null) {
            Data::set('message', 'This link has expired or has already been used. Ask for a new one.');
            return view('Login/ForgotPassword');
        }

        if ($_POST) {
            $password = (string) $this->request->getPost('password');

            if ($password !== (string) $this->request->getPost('password_confirm')) {
                Data::set('description', 'Must be identical');
            } else if (($passError = User::FirstUnsatisfiedPasswordRule($password)) !== null) {
                Data::set('description', $passError);
            } else {
                $user->resetPassword($password);
                return $this->response->redirect(base_url('login') . '?error_message=' . urlencode('Your password has been changed. Sign in with it.'));
            }
        }

        return view('Login/PasswordRenewal', Data::getStore());
    }

}
