<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\DatabaseService;
use App\Entities\EmailService;
use App\Entities\OAuthClient;
use App\Entities\PodioIntegration;
use App\Entities\Webhook;
use App\Entities\WebhookDelivery;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The credentials the API stopped handing out, held against the two things that has to be
 * true of each of them.
 *
 * `RestGetSweepTest` asserts the first - that no read answers with one - across every
 * resource at once. This file is the other side: each field is still writable, and a write
 * that leaves it empty keeps what is stored. Without the second, hiding the value would be
 * worse than leaving it: a dialog cannot show what it is not sent, so it sends the field
 * back empty, and every save of an unrelated setting would clear the credential.
 *
 * Driven off `SecretFields` on the entities rather than a list written here, so a field
 * added to one of them is covered without anyone remembering this file.
 */
class WriteOnlySecretsTest extends DatabaseTestCase {

    /**
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function everyWriteOnlyField(): array {
        $cases = [];

        foreach ([
            DatabaseService::class,
            EmailService::class,
            PodioIntegration::class,
            Webhook::class,
            WebhookDelivery::class,
            OAuthClient::class,
        ] as $entity) {
            foreach ($entity::SecretFields as $field) {
                $cases[substr(strrchr($entity, '\\'), 1) . '.' . $field] = [$entity, $field];
            }
        }

        return $cases;
    }

    /**
     * Hidden from every response and every push, and reported as set in its place.
     *
     * `toArray()` is the one place both happen, which is why it is asserted here rather
     * than through a request: the same method serialises a resource, a relation somebody
     * did not ask for, and the change events the browsers are sent.
     */
    #[DataProvider('everyWriteOnlyField')]
    public function testTheFieldIsWithheldAndReportedAsSet(string $entity, string $field): void {
        /** @var \App\Core\Entity $item */
        $item = new $entity();
        $item->{$field} = 'a-real-credential';

        $array = $item->toArray();

        $this->assertArrayNotHasKey($field, $array);
        $this->assertTrue($array["has_{$field}"]);
    }

    #[DataProvider('everyWriteOnlyField')]
    public function testAFieldThatIsNotSetIsReportedAsNotSet(string $entity, string $field): void {
        /** @var \App\Core\Entity $item */
        $item = new $entity();

        $this->assertFalse($item->toArray()["has_{$field}"]);
    }

    /**
     * The write half: what a PATCH is filtered through before it reaches the entity.
     *
     * Asserted on the filter rather than through a request because `WebhookDelivery` has no
     * write of its own - it is written by kso and read by the log - and the rule is the same
     * one either way. `OAuthClientsApiTest` sends the real PATCH.
     */
    #[DataProvider('everyWriteOnlyField')]
    public function testAnEmptyValueInAWriteKeepsWhatIsStored(string $entity, string $field): void {
        $keep = (new \ReflectionMethod($entity, 'keepStoredSecrets'));

        $this->assertSame(
            ['name' => 'unchanged'],
            $keep->invoke(null, ['name' => 'unchanged', $field => '']),
            'an empty value has to be dropped, or saving a form clears the credential'
        );
        $this->assertSame(
            ['name' => 'unchanged', $field => 'a-new-one'],
            $keep->invoke(null, ['name' => 'unchanged', $field => 'a-new-one']),
            'a value that was typed still has to be written'
        );
    }

}
