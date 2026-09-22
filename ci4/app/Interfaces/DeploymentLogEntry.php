<?php namespace App\Interfaces;
/**
 * A log line from one of a deployment's pods - the pods are read as one log, so each line says
 * which of them it came from.
 *
 * @property string $date
 * @property string $line
 * @property string $pod
 * @property string $container
 */
interface DeploymentLogEntry {

}
