<?php namespace App\Libraries\Kubernetes;

use DebugTool\Data;
use GuzzleHttp\Exception\ServerException;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;

class KubeHelper {

    public static function PrintException(\Exception $e): string {
        Data::debug('KubeHelper PrintException', get_class($e));
        switch (get_class($e)) {
            case ServerException::class:
                return self::PrintServerException($e);
            case KubernetesAPIException::class:
                return self::PrintKubernetesAPIException($e);
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
        Data::debug($content);
        return json_encode($content);
    }

}
