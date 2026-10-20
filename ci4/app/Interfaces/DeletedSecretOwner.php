<?php namespace App\Interfaces;

/**
 * A deleted deployment or workspace whose generated secrets kso keeps - who it was, as its row
 * is gone.
 *
 * @package App\Interfaces
 * @property string $owner `deployment` or `workspace`
 * @property int $owner_id
 * @property string $owner_name
 * @property string $owner_namespace
 * @property string $deleted
 * @property GeneratedSecret[] $secrets
 */
interface DeletedSecretOwner {

}
