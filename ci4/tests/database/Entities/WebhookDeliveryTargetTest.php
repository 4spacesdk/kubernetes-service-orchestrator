<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\Webhook;
use App\Entities\WebhookDelivery;

/**
 * Where a webhook may be sent. The answer is stored and shown in the delivery log, so a url that
 * reached kso's own files or the cloud's metadata service used to show whoever looked what was
 * there. See `OutboundUrl`.
 */
class WebhookDeliveryTargetTest extends DatabaseTestCase {

    public function testAUrlToALocalFileIsNotSentAndTheLogSaysWhy(): void {
        $delivery = $this->deliveryTo('file:///proc/self/environ');

        $delivery->run();

        $row = $this->db->table('webhook_deliveries')->where('id', $delivery->id)->get()->getRowArray();
        $this->assertSame(0, (int) $row['response_code']);
        $this->assertSame('Not sent: Only http and https urls can be called', $row['response_body']);
        $this->assertStringNotContainsString('DB_PASS', $row['response_body']);
    }

    public function testAUrlToTheMetadataServiceIsNotSent(): void {
        $delivery = $this->deliveryTo('http://169.254.169.254/latest/meta-data/');

        $delivery->run();

        $body = $this->db->table('webhook_deliveries')->where('id', $delivery->id)->get()->getRowArray()['response_body'];
        $this->assertStringStartsWith('Not sent: 169.254.169.254 is 169.254.169.254', $body);
    }

    private function deliveryTo(string $url): WebhookDelivery {
        $webhook = new Webhook();
        $webhook->type = \WebHookTypes::Workspace_Deployed;
        $webhook->name = 'subscriber';
        $webhook->url = $url;
        $webhook->http_method = 'post';
        $webhook->content_type = 'application/json';
        $webhook->auth_bearer_token = '';
        $webhook->save();

        $delivery = new WebhookDelivery();
        $delivery->webhook_id = $webhook->id;
        $delivery->url = $url;
        $delivery->method = 'post';
        $delivery->content_type = 'application/json';
        $delivery->auth_bearer_token = '';
        $delivery->payload = '{}';
        $delivery->save();

        return $delivery;
    }

}
