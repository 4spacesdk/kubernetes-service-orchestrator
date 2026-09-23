<?php namespace App\Interfaces;
/**
 * Why a deployment is doing badly - see `Libraries/Health/Diagnosis`.
 *
 * @property string $health The health as stored, for the heading
 * @property string $health_reason
 * @property DeploymentDiagnosisFinding[] $findings The most certain first; empty when no rule found anything
 */
interface DeploymentDiagnosisResponse {

}
