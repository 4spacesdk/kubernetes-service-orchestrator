<?php namespace App\Libraries\Kubernetes;

use DebugTool\Data;
use GuzzleHttp\Exception\ServerException;
use PodioBadRequestError;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;

class KubeHelper {

    public static function PrintException(\Exception $e): string {
        Data::debug('KubeHelper PrintException', get_class($e));
        switch (get_class($e)) {
            case ServerException::class:
                return self::PrintServerException($e);
            case KubernetesAPIException::class:
                return self::PrintKubernetesAPIException($e);
            case PodioBadRequestError::class:
                return $e->__toString();
            default:
                return $e->getMessage();
        }
    }

    private static function PrintServerException(ServerException $e): string {
        $content = $e->getResponse()->getBody()->getContents();
        Data::debug(json_decode($content));
        return $content;
    }

    private static function PrintKubernetesAPIException(KubernetesAPIException $e): string {
        $content = $e->getPayload();
        Data::debug($e->getMessage());
        Data::debug($content);
        return json_encode($content);
    }

    public static function GetMyNamespace(): string {
        if (file_exists('/var/run/secrets/kubernetes.io/serviceaccount/namespace')) {
            return file_get_contents('/var/run/secrets/kubernetes.io/serviceaccount/namespace');
        } else if (getenv('KUBERNETES_MY_NAMESPACE')) {
            return getenv('KUBERNETES_MY_NAMESPACE');
        } else {
            return 'default';
        }
    }

    public static function GetMyHostname(): string {
        $hostname = gethostname();
        $hostname = explode('-', $hostname);
        return $hostname[0];
    }

}
