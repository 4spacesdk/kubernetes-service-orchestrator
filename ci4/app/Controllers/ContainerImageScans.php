<?php namespace App\Controllers;

use App\Core\ResourceController;

/**
 * What Trivy found in the images that are running. Read only: the scanner writes the rows,
 * and a scan is asked for through `PUT container-images/{id}/scan`.
 */
class ContainerImageScans extends ResourceController {

}
