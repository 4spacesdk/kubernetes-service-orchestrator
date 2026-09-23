<?php namespace App\Interfaces;
/**
 * @property string $domain The domain it would become
 * @property ClusterGatewayDomain $gateway The kso gateway that listens for it, which it is linked to
 * @property string $conflict Why it cannot be taken over, when it cannot
 * @property string[] $changes What kso's next apply of the certificate would change
 */
interface ClusterDomainPlan {

}
