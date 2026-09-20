<?php namespace App\Controllers;

use App\Entities\User;
use App\Helpers\Client;
use App\Libraries\MFALib;
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
     */
    private const string RememberedDestination = 'request_url';

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
        if (!$requestUrl) {
            $requestUrl = getFrontendUrl();
        }
        session()->setFlashdata(self::RememberedDestination, $requestUrl);
        $data['requestUrl'] = $requestUrl;

        if ($this->request->getPostGet('error_message')) {
            $data['loginResponse'] = $this->request->getPostGet('error_message');
        }

        if ($_POST) {

            // Check credentials
            $username = $this->request->getPost('username');
            $password = $this->request->getPost('password');

            $loginResponse = AuthExtension::checkLoginWithUsernamePassword($username, $password);
            $data['loginResponse'] = $loginResponse;
            switch ($loginResponse) {
                case LoginResponse::Success:
                    /** @var User $user */
                    $user = (new UserModel())
                        ->where('username', $username)
                        ->find();
                    if ($user->hasMFASecret()) {
                        session()->set('2fa_in_progress', $username);
                        $this->response->redirect(base_url('login/twoFactor'));
                    } else {
                        AuthExtension::saveUserSession($user->id);
                        $this->response->redirect($data['requestUrl']);
                    }
                    break;

                case LoginResponse::RenewPassword:
                    /** @var User $user */
                    $user = (new UserModel())
                        ->where('username', $username)
                        ->find();
                    if ($user->hasMFASecret()) {
                        session()->set('2fa_in_progress', $username);
                        $this->response->redirect(base_url('login/twoFactor'));
                    } else {
                        AuthExtension::saveUserSession($user->id);
                        $this->response->redirect(base_url('login/renewPassword'));
                    }
                    break;

                case LoginResponse::WrongPassword:
                    $data['loginResponse'] = 'Wrong password';
                    break;

                case LoginResponse::UnknownUser:
                    $data['loginResponse'] = 'Unknown username';
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

    public function twoFactor(): string|ResponseInterface {
        /** @var string $requestUrl */
        $requestUrl = session()->getFlashdata(self::RememberedDestination);
        if (!$requestUrl) {
            $requestUrl = getFrontendUrl();
        }

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

        if ($_POST) {

            $code = $this->request->getPost('code');
            $mfaLib = new MFALib();
            $verify = $mfaLib->verifyCode($user->getMFASSecret(), $code);
            if ($verify) {
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
                $data['error'] = 'Failed to verify code. Try again.';
            }
        }

        session()->setFlashdata(self::RememberedDestination, $requestUrl);
        return view('Login/MFA', $data);
    }

    public function success(): void {
        $user = AuthExtension::checkSession();
        if ($user) {
            echo 'Success - Welcome ' . $user->name();
        } else {
            $this->response->redirect(base_url('/login'));
        }
    }

    public function renewPassword(): string|ResponseInterface {
        /** @var string $requestUrl */
        $requestUrl = session()->getFlashdata(self::RememberedDestination);
        if (!$requestUrl) {
            $requestUrl = getFrontendUrl();
        }

        if ($_POST) {

            $password = $this->request->getPost('password');
            $passwordConfirm = $this->request->getPost('password_confirm');

            if ($password == $passwordConfirm) {

                $passError = $this->firstUnsatisfiedPasswordRule((string) $password);

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
     * The first rule a new password does not satisfy, or null when it satisfies all four.
     *
     * First rather than last. The four checks used to write to one variable without
     * stopping, so the message named whichever rule was checked last - and "At least one
     * letter" could never be it, because anything without a letter has no capital either
     * and the capital was checked afterwards.
     *
     * The order is from the most basic complaint to the most specific, so that a password
     * failing several rules is told the one worth fixing first.
     */
    private function firstUnsatisfiedPasswordRule(string $password): ?string {
        if (strlen($password) < 8) {
            return 'At least eight characters';
        }
        if (!preg_match("#[a-zA-Z]+#", $password)) {
            return 'At least one letter';
        }
        if (!preg_match("#[0-9]+#", $password)) {
            return 'At least one number';
        }
        if (!preg_match("#[A-Z]+#", $password)) {
            return 'At least one uppercase letter';
        }

        return null;
    }

    public function forgotPassword(): string {
        session()->setFlashdata(self::RememberedDestination, session()->getFlashdata(self::RememberedDestination));

        if ($this->request->getPost('username')) {
            /** @var User $user */
            $user = (new \App\Models\UserModel())
                ->where('username', $this->request->getPost('username'))
                ->find();

            if ($user->exists()) {
                $user->sendForgotPasswordEmail();
                Data::set('success', true);
                Data::set('message', 'Check your e-mail inbox');
            } else {
                Data::set('success', false);
                Data::set('message', 'Unknown e-mail');
            }
        } else {
            Data::set('success', false);
        }

        return view('Login/ForgotPassword');
    }

}
