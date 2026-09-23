<?php namespace App\Tests\Unit\ContainerRegistries;

use App\Libraries\ContainerRegistries\HarborRegistry;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Which of a Harbor project's webhook policies is this kso's.
 *
 * Every kso called its policy `kso` and found it again by that name, so two kso's using one
 * Harbor took the policy over from each other on every set up, and only the last one heard of a
 * push. Each has its own now, named after its host and marked with its installation.
 */
class HarborWebhookPolicyTest extends CIUnitTestCase {

    private const string Mine = 'aaaa1111';
    private const string MyUrl = 'https://kso.example.org/api/auto-updates/webhooks/harbor/3';

    public function testThePolicyIsNamedAfterTheHostItCalls(): void {
        $this->assertSame('kso-kso.example.org', HarborRegistry::PolicyName(self::MyUrl));
    }

    public function testThePolicyThisInstallationMarkedIsItsOwn(): void {
        $policies = [
            $this->policy(1, 'kso-other.example.org', 'https://other.example.org/hook', HarborRegistry::PolicyDescription('bbbb2222')),
            $this->policy(2, 'kso-kso.example.org', self::MyUrl, HarborRegistry::PolicyDescription(self::Mine)),
        ];

        $this->assertSame(2, HarborRegistry::OwnPolicy($policies, self::Mine, self::MyUrl)['id']);
    }

    /**
     * Set up before the mark: taken over, and renamed on the way.
     */
    public function testTheOldKsoPolicyCallingThisKsoIsItsOwn(): void {
        $policies = [$this->policy(4, 'kso', self::MyUrl, 'Tells kso about pushed tags. Managed by kso.')];

        $this->assertSame(4, HarborRegistry::OwnPolicy($policies, self::Mine, self::MyUrl)['id']);
    }

    public function testAnOldKsoPolicyCallingAnotherKsoIsLeftAlone(): void {
        $policies = [$this->policy(4, 'kso', 'https://other.example.org/hook', 'Tells kso about pushed tags. Managed by kso.')];

        $this->assertNull(HarborRegistry::OwnPolicy($policies, self::Mine, self::MyUrl));
    }

    public function testAnotherInstallationsPolicyIsNotThisOnes(): void {
        $policies = [$this->policy(5, 'kso-other.example.org', 'https://other.example.org/hook', HarborRegistry::PolicyDescription('bbbb2222'))];

        $this->assertNull(HarborRegistry::OwnPolicy($policies, self::Mine, self::MyUrl));
    }

    private function policy(int $id, string $name, string $address, string $description): array {
        return [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'targets' => [['type' => 'http', 'address' => $address]],
        ];
    }

}
