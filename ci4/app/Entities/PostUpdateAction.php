<?php namespace App\Entities;

use App\Models\PostUpdateActionConditionModel;
use DebugTool\Data;
use App\Core\Entity;
use App\Libraries\Podio\PodioItemUrl;

/**
 * Class PostUpdateAction
 * @package App\Entities
 * @property string $name
 * @property string $type
 *
 * # Type: Podio, Add Comment
 * @property int $podio_add_comment_integration_id
 * @property PodioIntegration $podio_add_comment_integration
 * @property string $podio_add_comment_value
 *
 *  # Type: Podio, Field Update
 * @property int $podio_field_update_field_reference_id
 * @property PodioFieldReference $podio_field_update_field_reference
 * @property string $podio_field_update_value
 *
 * Many
 * @property PostUpdateActionCondition $post_update_action_conditions
 * @property DeploymentSpecification $deployment_specifications
 */
class PostUpdateAction extends Entity {

    public function updateConditions(PostUpdateActionCondition $values): void {
        $this->post_update_action_conditions->find()->deleteAll();
        $this->save($values);
        $this->post_update_action_conditions = $values;
    }

    public function checkConditions(Deployment $deployment): bool {
        /** @var PostUpdateActionCondition $conditions */
        $conditions = (new PostUpdateActionConditionModel())
            ->where('post_update_action_id', $this->id)
            ->find();

        foreach ($conditions as $condition) {
            if (!$condition->check($deployment)) {
                Data::debug('action', $this->name, 'failed conditions');
                return false;
            }
        }

        Data::debug('action', $this->name, 'passed conditions');
        return true;
    }

    public function perform(Deployment $deployment): void {
        switch ($this->type) {
            case \PostUpdateActionTypes::Podio_AddComment:
                if ($deployment->workspace_id) {
                    if (!$deployment->workspace->exists()) {
                        $deployment->workspace->find();
                    }
                }
                $spec = $deployment->findDeploymentSpecification();
                if (!$spec->container_image->exists()) {
                    $spec->container_image->find();
                }
                $shortSha = $spec->container_image->getCommitShortSha($deployment);
                $vcs = $spec->container_image->getVersionControlSystem();
                if ($shortSha === null || $vcs === null) {
                    // The default for an image: no commit identification, no version control.
                    Data::debug('ERROR Cannot look up the commit for', $spec->container_image->name);
                    return;
                }
                $commitUrl = $vcs->getCommitUrl($shortSha);
                $commitMessage = $vcs->getCommitMessage($shortSha);
                // Grab url from commit
                preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $commitMessage, $match);
                $podioItemUrl = count($match[0]) ? $match[0][0] : '';

                $comment = $this->podio_add_comment_value;

                $modifiers = [
                    fn(string $value) => str_replace('${workspace.name}', $deployment->workspace->namespace, $value),
                    fn(string $value) => str_replace('${commit.url}', $commitUrl, $value),
                ];

                foreach ($modifiers as $fn) {
                    $comment = $fn($comment);
                }

                $itemId = PodioItemUrl::itemId($podioItemUrl);
                if ($itemId === null) {
                    Data::debug('No Podio item in the commit message for', $deployment->name);
                    return;
                }
                if (!$this->podio_add_comment_integration->exists()) {
                    $this->podio_add_comment_integration->find();
                }
                service('integrations')->podio()->addComment(
                    $this->podio_add_comment_integration,
                    $itemId,
                    $comment
                );

                break;
            case \PostUpdateActionTypes::Podio_FieldUpdate:
                $spec = $deployment->findDeploymentSpecification();
                if (!$spec->container_image->exists()) {
                    $spec->container_image->find();
                }
                $shortSha = $spec->container_image->getCommitShortSha($deployment);
                $vcs = $spec->container_image->getVersionControlSystem();
                if ($shortSha === null || $vcs === null) {
                    Data::debug('ERROR Cannot look up the commit for', $spec->container_image->name);
                    return;
                }
                $commitMessage = $vcs->getCommitMessage($shortSha);
                // Grab url from commit
                preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $commitMessage, $match);
                $podioItemUrl = count($match[0]) ? $match[0][0] : '';

                $itemId = PodioItemUrl::itemId($podioItemUrl);
                if ($itemId === null) {
                    Data::debug('No Podio item in the commit message for', $deployment->name);
                    return;
                }
                if (!$this->podio_field_update_field_reference->exists()) {
                    $this->podio_field_update_field_reference->find();
                }
                $reference = $this->podio_field_update_field_reference;
                if (!$reference->podio_integration->exists()) {
                    $reference->podio_integration->find();
                }

                // A numeric value is an option id and has to go as a number; anything else
                // is text. Podio rejects the wrong one.
                $value = is_numeric($this->podio_field_update_value)
                    ? (int) $this->podio_field_update_value
                    : $this->podio_field_update_value;

                service('integrations')->podio()->updateField(
                    $reference->podio_integration,
                    $reference->field_id,
                    $itemId,
                    $value
                );

                break;
        }
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|PostUpdateAction[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
