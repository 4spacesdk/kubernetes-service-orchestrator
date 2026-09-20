<?php namespace App\Tests\Unit\Models;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Every model extends the base model, and none of them extends the users model.
 *
 * Twelve did, including `DeploymentModel`, `DomainModel` and `GatewayModel`. The extension's
 * `RestExtension\Models\UserModel` is an empty `extends Model`, so it made no difference to
 * what they did - it was a generator leftover, copied from file to file. What it would have
 * made a difference to is the day the extension gives that class a relation or a hook of its
 * own: twelve models unrelated to users would have inherited it, and nothing here would have
 * said a word.
 *
 * `App\Models\UserModel` is the one that may: it is kso's users model, and the name it
 * extends is the extension's users model.
 */
class ModelBaseClassTest extends CIUnitTestCase {

    public function testNoModelButTheUsersOneExtendsTheUsersModel(): void {
        $offenders = [];

        foreach (glob(APPPATH . 'Models/*.php') as $file) {
            $name = basename($file, '.php');
            if ($name === 'UserModel') {
                continue;
            }

            $parent = get_parent_class('App\\Models\\' . $name);
            if ($parent === \RestExtension\Models\UserModel::class) {
                $offenders[] = $name;
            }
        }

        $this->assertSame([], $offenders, 'these extend the users model for no reason');
    }

    /**
     * And the sweep is looking at the models rather than at an empty directory.
     */
    public function testTheSweepSeesTheModels(): void {
        $this->assertGreaterThan(50, count(glob(APPPATH . 'Models/*.php')));
    }

}
