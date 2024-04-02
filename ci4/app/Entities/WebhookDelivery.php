<?php namespace App\Entities;

use DebugTool\Data;
use RestExtension\Core\Entity;

/**
 * Class WebhookDelivery
 * @package App\Entities
 * @property int $webhook_id
 * @property Webhook $webhook
 * @property string $url
 * @property string $method
 * @property string $content_type
 * @property string $auth_bearer_token
 * @property string $payload
 * @property int $response_code
 * @property string $response_headers
 * @property string $response_body
 * @property int $response_time
 */
class WebhookDelivery extends Entity {

    public function retry(): WebhookDelivery {
        $new = $this;
        $new->id = null;
        $new->save();
        $new->run();
        return $new;
    }

    public function run(): void {
        $headers = [
            "Content-Type: {$this->content_type}",
        ];

        if ($this->auth_bearer_token) {
            $headers[] = "Authorization: Bearer {$this->auth_bearer_token}";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->url);

        switch($this->method) {
            case 'get':
                curl_setopt($ch, CURLOPT_URL, $this->url);
                break;
            case 'post':
                curl_setopt($ch, CURLOPT_URL, $this->url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);
                break;
            case 'patch':
                curl_setopt($ch, CURLOPT_URL, $this->url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);
                break;
            case 'put':
                curl_setopt($ch, CURLOPT_URL, $this->url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);
                break;
            case 'delete':
                curl_setopt($ch, CURLOPT_URL, $this->url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);
                break;
        }

        $time = microtime(true);
        $response = curl_exec($ch);
        $this->response_time = (microtime(true) - $time) * 1000;
        $this->response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->response_headers = substr($response, 0, $headerSize);
        $this->response_body = substr($response, $headerSize);
        curl_close($ch);
        $this->save();
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|WebhookDelivery[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
