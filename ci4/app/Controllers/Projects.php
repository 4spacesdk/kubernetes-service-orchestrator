<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\Project;
use App\Interfaces\IntArrayInterface;

class Projects extends ResourceController {

    /**
     * The users the project is relevant to, as a whole: those in the list are joined, the rest
     * are not.
     *
     * @route /projects/{id}/users
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema IntArrayInterface
     * @return void
     * @audit entity
     */
    public function updateUsers(int $id): void {
        $item = new Project();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown project');
            return;
        }

        /** @var IntArrayInterface $body */
        $body = $this->request->getJSON();
        $item->updateUsers((array) ($body->values ?? []));
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @ignore true
     * @param $id
     * @return void
     * @codeCoverageIgnore
     */
    public function put($id = 0) {
    }

}
