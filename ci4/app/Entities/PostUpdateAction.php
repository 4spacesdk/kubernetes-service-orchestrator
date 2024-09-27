<?php namespace App\Entities;

use App\Models\PostUpdateActionConditionModel;
use DebugTool\Data;
use RestExtension\Core\Entity;

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
                $shortSha = $spec->container_image->getCommitIdentification()->getCommitShortSha($deployment);
                $vcs = $spec->container_image->getVersionControlSystem();
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

                [$_, $itemId] = explode('items/', $podioItemUrl);
                if (!$this->podio_add_comment_integration->exists()) {
                    $this->podio_add_comment_integration->find();
                }
                try {
                    $client = new \PodioClient($this->podio_add_comment_integration->client_id, $this->podio_add_comment_integration->client_secret);
                    $client->authenticate_with_app($this->podio_add_comment_integration->app_id, $this->podio_add_comment_integration->app_token);
                    $podioItem = \PodioItem::get_by_app_item_id($client, $this->podio_add_comment_integration->app_id, $itemId);
                    \PodioComment::create($client, 'item', $podioItem->item_id, ['value' => $comment, 'created_by' => '4Spaces KSO']);
                } catch (\Exception $e) {
                    Data::debug(get_class($this), $e);
                }

                break;
            case \PostUpdateActionTypes::Podio_FieldUpdate:
                $spec = $deployment->findDeploymentSpecification();
                if (!$spec->container_image->exists()) {
                    $spec->container_image->find();
                }
                $shortSha = $spec->container_image->getCommitIdentification()->getCommitShortSha($deployment);
                $vcs = $spec->container_image->getVersionControlSystem();
                $commitMessage = $vcs->getCommitMessage($shortSha);
                // Grab url from commit
                preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $commitMessage, $match);
                $podioItemUrl = count($match[0]) ? $match[0][0] : '';

                [$_, $itemId] = explode('items/', $podioItemUrl);
                if (!$this->podio_field_update_field_reference->exists()) {
                    $this->podio_field_update_field_reference->find();
                }
                if (!$this->podio_field_update_field_reference->podio_integration->exists()) {
                    $this->podio_field_update_field_reference->podio_integration->find();
                }
                try {
                    $client = new \PodioClient($this->podio_field_update_field_reference->podio_integration->client_id, $this->podio_field_update_field_reference->podio_integration->client_secret);
                    $client->authenticate_with_app($this->podio_field_update_field_reference->podio_integration->app_id, $this->podio_field_update_field_reference->podio_integration->app_token);
                    $podioItem = \PodioItem::get_by_app_item_id($client, $this->podio_field_update_field_reference->podio_integration->app_id, $itemId);
                    \PodioItem::update_values($client, $podioItem->item_id, [
                        $this->podio_field_update_field_reference->field_id => is_numeric($this->podio_field_update_value) ? (int)($this->podio_field_update_value) : $this->podio_field_update_value,
                    ]);
                } catch (\Exception $e) {
                    Data::debug(get_class($this), $e);
                }

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
