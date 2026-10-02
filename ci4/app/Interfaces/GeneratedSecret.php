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
 */
interface GeneratedSecret {

}
