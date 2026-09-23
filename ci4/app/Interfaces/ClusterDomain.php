<?php namespace App\Interfaces;
/**
 * A cert-manager Certificate in the cluster, held up against kso's domains - see `ClusterDomains`.
 *
 * @property string $namespace
 * @property string $name
 * @property string[] $dns_names
 * @property string $issuer
 * @property string $secret_name
 * @property bool $ready Whether cert-manager says it is; empty before it has looked
 * @property string $not_after When it expires
 * @property string $status known, unknown, or internal - only names inside the cluster, not a domain
 * @property int $domain_id kso's, when it is known
 * @property string[] $differences For a known one, what differs between the cluster and kso
 * @property ClusterDomainPlan $plan For one kso does not have, what taking it over would do
 */
interface ClusterDomain {

}
