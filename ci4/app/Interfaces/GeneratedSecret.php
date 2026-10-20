<?php namespace App\Interfaces;

/**
 * A secret kso made, as a list shows it - never its value. See `GeneratedSecrets`.
 *
 * @package App\Interfaces
 * @property string $name
 * @property string $recipe what it is made of, as written after the name - `randAlphaNum 32`
 * @property string $created
 * @property string $rotated
 * @property bool $pending rotated, and made anew at the next deploy
 * @property int $version the current value's - or, pending, the one the next deploy makes
 * @property string $origin where the current value came from: `made`, `restored from version 2`, `set by hand`
 * @property string $made when the current value was
 * @property GeneratedSecretVersion[] $versions the values it no longer has, the newest first
 */
interface GeneratedSecret {

}
