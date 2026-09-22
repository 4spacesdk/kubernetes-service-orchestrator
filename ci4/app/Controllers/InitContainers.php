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
     * @audit entity
     */
    public function updateEnvironmentVariables(int $id): void {
        $item = new InitContainer();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown init container');
            return;
        }

        /** @var EnvironmentVariableList $body */
        $body = $this->request->getJSON();
        $values = new InitContainerEnvironmentVariable();
        $values->all = array_map(
            fn(array $variable) => InitContainerEnvironmentVariable::Create(...$variable),
            InitContainerEnvironmentVariable::Replacements($body->values, $item->init_container_environment_variables->find())
        );
        $item->updateEnvironmentVariables($values);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * PUT is switched off; the UI saves with PATCH.
     *
     * `@ignore true` keeps it out of `api_routes`, so this body is never entered. A routed
     * PUT would replace every column rather than the ones that were sent.
     *
     * @ignore true
     * @codeCoverageIgnore
     * @param $id
     * @return void
     */
    public function put($id = 0) {
    }

}
