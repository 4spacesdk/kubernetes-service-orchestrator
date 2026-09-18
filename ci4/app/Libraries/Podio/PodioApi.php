<?php namespace App\Libraries\Podio;

use App\Entities\PodioIntegration;
use DebugTool\Data;

/**
 * Podio over the wire, through the official client.
 *
 * Not measured: every method here is a call to Podio and the mapping of its answer.
 * What kso decides is tested through the fake behind `BasePodio`, which is the whole point
 * of that interface - see the strategy note in the test setup.
 *
 * @codeCoverageIgnore
 */
class PodioApi extends BasePodio {

    public function fields(PodioIntegration $integration): array {
        $client = $this->connect($integration);
        $app = \PodioApp::get($client, $integration->app_id);

        $fields = [];
        foreach ($app->fields as $field) {
            Data::debug($field->config);
            $fields[] = [
                'id' => (string) $field->id,
                'name' => $field->label,
                'type' => $field->type,
            ];
        }

        return $fields;
    }

    public function fieldDetails(PodioIntegration $integration, string $fieldId): array {
        $client = $this->connect($integration);
        $appField = \PodioAppField::get($client, $integration->app_id, $fieldId);

        return [
            'id' => (string) $appField->id,
            'name' => $appField->name,
            'type' => $appField->type,
            'options' => array_map(
                fn ($option) => [
                    'id' => (string) $option['id'],
                    'text' => (string) $option['text'],
                    'color' => (string) $option['color'],
                ],
                array_values(array_filter(
                    $appField->config['settings']['options'] ?? [],
                    fn ($option) => $option['status'] == 'active'
                ))
            ),
        ];
    }

    public function fieldValue(PodioIntegration $integration, string $fieldId, string $itemId): ?string {
        try {
            $client = $this->connect($integration);
            $item = \PodioItem::get_by_app_item_id($client, $integration->app_id, $itemId);

            foreach ($item->fields as &$field) {
                if ($field->field_id == $fieldId) {
                    return is_array($field->values)
                        ? (count($field->values) ? $field->values[0]['id'] : '')
                        : $field->values;
                }
            }
        } catch (\Exception $e) {
            Data::debug(static::class, $e);
        }

        return null;
    }

    public function addComment(PodioIntegration $integration, string $itemId, string $comment): void {
        try {
            $client = $this->connect($integration);
            $item = \PodioItem::get_by_app_item_id($client, $integration->app_id, $itemId);
            \PodioComment::create($client, 'item', $item->item_id, [
                'value' => $comment,
                'created_by' => '4Spaces KSO',
            ]);
        } catch (\Exception $e) {
            Data::debug(static::class, $e);
        }
    }

    public function updateField(PodioIntegration $integration, string $fieldId, string $itemId, string|int $value): void {
        try {
            $client = $this->connect($integration);
            $item = \PodioItem::get_by_app_item_id($client, $integration->app_id, $itemId);
            \PodioItem::update_values($client, $item->item_id, [$fieldId => $value]);
        } catch (\Exception $e) {
            Data::debug(static::class, $e);
        }
    }

    private function connect(PodioIntegration $integration): \PodioClient {
        $client = new \PodioClient($integration->client_id, $integration->client_secret);
        $client->authenticate_with_app($integration->app_id, $integration->app_token);

        return $client;
    }

}
