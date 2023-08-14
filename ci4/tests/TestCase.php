<?php namespace App;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Config\Config;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIDatabaseTestCase;
use Config\RestExtension;

class TestCase extends CIDatabaseTestCase {

    protected $refresh = true;
//    protected $seed = 'DevSeeder';
    protected $basePath = APPPATH . 'Database';
    protected $namespace = '';

    public function setUp(): void {
        /** @var RestExtension $restExtensionConfig */
        $restExtensionConfig = Config::get('RestExtension');
        $restExtensionConfig->databaseGroupName = $this->DBGroup;

        Events::trigger('pre_system');
        parent::setUp();
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    private function echo($msg) {
        CLI::write($msg);
    }

}
