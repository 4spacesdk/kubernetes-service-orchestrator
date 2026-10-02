<?php namespace App\Interfaces;

/**
 * `${network.trustedProxies}` for a deployment, as it would be filled in now - see `TrustedProxies`.
 *
 * @package App\Interfaces
 * @property string $way the way in, in words
 * @property string $value the list, when it can be told
 * @property string $error why it cannot, when it cannot
 */
interface DeploymentTrustedProxiesResponse {

}
