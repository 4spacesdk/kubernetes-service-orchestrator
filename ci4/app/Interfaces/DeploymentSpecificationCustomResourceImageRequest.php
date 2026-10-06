<?php namespace App\Interfaces;

/**
 * Which of a custom resource's images is the specification's container image - see
 * `DeploymentSpecification::linkCustomResourceImage()`.
 *
 * @package App\Interfaces
 * @property int $containerImageId
 * @property string $image the image as the manifest names it, tag included
 */
interface DeploymentSpecificationCustomResourceImageRequest {

}
