<?php namespace App\Core;
use DebugTool\Data;
use OrmExtension\Extensions\Entity;
use RestExtension\QueryParser;
use RestExtension\ResourceControllerInterface;
use RestExtension\ResourceControllerTrait;

class ResourceController extends BaseController implements ResourceControllerInterface {

    use ResourceControllerTrait;

    /**
     * @param Entity|int $items
     */
    public function _setResources($items) {
        if(is_numeric($items)) {
            Data::set('count', $items);
        } else if($items instanceof Entity) {
            Data::set('count', $items->count());
            Data::set('resources', $items->allToArray());
        }
    }

    /**
     * @param array $items
     */
    public function _setRawResources($items) {
        Data::set('count', count($items));
        Data::set('resources', $items);
    }

    /**
     * @param Entity $item
     */
    public function _setResource($item) {
        Data::set('resource', $item->toArray());
    }

    public function requireAuth(string $method): bool {
        return true;
    }

    public function error($errorCode, $statusCode = 503, $error = null) {
        Data::set('error_code', $errorCode);
        $this->fail($error, $statusCode);
    }
}
