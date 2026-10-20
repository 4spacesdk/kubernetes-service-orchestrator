<?php namespace App\Controllers;

use App\Libraries\Audit\Audit;
use App\Libraries\Kubernetes\GeneratedSecrets;
use DebugTool\Data;

/**
 * The generated secrets of deployments and workspaces that were deleted. They are kept - a key a
 * customer's data was encrypted with may be needed again - until somebody purges them. See
 * `GeneratedSecrets`.
 */
class DeletedSecrets extends \App\Core\BaseController {

    private const array Owners = [GeneratedSecrets::Deployment, GeneratedSecrets::Workspace];

    /**
     * Who was deleted and which secrets kso keeps of each, with their versions - never a value.
     *
     * @route /deleted-secrets
     * @method get
     * @custom true
     * @responseSchema DeletedSecretsGetResponse
     */
    public function getDeleted(): void {
        Data::set('resource', ['owners' => GeneratedSecrets::Deleted()]);
        $this->success();
    }

    /**
     * One value of a deleted owner's secret, for a person who asked for it - recorded, as it
     * leaves kso.
     *
     * @route /deleted-secrets/reveal
     * @method put
     * @custom true
     * @parameter string $owner parameterType=query - deployment or workspace
     * @parameter int $ownerId parameterType=query
     * @parameter string $name parameterType=query
     * @parameter int $version parameterType=query - an earlier value's; the current one without
     * @responseSchema GeneratedSecretRevealResponse
     * @audit deleted_secret.reveal
     */
    public function reveal(): void {
        [$owner, $ownerId] = $this->owner();
        $name = (string) $this->request->getGet('name');
        $version = $this->request->getGet('version');
        $version = $version === null || $version === '' ? null : (int) $version;
        if ($owner === null || !GeneratedSecrets::IsDeleted($owner, $ownerId)) {
            $this->fail('unknown deleted owner');
            return;
        }
        $value = GeneratedSecrets::Reveal($owner, $ownerId, $name, $version);
        if ($value === null || $value === '') {
            $this->fail('unknown secret');
            return;
        }
        Audit::Record('deleted_secret.reveal', $this->resource($owner, $ownerId), ['name' => $name] + ($version === null ? [] : ['version' => $version]));
        Data::set('resource', ['name' => $name, 'value' => $value]);
        $this->success();
    }

    /**
     * A deleted owner's secrets and every version of them gone for good - recorded.
     *
     * @route /deleted-secrets/purge
     * @method put
     * @custom true
     * @parameter string $owner parameterType=query - deployment or workspace
     * @parameter int $ownerId parameterType=query
     * @responseSchema DeletedSecretsGetResponse
     * @audit deleted_secret.purge
     */
    public function purge(): void {
        [$owner, $ownerId] = $this->owner();
        if ($owner === null) {
            $this->fail('unknown deleted owner');
            return;
        }
        $resource = $this->resource($owner, $ownerId);
        $purged = Audit::Atomically(function () use ($owner, $ownerId, $resource): bool {
            if (!GeneratedSecrets::Purge($owner, $ownerId)) {
                return false;
            }
            Audit::Record('deleted_secret.purge', $resource);
            return true;
        });
        if (!$purged) {
            $this->fail('unknown deleted owner');
            return;
        }
        Data::set('resource', ['owners' => GeneratedSecrets::Deleted()]);
        $this->success();
    }

    /**
     * @return array{0: ?string, 1: int} the owner, null when it is neither kind, and its id
     */
    private function owner(): array {
        $owner = (string) $this->request->getGet('owner');
        return [in_array($owner, self::Owners, true) ? $owner : null, (int) $this->request->getGet('ownerId')];
    }

    /**
     * The trail's resource: the owner's row is gone, so who it was comes from its secrets.
     *
     * @return array{type: string, id: int, name: ?string}
     */
    private function resource(string $owner, int $ownerId): array {
        $name = null;
        foreach (GeneratedSecrets::Deleted() as $deleted) {
            if ($deleted['owner'] === $owner && $deleted['owner_id'] === $ownerId) {
                $name = $deleted['owner_name'];
            }
        }
        return ['type' => ucfirst($owner), 'id' => $ownerId, 'name' => $name];
    }

    public function requireAuth(string $method): bool {
        return true;
    }

}
