<?php namespace App\Tests\Fakes;

use App\Entities\ContainerImage;
use App\Entities\ContainerRegistry;
use App\Entities\Deployment;
use App\Libraries\CommitIdentificationMethods\BaseCommitIdentificationMethod;
use App\Libraries\ContainerRegistries\BaseContainerRegistry;
use App\Libraries\Integrations\IntegrationFactory;
use App\Libraries\GoogleCloud\BasePubSub;
use App\Libraries\Podio\BasePodio;
use App\Libraries\VersionControlSystems\BaseVersionControlSystem;
use Config\Services;

/**
 * Stand-ins for the three outside systems a container image talks to.
 *
 * A registry, a GitHub and a commit lookup, each answering whatever a test told it to.
 * Install one with `FakeIntegrations::install()` and the code under test reaches these
 * instead of the network, without knowing the difference.
 *
 * The point is not to avoid slow tests. It is that the real ones cannot be reached from a
 * test at all - `curl_init()` against a registry, a GitHub client built in a constructor -
 * so everything behind them was untestable until there was something to put in their
 * place.
 *
 *     $fakes = FakeIntegrations::install();
 *     $fakes->tags = ['1.0.0', '1.1.0'];
 *     $fakes->commitMessage = 'Fixes https://podio.com/x/items/42';
 *
 * Each faked answer is null by default, which stands for "this image has no such
 * integration configured" - the same thing the real factory returns for an unknown
 * provider, and the case most callers get wrong.
 */
class FakeIntegrations extends IntegrationFactory {

    /** @var string[]|null Tags the registry reports. Null means no registry configured. */
    public ?array $tags = null;

    public string $repoName = 'team/app';

    /** @var string[] Repository names the registry lists, each pulled as `registry.example.org/<name>`. */
    public array $repositories = [];

    /**
     * The real registry clients, for a test about what one of them decides - which Pub/Sub
     * topic the Artifact Registry client asks for, say - with the fakes still behind it.
     */
    public bool $realRegistryClients = false;

    /** @var array<array{webhookUrl: string, secret: string, imageUrls: string[]}> Every setupEvents() call. */
    public array $eventSetups = [];

    /** Thrown by setupEvents(), for a registry that refuses. */
    public ?\Exception $failSetupEventsWith = null;

    /** Null means the image has no version control configured. */
    public ?string $commitMessage = null;

    public string $commitUrl = 'https://github.com/team/app/commit/abc1234';

    /** Null means no commit identification is configured. */
    public ?string $shortSha = null;

    /** @var string[] The name of every registry connection the code asked about, for tests that count calls. */
    public array $registryLookups = [];

    public static function install(): self {
        $fakes = new self();
        Services::injectMock('integrations', $fakes);

        return $fakes;
    }

    public static function uninstall(): void {
        Services::injectMock('integrations', null);
    }

    public function containerRegistry(ContainerRegistry $registry): ?BaseContainerRegistry {
        $this->registryLookups[] = $registry->name;

        if ($this->realRegistryClients) {
            return parent::containerRegistry($registry);
        }
        if ($this->tags === null) {
            return null;
        }

        return new class ($registry, $this->tags, $this->repoName, $this->repositories, $this) extends BaseContainerRegistry {
            /**
             * @param string[] $tags
             * @param string[] $repositories
             */
            public function __construct(ContainerRegistry $registry, private array $tags, private string $repoName, private array $repositories, private FakeIntegrations $fakes) {
                parent::__construct($registry);
            }

            public function getUrlPrefix(): string {
                return 'registry.example.org';
            }

            public function setupEvents(string $webhookUrl, string $secret, array $imageUrls): string {
                if ($this->fakes->failSetupEventsWith) {
                    throw $this->fakes->failSetupEventsWith;
                }
                $this->fakes->eventSetups[] = ['webhookUrl' => $webhookUrl, 'secret' => $secret, 'imageUrls' => $imageUrls];
                return 'fake events set up';
            }

            public function listRepositories(): array {
                return array_map(fn ($name) => ['name' => $name, 'url' => "registry.example.org/{$name}"], $this->repositories);
            }

            public function getRepoName(string $url): string {
                return $this->repoName;
            }

            public function getTags(string $url): array {
                return $this->tags;
            }

            public function testConnection(): string {
                return 'fake registry';
            }
        };
    }

    public function versionControlSystem(ContainerImage $image): ?BaseVersionControlSystem {
        if ($this->commitMessage === null) {
            return null;
        }

        return new class ($this->commitMessage, $this->commitUrl) extends BaseVersionControlSystem {
            public function __construct(private string $message, private string $url) {}

            public function getCommitMessage(string $shortSha): string {
                return $this->message;
            }

            public function getCommitUrl(string $shortSha): string {
                return $this->url;
            }
        };
    }

    public ?FakePodio $podio = null;

    public function podio(): BasePodio {
        return $this->podio ??= new FakePodio();
    }

    public ?FakePubSub $pubSub = null;

    public function pubSub(): BasePubSub {
        return $this->pubSub ??= new FakePubSub();
    }

    public function commitIdentification(ContainerImage $image): ?BaseCommitIdentificationMethod {
        if ($this->shortSha === null) {
            return null;
        }

        return new class ($this->shortSha) extends BaseCommitIdentificationMethod {
            public function __construct(private string $sha) {}

            public function getCommitShortSha(Deployment $deployment): string {
                return $this->sha;
            }
        };
    }

}
