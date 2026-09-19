<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\ContainerImage;
use DebugTool\Data;

class ContainerImages extends ResourceController {

    /**
     * The image's tags, straight from its registry, each with when it was pushed - a quick
     * check that the registry's credentials work for this image. A registry that refuses answers with its reason, not
     * with an empty list.
     *
     * @route /container-images/{id}/tags
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema ContainerImageTagsGetResponse
     * @return void
     */
    public function getTags(int $id): void {
        $item = new ContainerImage();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown container image');
            return;
        }
        if ($item->getRegistryClient() === null) {
            $this->fail('the image has no container registry to ask');
            return;
        }

        try {
            Data::set('resource', ['tags' => $item->getTagDetails()]);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
            return;
        }
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
