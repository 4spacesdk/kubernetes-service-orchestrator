<?php namespace App\Controllers;

class Systems extends \App\Core\BaseController {

    public function requireAuth(string $method): bool {
        return true;
    }

}
