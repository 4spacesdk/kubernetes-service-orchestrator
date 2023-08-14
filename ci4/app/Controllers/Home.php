<?php namespace App\Controllers;

class Home extends \App\Core\BaseController {

    public function requireAuth(string $method): bool {
        return false;
    }

    public function index() {
        $url = base_url('swagger');
        return "<a href='$url'>Swagger</a>";
    }
}
