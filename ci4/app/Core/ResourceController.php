<?php namespace App\Core;
use DebugTool\Data;
use OrmExtension\Extensions\Entity;
use RestExtension\QueryParser;
use RestExtension\ResourceControllerInterface;
use RestExtension\ResourceControllerTrait;

class ResourceController extends BaseController implements ResourceControllerInterface {

    use ResourceControllerTrait;

    /**
     * @param Entity|int $items a count, when the request asked for one, or the rows
     */
    public function _setResources($items) {
        if(is_int($items)) {
            // `is_numeric()` was wider than the two things a caller can hand over: it also
            // matched a numeric string, so `_setResources("5")` was reported as a list of
            // five that carried no rows.
            Data::set('count', $items);
        } else if($items instanceof Entity) {
            Data::set('count', $items->count());
            Data::set('resources', $items->allToArray());
        } else {
            // Anything else is a mistake at the call site, not something a client did. It
            // used to be dropped on the floor and the request still answered `200 OK` with
            // no `count` and no `resources`: a response a client cannot tell from an empty
            // list, on a request the log records as having succeeded.
            throw new \InvalidArgumentException(sprintf(
                '_setResources() takes a count or an entity collection, got %s',
                get_debug_type($items)
            ));
        }
    }

    /**
     * The same envelope for rows that never came from the ORM, so a client cannot tell the
     * two kinds of endpoint apart. `_setResources()` is the one to use for entities; this
     * is for an endpoint that assembled its own answer, such as a field list fetched from
     * Podio.
     *
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

    /**
     * A refusal, carrying the machine-readable `error_code` the generated clients branch on
     * beside the human-readable message.
     *
     * The default used to be 503 Service Unavailable, which says "come back later" about a
     * refusal that will be refused just as firmly next time - and it disagreed with the 200
     * `fail()` defaulted to. Both are 400 now. Every caller passes its own status; this is
     * only what an error nobody classified comes out as.
     */
    public function error($errorCode, $statusCode = 400, $error = null) {
        Data::set('error_code', $errorCode);
        $this->fail($error, $statusCode);
    }
}
