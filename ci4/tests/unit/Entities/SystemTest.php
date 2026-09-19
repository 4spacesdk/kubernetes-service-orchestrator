<?php namespace App\Tests\Unit\Entities;

use App\Entities\System;
use CodeIgniter\Test\CIUnitTestCase;

class SystemTest extends CIUnitTestCase {

    /**
     * Anything that looks like a credential must never appear in an api response. This is
     * the guard for the leak where /api/settings, which is served without authentication,
     * returned the whole System entity including the GitHub App private key.
     *
     * The check is on the field names rather than a fixed list, so a credential added to
     * the entity later fails here until someone decides what to do with it.
     */
    public function testPublicArrayNeverCarriesACredential(): void {
        $system = $this->systemWithEverythingFilledIn();

        foreach (array_keys($system->toPublicArray()) as $field) {
            $this->assertDoesNotMatchRegularExpression(
                '/secret|private_key|credential|password|token/i',
                $field,
                "toPublicArray() returned '{$field}', which reads like a credential"
            );
        }
    }

    public function testPublicArrayCarriesWhatTheWebAppReads(): void {
        $system = $this->systemWithEverythingFilledIn();

        $this->assertSame(
            [
                'id',
                'is_network_nginx_ingress_supported',
                'is_network_istio_supported',
                'is_network_contour_supported',
                'is_network_gateway_api_supported',
                'hosting_provider',
            ],
            array_keys($system->toPublicArray())
        );
    }

    /**
     * Reading a property gives the raw database value, so a boolean column arrives as the
     * string "0". That is truthy in JavaScript, and the System page would show every
     * network type as supported.
     */
    public function testPublicArrayCastsRatherThanPassingDatabaseStringsOn(): void {
        $system = new System();
        $system->id = '1';
        $system->is_network_nginx_ingress_supported = '1';
        $system->is_network_istio_supported = '0';

        $public = $system->toPublicArray();

        $this->assertSame(1, $public['id']);
        $this->assertTrue($public['is_network_nginx_ingress_supported']);
        $this->assertFalse($public['is_network_istio_supported']);
    }

    private function systemWithEverythingFilledIn(): System {
        $system = new System();

        $system->id = 1;
        $system->is_network_nginx_ingress_supported = true;
        $system->is_network_istio_supported = false;
        $system->is_network_contour_supported = false;
        $system->is_network_gateway_api_supported = true;
        $system->hosting_provider = 'gke';

        return $system;
    }

}
