<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\ContainerImage;
use App\Libraries\Audit\Audit;
use App\Libraries\ImageScanning\ImageScanner;
use DebugTool\Data;

class ContainerImages extends ResourceController {

    /**
     * Scan the tags of this image that deployments run, within the minute: they are queued,
     * and the cron job that runs every minute scans them. Answers with how many were queued -
     * none when no deployment runs the image.
     *
     * With a tag, that tag alone, whether anything runs it or not - an image to look at before
     * it is deployed. See ImageScanner::queueTag().
     *
     * @route /container-images/{id}/scan
     * @method put
     * @custom true
     * @param int $id
     * @parameter string $tag parameterType=query
     * @responseSchema ContainerImageScanRequestResponse
     * @return void
     * @audit container_image.scan
     */
    public function scan(int $id): void {
        $item = new ContainerImage();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown container image');
            return;
        }

        $tag = trim((string) $this->request->getGet('tag'));
        if ($tag === '') {
            Data::set('resource', ['queued' => (new ImageScanner())->queue($item)]);
            Audit::Record('container_image.scan', $item);
            $this->success();
            return;
        }

        // What a registry accepts as a tag: it becomes part of the reference Trivy pulls.
        if (!preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,127}$/', $tag)) {
            $this->fail('invalid tag');
            return;
        }
        (new ImageScanner())->queueTag($item, $tag);
        Data::set('resource', ['queued' => 1]);
        Audit::Record('container_image.scan', $item, ['tag' => $tag]);
        $this->success();
    }

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
