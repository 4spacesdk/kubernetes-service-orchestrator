<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class DeploymentPackage
 * @package App\Entities
 * @property string $name
 * @property string $namespace
 *
 * # Settings
 * @property int $default_email_service_id
 * @property int $default_database_service_id
 * @property int $default_domain_id
 *
 * Many
 * @property Workspace $workspaces
 * @property DeploymentPackageDeploymentSpecification $deployment_package_deployment_specifications
 * @property DeploymentPackageEnvironmentVariable $deployment_package_environment_variables
 * @property Label $labels
 */
class DeploymentPackage extends Entity {

    public function updateDeploymentSpecifications(DeploymentPackageDeploymentSpecification $values): void {
        $this->deployment_package_deployment_specifications->find()->deleteAll();
        $this->save($values);
        $this->deployment_package_deployment_specifications = $values;
    }

    public function updateEnvironmentVariables(DeploymentPackageEnvironmentVariable $values): void {
        $this->deployment_package_environment_variables->find()->deleteAll();
        $this->save($values);
        $this->deployment_package_environment_variables = $values;
    }

    public function updateLabels(Label $values): void {
        $this->labels->find()->deleteAll();
        $this->save($values);
        $this->labels = $values;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentPackage[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
