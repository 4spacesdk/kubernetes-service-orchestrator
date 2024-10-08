<?php namespace App\Entities;

use DebugTool\Data;
use App\Core\Entity;

/**
 * Class PostUpdateActionCondition
 * @package App\Entities
 * @property int $post_update_action_id
 * @property PostUpdateAction $post_update_action
 * @property string $type
 * @property int $podio_field_reference_id
 * @property PodioFieldReference $podio_field_reference
 * @property string $value
 */
class PostUpdateActionCondition extends Entity {

    public function check(Deployment $deployment): bool {
        switch ($this->type) {
            case \PostUpdateActionConditionTypes::PodioFieldEquals:

                $spec = $deployment->findDeploymentSpecification();
                if (!$spec->container_image->exists()) {
                    $spec->container_image->find();
                }
                $shortSha = $spec->container_image->getCommitIdentification()->getCommitShortSha($deployment);

                if (!strlen($shortSha)) {
                    Data::debug('ERROR Could not find commit short sha');
                    return false;
                }

                Data::debug('Commit short sha', $shortSha);

                $vcs = $spec->container_image->getVersionControlSystem();
                $commitMessage = $vcs->getCommitMessage($shortSha);
                if (!strlen($commitMessage)) {
                    Data::debug('ERROR Could not find commit message');
                    return false;
                }

                // Grab url from commit
                preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $commitMessage, $match);
                if (count($match[0]) !== 1) {
                    Data::debug('ERROR Could not find task/issue url in commit message', $commitMessage);
                    return false;
                }
                $podioItemUrl = $match[0][0];

                if (!$this->podio_field_reference->exists()) {
                    $this->podio_field_reference->find();
                }
                $fieldValue = $this->podio_field_reference->getFieldValue($podioItemUrl);
                Data::debug('check podio field value', $fieldValue, $this->value, $fieldValue == $this->value);
                return $fieldValue == $this->value;
        }
        return false;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|PostUpdateActionCondition[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
