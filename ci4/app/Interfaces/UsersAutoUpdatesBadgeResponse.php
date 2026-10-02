<?php namespace App\Interfaces;

/**
 * The number on the menu's Updates: what waits for approval, and what was approved on its own
 * since the signed-in user last opened Updates.
 *
 * @package App\Interfaces
 * @property int $waiting
 * @property int $approved_on_their_own
 */
interface UsersAutoUpdatesBadgeResponse {

}
