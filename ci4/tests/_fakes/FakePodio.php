<?php namespace App\Tests\Fakes;

use App\Entities\PodioIntegration;
use App\Libraries\Podio\BasePodio;

/**
 * Podio, without Podio.
 *
 * Reads answer from whatever a test put in `$fieldValues`; writes are recorded in
 * `$comments` and `$fieldUpdates` so a test can assert what kso tried to do. That is the
 * interesting half - the whole point of a post-update action is the write.
 *
 *     $fakes = FakeIntegrations::install();
 *     $fakes->podio()->fieldValues['123'] = 'ready';
 *     ...
 *     $this->assertSame([...], $fakes->podio()->comments);
 */
class FakePodio extends BasePodio {

    /** @var array<string, string|null> field id => value */
    public array $fieldValues = [];

    /** @var array<array{id: string, name: string, type: string}> */
    public array $fields = [];

    /** @var array<array{itemId: string, comment: string}> */
    public array $comments = [];

    /** @var array<array{fieldId: string, itemId: string, value: string|int}> */
    public array $fieldUpdates = [];

    public function fields(PodioIntegration $integration): array {
        return $this->fields;
    }

    public function fieldDetails(PodioIntegration $integration, string $fieldId): array {
        return [
            'id' => $fieldId,
            'name' => 'Faked field',
            'type' => 'category',
            'options' => [],
        ];
    }

    public function fieldValue(PodioIntegration $integration, string $fieldId, string $itemId): ?string {
        return $this->fieldValues[$fieldId] ?? null;
    }

    public function addComment(PodioIntegration $integration, string $itemId, string $comment): void {
        $this->comments[] = ['itemId' => $itemId, 'comment' => $comment];
    }

    public function updateField(PodioIntegration $integration, string $fieldId, string $itemId, string|int $value): void {
        $this->fieldUpdates[] = ['fieldId' => $fieldId, 'itemId' => $itemId, 'value' => $value];
    }

}
