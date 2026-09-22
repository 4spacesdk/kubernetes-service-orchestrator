<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class WorkspaceTemplate
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
 * @property WorkspaceTemplateDeploymentSpecification $workspace_template_deployment_specifications
 * @property WorkspaceTemplateEnvironmentVariable $workspace_template_environment_variables
 * @property Label $labels
 */
class WorkspaceTemplate extends Entity {

    /**
     * A saved copy of this template, pointing at the same specifications. All of it or none.
     *
     * The specifications are not copied - a template is a selection of them, with defaults -
     * and neither are the workspaces made from it. The min scale schedules a specification
     * row uses are shared entities, so the copy links to the same ones.
     *
     * `name` is free text. `namespace` is the start of every namespace a workspace made
     * from the template gets, so it keeps a form Kubernetes accepts.
     */
    public function duplicate(): WorkspaceTemplate {
        return self::inTransaction(function () {
            $copy = $this->getCopy();
            $copy->name = "{$this->name} (copy)";
            if (strlen((string) $this->namespace)) {
                $copy->namespace = "{$this->namespace}-copy";
            }
            $copy->save();

            foreach ($this->workspace_template_environment_variables->find() as $variable) {
                $variableCopy = $variable->getCopy();
                $variableCopy->workspace_template_id = $copy->id;
                $variableCopy->save();
            }

            foreach ($this->workspace_template_deployment_specifications->find() as $row) {
                /** @var WorkspaceTemplateDeploymentSpecification $row */
                $rowCopy = $row->getCopy();
                $rowCopy->workspace_template_id = $copy->id;
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

    public function updateDeploymentSpecifications(WorkspaceTemplateDeploymentSpecification $values): void {
        $this->workspace_template_deployment_specifications->find()->deleteAll();
        $this->save($values);
        $this->workspace_template_deployment_specifications = $values;
    }

    public function updateEnvironmentVariables(WorkspaceTemplateEnvironmentVariable $values): void {
        $this->workspace_template_environment_variables->find()->deleteAll();
        $this->save($values);
        $this->workspace_template_environment_variables = $values;
    }

    public function updateLabels(Label $values): void {
        $this->labels->find()->deleteAll();
        $this->save($values);
        $this->labels = $values;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|WorkspaceTemplate[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
