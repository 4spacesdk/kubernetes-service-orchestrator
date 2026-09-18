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

    /**
     * A saved copy of this package, pointing at the same specifications. All of it or none.
     *
     * The specifications are not copied - a package is a selection of them, with defaults -
     * and neither are the workspaces made from it. The min scale schedules a specification
     * row uses are shared entities, so the copy links to the same ones.
     *
     * `name` is free text. `namespace` is the start of every namespace a workspace made
     * from the package gets, so it keeps a form Kubernetes accepts.
     */
    public function duplicate(): DeploymentPackage {
        return self::inTransaction(function () {
            $copy = $this->getCopy();
            $copy->name = "{$this->name} (copy)";
            if (strlen((string) $this->namespace)) {
                $copy->namespace = "{$this->namespace}-copy";
            }
            $copy->save();

            foreach ($this->deployment_package_environment_variables->find() as $variable) {
                $variableCopy = $variable->getCopy();
                $variableCopy->deployment_package_id = $copy->id;
                $variableCopy->save();
            }

            foreach ($this->deployment_package_deployment_specifications->find() as $row) {
                /** @var DeploymentPackageDeploymentSpecification $row */
                $rowCopy = $row->getCopy();
                $rowCopy->deployment_package_id = $copy->id;
                $rowCopy->save();
                $rowCopy->save($row->k_native_min_scale_schedules->find());
            }

            // New label rows, for the reason given in DeploymentSpecification::duplicate().
            $labels = new Label();
            foreach ($this->labels->find() as $label) {
                $labels->add(Label::Create($label->name, $label->value));
            }
            $copy->save($labels);

            return $copy;
        });
    }

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
