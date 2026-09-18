<?php namespace App\Libraries\Podio;

use App\Entities\PodioIntegration;

/**
 * Everything kso asks Podio for, in kso's own terms.
 *
 * Five operations, no Podio objects crossing the boundary: an app's fields, one field's
 * details, a field value on an item, a comment, and a field update. That is the whole
 * surface, and expressing it this way is what lets the code above it be tested - the
 * alternative is a `\PodioClient` built inside whichever method happens to need it, which
 * is what this replaces.
 *
 * Item ids are passed in already extracted. Pulling one out of a task url is the caller's
 * business and has nothing to do with talking to Podio.
 */
abstract class BasePodio {

    /**
     * The fields on the integration's app.
     *
     * @return array<array{id: string, name: string, type: string}>
     */
    abstract public function fields(PodioIntegration $integration): array;

    /**
     * One field, with its selectable options where it has any.
     *
     * @return array{id: string, name: string, type: string, options: array<array{id: string, text: string, color: string}>}
     */
    abstract public function fieldDetails(PodioIntegration $integration, string $fieldId): array;

    /**
     * The value of one field on one item, or null when the item has no such field.
     */
    abstract public function fieldValue(PodioIntegration $integration, string $fieldId, string $itemId): ?string;

    abstract public function addComment(PodioIntegration $integration, string $itemId, string $comment): void;

    abstract public function updateField(PodioIntegration $integration, string $fieldId, string $itemId, string|int $value): void;

}
