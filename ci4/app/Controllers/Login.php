<?php namespace App\Controllers;

use App\Entities\User;
use AuthExtension\AuthExtension;
use AuthExtension\Config\LoginResponse;
use AuthExtension\Models\UserModel;

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
            $requestUrl = base_url('home'); // Should never happen in production
        }
        session()->setFlashdata('requestUrl', $requestUrl);
        $data['requestUrl'] = $requestUrl;

        if ($_POST) {

            // Check credentials
            $username = $this->request->getPost('username');
            $password = $this->request->getPost('password');

            $loginResponse = AuthExtension::login($username, $password, $scopes);
            $data['loginResponse'] = $loginResponse;
            switch ($loginResponse) {
                case LoginResponse::Success:
                    $this->response->redirect($data['requestUrl']);
                    break;
                case LoginResponse::RenewPassword:

                    /** @var User $user */
                    $user = (new UserModel())
                        ->where('id', session('user_id'))
                        ->find();
                    return $this->renewPassword($user, $scopes);

                case LoginResponse::WrongPassword:

                    // Temporary legacy password check, added in 5.4.0 (2021.01). To be removed at 2021.07.
                    /** @var User $legacyUser */
                    $legacyUser = (new UserModel())
                        ->where('username', $username)
                        ->find();
                    if ($legacyUser->exists() && strlen($legacyUser->password) && substr($legacyUser->password, 1, 1) != '$') {
                        // User has a password and it is not bcrypt. Check for SHA1.
                        if ($legacyUser->password == sha1($password)) {
                            session()->set('user_id', $legacyUser->id);
                            if (!$legacyUser->renew_password) { // Force renew password
                                $legacyUser->renew_password = true;
                                $legacyUser->save();
                            }
                            return $this->renewPassword($legacyUser, $scopes);
                        }
                    }
                    $data['loginResponse'] = 'Forkert kodeord';
                    break;

                case LoginResponse::UnknownUser:
                    $data['loginResponse'] = 'Ukendt email';
                    break;

                case LoginResponse::WrongScope:
                    $data['loginResponse'] = "You don't have access to this site";
                    break;
            }

        }

        return view('Login/Form', $data);
    }

    public function success() {
        $user = AuthExtension::checkSession();
        if ($user)
            echo 'Success - Welcome ' . $user->name();
        else
            $this->response->redirect(base_url('/login'));
    }

    private function renewPassword(User $user, string $scope) {
//        $token = Token::RequestToken(\TokenTypes::RenewPassword, $user);
//        return $this->response->redirect(base_url('/ForgotPassword/Recover') . '?' . http_build_query([
//                'token' => $token->value,
//                'scope' => $scope,
//            ]));
    }

}
