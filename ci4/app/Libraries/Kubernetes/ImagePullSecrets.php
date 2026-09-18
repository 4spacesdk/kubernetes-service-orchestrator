<?php namespace App\Libraries\Kubernetes;

use App\Entities\ContainerImage;

/**
 * The `imagePullSecrets` of a pod, from every image it runs.
 *
 * A pod has one list for all its containers, so an init container from another registry
 * needs its secret named too. It used to be only the main image's.
 */
class ImagePullSecrets {

    /**
     * @return array<array{name: string}> Empty when none of the images needs one.
     */
    public static function of(ContainerImage ...$images): array {
        $names = [];
        foreach ($images as $image) {
            if ($image->exists()) {
                $names = [...$names, ...$image->getPullSecretNames()];
            }
        }
        return array_map(fn (string $name) => ['name' => $name], array_values(array_unique($names)));
    }

}
