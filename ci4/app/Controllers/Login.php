<?php namespace App\Controllers;

use App\Entities\User;
use App\Helpers\Client;
use App\Libraries\MFALib;
use AuthExtension\AuthExtension;
use AuthExtension\Config\LoginResponse;
use AuthExtension\Models\UserModel;
use DebugTool\Data;

class Login extends \App\Core\BaseController {

    public function requireAuth(string $method): bool {
        return false;
    }

    public function index() {
        $data = [];
        $scopes = '';

        $requestUrl = session()->getFlashdata('requestUrl');
        if ($this->request->getGet('request_uri')) {
            $requestUrl = $this->request->getGet('request_uri');
        }
        if ($this->request->getGet('redirect_uri')) {
            $requestUrl = $this->request->getGet('redirect_uri');
        }
        if (!$requestUrl) {
            $requestUrl = getFrontendUrl();
        }
        session()->setFlashdata('requestUrl', $requestUrl);
        $data['requestUrl'] = $requestUrl;

        if ($this->request->getPostGet('error_message')) {
            $data['loginResponse'] = $this->request->getPostGet('error_message');
        }

        if ($_POST) {

            // Check credentials
            $username = $this->request->getPost('username');
            $password = $this->request->getPost('password');

            $loginResponse = AuthExtension::checkLoginWithUsernamePassword($username, $password, $scopes);
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

                case LoginResponse::WrongScope:
                    $data['loginResponse'] = "You don't have access to this site";
                    break;
            }

        }

        return view('Login/Login', $data);
    }

    public function twoFactor(): string {
        /** @var string $requestUrl */
        $requestUrl = session()->getFlashdata('request_url');
        if (!$requestUrl) {
            $requestUrl = getFrontendUrl();
        }

        $username = session()->get('2fa_in_progress');
        if (!$username) {
            $this->response->redirect(base_url('login') . '?error_message=Two factor authentication not initialized.');
            $this->response->send();
            exit;
        }

        /** @var User $user */
        $user = (new UserModel())
            ->where('username', $username)
            ->find();
        if (!$user->exists()) {
            $this->response->redirect(base_url('login') . "?error_message=Unknown username ({$username}).");
            $this->response->send();
            exit;
        }


        $data = [

        ];

        if ($_POST) {

            $code = $this->request->getPost('code');
            $mfaLib = new MFALib();
            $verify = $mfaLib->verifyCode($user->getMFASSecret(), $code);
            if ($verify) {
                unset($_SESSION[ '2fa_in_progress' ]);
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

        session()->setFlashdata('request_url', $requestUrl);
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

    public function renewPassword(): string {
        /** @var string $requestUrl */
        $requestUrl = session()->getFlashdata('request_url');
        if (!$requestUrl) {
            $requestUrl = getFrontendUrl();
        }

        if ($_POST) {

            $password = $this->request->getPost('password');
            $passwordConfirm = $this->request->getPost('password_confirm');

            if ($password == $passwordConfirm) {

                if (strlen($password) < 8) {
                    $passError = 'At least eight characters';
                }

                if (!preg_match("#[0-9]+#", $password)) {
                    $passError = 'At least one number';
                }

                if (!preg_match("#[a-zA-Z]+#", $password)) {
                    $passError = 'At least one letter';
                }

                if (!preg_match("#[A-Z]+#", $password)) {
                    $passError = 'At least one uppercase letter';
                }

                if (!isset($passError)) {

                    $user = AuthExtension::checkSession();
                    if ($user) {
                        $user->password = User::encryptPassword($password);
                        $user->renew_password = false;
                        $user->save();

                        $this->response->redirect($requestUrl);
                        $this->response->send();
                        exit;
                    }

                } else {
                    Data::set('description', $passError);
                }

            } else {
                Data::set('description', 'Must be identical');
            }
        }

        session()->setFlashdata('request_url', $requestUrl);

        return view('Login/PasswordRenewal', Data::getStore());
    }

    public function forgotPassword(): string {
        session()->setFlashdata('request_url', session()->getFlashdata('request_url'));

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
