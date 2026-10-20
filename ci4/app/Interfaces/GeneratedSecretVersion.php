<?php namespace App\Interfaces;

/**
 * A value a generated secret no longer has - kept, and never its value in a list.
 *
 * @package App\Interfaces
 * @property int $version
 * @property string $recipe
 * @property string $origin
 * @property string $made
 * @property string $retired when it stopped being the current one
 */
interface GeneratedSecretVersion {

}
