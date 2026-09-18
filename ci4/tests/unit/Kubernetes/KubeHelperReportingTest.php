<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\KubeHelper;
use CodeIgniter\Test\CIUnitTestCase;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;

/**
 * What an operator is shown when a cluster call fails, and how kso identifies itself.
 *
 * `PrintException()` is the one place a raw exception is turned into the text on a
 * deployment's page. Three kinds carry their reason somewhere other than `getMessage()` -
 * Guzzle keeps it in the response body, php-k8s in a payload array, Podio in `__toString()` -
 * and anything else is shown as it is. Get the branch wrong and the page says
 * `Server error: PUT ... resulted in a 422 response`, with the field that was rejected
 * sitting unread in the body.
 *
 * The retry half of the class is in `ApplyRetriesOnConflictTest`.
 *
 * `GetMyHostname()` has no test here on purpose. It cuts `gethostname()` at the first
 * hyphen to recover the name of the Deployment that owns the pod, and there is nothing to
 * hold that against from inside a container whose hostname is a hex id with no hyphen in
 * it: every mutation of that line produces the same answer. A test would assert the
 * implementation back at itself.
 */
class KubeHelperReportingTest extends CIUnitTestCase {

    // <editor-fold desc="Turning an exception into something readable">

    /**
     * Guzzle's message is a one line summary; the reason is in the body it truncated. A
     * Kubernetes Status object is json, and handing the whole of it back is what lets the
     * page show which field the api server refused.
     */
    public function testAGuzzleServerErrorIsReportedAsItsResponseBody(): void {
        $body = json_encode([
            'kind' => 'Status',
            'status' => 'Failure',
            'message' => 'Deployment.apps "api" is invalid: spec.replicas: Invalid value: -1',
            'code' => 422,
        ]);

        $printed = KubeHelper::PrintException(new ServerException(
            'Server error: `PUT /apis/apps/v1/...` resulted in a `422 Unprocessable Entity` response',
            new Request('PUT', '/apis/apps/v1/namespaces/test/deployments/api'),
            new Response(422, [], $body)
        ));

        $this->assertSame($body, $printed);
        $this->assertStringContainsString('spec.replicas', $printed);
    }

    public function testAKubernetesApiErrorIsReportedAsItsPayload(): void {
        $printed = KubeHelper::PrintException(new KubernetesAPIException(
            'the server rejected our request',
            422,
            ['message' => 'metadata.name: Invalid value: "Api"', 'reason' => 'Invalid']
        ));

        $this->assertSame(
            ['message' => 'metadata.name: Invalid value: "Api"', 'reason' => 'Invalid'],
            json_decode($printed, true)
        );
    }

    /**
     * php-k8s does not always have a payload - a transport level failure has none - and
     * then the message is all there is. Reporting an empty json object instead would leave
     * the page blank.
     */
    public function testAKubernetesApiErrorWithNoPayloadFallsBackToItsMessage(): void {
        $printed = KubeHelper::PrintException(new KubernetesAPIException('404 page not found', 404));

        $this->assertSame('404 page not found', $printed);
    }

    /**
     * Podio puts the reason in `__toString()` rather than in the message - its own message
     * is the class name - so this is the one branch where printing the exception normally
     * would say nothing at all.
     */
    public function testAPodioErrorIsReportedAsItsFullText(): void {
        $error = new \PodioBadRequestError(
            json_encode([
                'error_description' => 'Invalid value "x" (string): must be an integer',
                'request' => ['url' => 'https://api.podio.com/comment/item/1'],
            ]),
            400,
            'https://api.podio.com/comment/item/1'
        );

        $printed = KubeHelper::PrintException($error);

        $this->assertStringContainsString('must be an integer', $printed);
        $this->assertStringContainsString('https://api.podio.com/comment/item/1', $printed);
        $this->assertNotSame($error->getMessage(), $printed, 'its own message is only the class name');
    }

    public function testAnythingElseIsReportedAsItsMessage(): void {
        $this->assertSame(
            'missing KUBERNETES_AUTH',
            KubeHelper::PrintException(new \Exception('missing KUBERNETES_AUTH'))
        );
    }

    /**
     * The switch is on `get_class()`, not on `instanceof`, so a subclass falls through to
     * the default and is printed as its message. Guzzle throws `ClientException` for every
     * 4xx and `ServerException` only for 5xx - and the api server answers a rejected
     * manifest with 422, a 4xx. The body with the reason in it is therefore dropped for
     * exactly the failure an operator is most likely to cause.
     */
    public function testAGuzzleClientErrorLosesItsBody(): void {
        $printed = KubeHelper::PrintException(new \GuzzleHttp\Exception\ClientException(
            'Client error: `PUT /apis/apps/v1/...` resulted in a `422 Unprocessable Entity` response',
            new Request('PUT', '/apis/apps/v1/namespaces/test/deployments/api'),
            new Response(422, [], json_encode(['message' => 'spec.replicas: Invalid value: -1']))
        ));

        $this->assertStringNotContainsString('spec.replicas', $printed);
    }

    // </editor-fold>

    // <editor-fold desc="Where kso thinks it is running">

    public function testTheNamespaceComesFromTheEnvironmentWhenThereIsNoServiceAccount(): void {
        $this->withNamespaceVariable('kso-production', function (): void {
            $this->assertSame('kso-production', KubeHelper::GetMyNamespace());
        });
    }

    /**
     * Nothing configured at all, which is what a developer's container looks like. The
     * fallback is what `MFALib` reads to decide whether to put a namespace in the name of
     * the authenticator entry, so an installation in `default` is treated as unnamed.
     */
    public function testWithNothingConfiguredItIsTheDefaultNamespace(): void {
        $this->withNamespaceVariable('', function (): void {
            $this->assertSame('default', KubeHelper::GetMyNamespace());
        });
    }

    // </editor-fold>

    /**
     * `KUBERNETES_MY_NAMESPACE` is read with `getenv()`, and everything a test writes to
     * the process is written for every test after it - so it goes back, including when the
     * body throws.
     *
     * @param callable(): void $body
     */
    private function withNamespaceVariable(string $value, callable $body): void {
        $original = getenv('KUBERNETES_MY_NAMESPACE');

        putenv('KUBERNETES_MY_NAMESPACE=' . $value);

        try {
            $body();
        } finally {
            if ($original === false) {
                putenv('KUBERNETES_MY_NAMESPACE');
            } else {
                putenv('KUBERNETES_MY_NAMESPACE=' . $original);
            }
        }
    }

}
