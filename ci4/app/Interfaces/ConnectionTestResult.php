<?php namespace App\Interfaces;

/**
 * Whether a connection could be made, and if not, why - in the words of the server or the
 * driver that refused it.
 *
 * @property bool $value
 * @property string $reason null when the connection was made
 */
interface ConnectionTestResult {

}
