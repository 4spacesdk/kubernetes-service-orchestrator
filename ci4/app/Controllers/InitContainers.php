<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\InitContainer;
use App\Entities\InitContainerEnvironmentVariable;
use App\Interfaces\EnvironmentVariableList;

class InitContainers extends ResourceController {

    /**
     * @route /init-containers/{id}/environment-variables
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema EnvironmentVariableList
     * @return void
     */
    public function updateEnvironmentVariables(int $id): void {
        $item = new InitContainer();
        $item->find($id);
        if ($item->exists()) {
            /** @var EnvironmentVariableList $body */
            $body = $this->request->getJSON();
            $values = new InitContainerEnvironmentVariable();
            $values->all = array_map(
                fn($data) => InitContainerEnvironmentVariable::Create($data->name, $data->value),
                $body->values
            );
            $item->updateEnvironmentVariables($values);
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
