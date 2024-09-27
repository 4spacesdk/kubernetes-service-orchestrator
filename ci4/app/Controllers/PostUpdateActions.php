<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\PodioFieldReference;
use App\Entities\PostUpdateAction;
use App\Entities\PostUpdateActionCondition;
use App\Interfaces\PostUpdateActionConditionList;

class PostUpdateActions extends ResourceController {

    /**
     * @route /post-update-actions/{id}/conditions
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema PostUpdateActionConditionList
     * @return void
     */
    public function updateConditions(int $id): void {
        $item = new PostUpdateAction();
        $item->find($id);
        if ($item->exists()) {
            /** @var PostUpdateActionConditionList $body */
            $body = $this->request->getJSON();

            $values = new PostUpdateActionCondition();
            foreach ($body->values as $value) {
                $condition = new PostUpdateActionCondition();
                $condition->type = $value->type;
                $condition->value = $value->value;
                if ($value->podio_field_reference) {
                    $condition->podio_field_reference_id = PodioFieldReference::Create(
                        $value->podio_field_reference->podio_integration_id,
                        $value->podio_field_reference->field_id
                    )->id;
                }
                $condition->save();
                $values->add($condition);
            }

            $item->updateConditions($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @ignore true
     * @param $id
     * @return void
     */
    public function put($id = 0) {
    }

}
