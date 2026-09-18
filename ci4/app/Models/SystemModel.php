<?php namespace App\Models;

use OrmExtension\Extensions\Model;
use RestExtension\ResourceModelInterface;

class SystemModel extends Model implements ResourceModelInterface {

    /**
     * The four GET-side members below cannot be entered, and that is deliberate.
     *
     * `restGet()` is what runs them, and there are only two ways in: a `get` route, or
     * being an included relation on one. `systems` has neither. It carries only a `patch`
     * route - `Systems::get()` is switched off with `@ignore true` precisely because a
     * listing would hand out the GitHub App private key, which is SEC-1 - and no model
     * names `SystemModel` in its `$hasOne` or `$hasMany`, so no `?include=` reaches it.
     *
     * They are marked rather than left uncovered because the thing keeping them out is a
     * security decision we want to keep, not a test we have not written yet. Should
     * `systems` ever get a `get` route, `SystemsApiTest` fails first, which is the order
     * these should be discovered in.
     *
     * The three permission methods are a different matter and are *not* marked: `patch`
     * reaches all three, and `isRestUpdateAllowed()` returning `false` silently stops the
     * save while still answering 200 - see
     * `SystemsApiTest::testSavingTheSystemChangesTheRowAndNotJustTheAnswer()`.
     *
     * That the interface demands six members when this model can use three is FEAT-50.
     */

    public $hasOne = [

    ];

    public $hasMany = [

    ];

    /** @codeCoverageIgnore */
    public function preRestGet($queryParser, $id) {

    }

    /** @codeCoverageIgnore */
    public function postRestGet($queryParser, $items) {

    }

    public function isRestCreationAllowed($item): bool {
        return false;
    }

    public function isRestUpdateAllowed($item): bool {
        return true;
    }

    public function isRestDeleteAllowed($item): bool {
        return false;
    }

    /** @codeCoverageIgnore */
    public function appleRestGetManyRelations($items) {

    }

    /** @codeCoverageIgnore */
    public function ignoredRestGetOnRelations(): array {
        return [

        ];
    }

}
