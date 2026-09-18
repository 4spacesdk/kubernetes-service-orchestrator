<?php namespace App\Libraries\GoogleCloud;

use App\Libraries\Kubernetes\KubeHelper;

/**
 * What this installation calls its subscription to a registry's push topic.
 *
 * The name identifies one kso against a topic that several may be reading, so it carries
 * the project and the pod's own name. It used to be built by the same expression in two
 * places - where the subscription is created, and where it is read - and the two agreeing
 * is the only thing that makes auto updates work. They can no longer drift apart.
 */
class GcrSubscription {

    public const TOPIC = 'gcr';

    public static function name(): string {
        return str_replace(' ', '_', strtolower((string) getenv('PROJECT_NAME')))
            . '.kso-' . KubeHelper::GetMyHostname()
            . '.' . KubeHelper::GetMyNamespace();
    }

}
