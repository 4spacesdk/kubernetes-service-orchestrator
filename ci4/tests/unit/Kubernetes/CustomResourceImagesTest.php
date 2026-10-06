<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\CustomResourceImages;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The images a custom resource names, read from the manifest as written - placeholders and all.
 */
class CustomResourceImagesTest extends CIUnitTestCase {

    private const string RabbitMq = <<<'YAML'
apiVersion: rabbitmq.com/v1beta1
kind: RabbitmqCluster
metadata:
  name: rabbitmq-server
  namespace: ${namespace}
spec:
  image: 651p8071.c1.de1.container-registry.ovh.net/taksinto/rabbitmq-server:develop
  override:
    statefulSet:
      spec:
        template:
          spec:
            containers:
              - name: rabbitmq
                imagePullPolicy: Always
            initContainers:
              - image: "busybox:1.36" # quoted, with a comment
YAML;

    public function testEveryImageItNamesIsFoundWithItsTag(): void {
        $this->assertSame([
            ['reference' => '651p8071.c1.de1.container-registry.ovh.net/taksinto/rabbitmq-server:develop', 'repository' => '651p8071.c1.de1.container-registry.ovh.net/taksinto/rabbitmq-server', 'tag' => 'develop'],
            ['reference' => 'busybox:1.36', 'repository' => 'busybox', 'tag' => '1.36'],
        ], CustomResourceImages::Found(self::RabbitMq));
    }

    /**
     * A registry's port is not a tag, and no tag is latest, as Kubernetes reads it.
     */
    public function testARegistrysPortIsNotATag(): void {
        $this->assertSame(['localhost:5000/app', '1.0'], CustomResourceImages::Split('localhost:5000/app:1.0'));
        $this->assertSame(['localhost:5000/app', 'latest'], CustomResourceImages::Split('localhost:5000/app'));
        $this->assertSame(['app', '1.0'], CustomResourceImages::Split('app:1.0@sha256:abc'));
    }

    public function testTheTagBecomesTheDeploymentsVersionWhereThatImageIsNamed(): void {
        $manifest = CustomResourceImages::WithVersionPlaceholder(self::RabbitMq, '651p8071.c1.de1.container-registry.ovh.net/taksinto/rabbitmq-server:develop');

        $this->assertStringContainsString('  image: 651p8071.c1.de1.container-registry.ovh.net/taksinto/rabbitmq-server:${deployment.version}', $manifest);
        $this->assertStringContainsString('- image: "busybox:1.36"', $manifest, 'the other image is left as it is');
    }

}
