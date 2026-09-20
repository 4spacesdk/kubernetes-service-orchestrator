<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\PodioIntegration;
use DebugTool\Data;

class PodioIntegrations extends ResourceController {

    /**
     * @route /podio-integrations/{id}/fields
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema PodioIntegrationGetFieldsResponse
     * @return void
     * @throws \Exception
     */
    public function getFields(int $id = 0): void {
        $item = new PodioIntegration();
        $item->find($id);

        // Through the envelope rather than by hand, so this list carries `count` like every
        // other collection - a generated client reads the total off the envelope.
        $this->_setRawResources($item->exists() ? $item->getFields() : []);
        $this->success();
    }

    /**
     * @route /podio-integrations/{id}/fields/{fieldId}/details
     * @method get
     * @custom true
     * @param int $id
     * @param string $fieldId
     * @responseSchema PodioIntegrationGetFieldDetailsResponse
     * @return void
     * @throws \Exception
     */
    public function getFieldDetails(int $id, string $fieldId): void {
        $item = new PodioIntegration();
        $item->find($id);

        Data::set('resource', $item->exists() ? $item->getFieldDetails($fieldId) : []);
        $this->success();
    }

    /**
     * PUT is switched off; the UI saves with PATCH.
     *
     * `@ignore true` keeps it out of `api_routes`, so this body is never entered. A routed
     * PUT would replace every column, including the client secret and app token.
     *
     * @ignore true
     * @codeCoverageIgnore
     * @param $id
     * @return void
     */
    public function put($id = 0) {
    }

}
