<?php namespace App\Libraries\Kubernetes;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

/**
 * The cluster could not be reached at all - a name that did not resolve, a connection refused or
 * timed out. Not a reason a deployment cannot be deployed, so it is thrown rather than returned
 * from `validateDeployCommand()`: as a validation error it turned a live deployment into a Draft,
 * and a Draft is not a failed deploy, so an auto update ran its post update actions.
 *
 * A cluster that answers - "401 Unauthorized", "403 Forbidden" - has answered, and stays a reason.
 */
class ClusterDidNotAnswer extends \RuntimeException {

    public function __construct(\Throwable $previous) {
        parent::__construct(KubeHelper::PrintException($previous), 0, $previous);
    }

    public static function Is(\Throwable $e): bool {
        return $e instanceof ConnectException
            || ($e instanceof RequestException && !$e->hasResponse());
    }

}
