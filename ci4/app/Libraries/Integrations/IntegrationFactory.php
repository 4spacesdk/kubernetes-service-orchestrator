<?php namespace App\Libraries\Integrations;

use App\Entities\ContainerImage;
use App\Libraries\CommitIdentificationMethods\BaseCommitIdentificationMethod;
use App\Libraries\CommitIdentificationMethods\EnvironmentVariableCommitIdentification;
use App\Libraries\ContainerRegistries\AzureContainerRegistry;
use App\Libraries\ContainerRegistries\BaseContainerRegistry;
use App\Libraries\ContainerRegistries\GoogleCloudArtifactRegistry;
use App\Libraries\ContainerRegistries\HarborRegistry;
use App\Libraries\GoogleCloud\BasePubSub;
use App\Libraries\GoogleCloud\PubSubApi;
use App\Libraries\Podio\BasePodio;
use App\Libraries\Podio\PodioApi;
use App\Libraries\VersionControlSystems\BaseVersionControlSystem;
use App\Libraries\VersionControlSystems\GithubVersionControl;

/**
 * Which outside systems a container image talks to.
 *
 * A container image names a registry, a version control provider and a way of finding the
 * commit a running version was built from. Each of those is an abstraction with one
 * implementation per provider; this decides which one, and it is the only place that does.
 *
 * It used to be three `switch` statements inside the `ContainerImage` entity, which meant
 * a data class reached straight for a network client and nothing could get between them.
 * As a service it can be replaced - `Services::injectMock('integrations', ...)` - so the
 * code that uses these can be tested without a registry, a GitHub or a network.
 *
 * The concrete classes are unchanged, and so is the behaviour: an unknown provider still
 * resolves to null, which every caller already handles.
 */
class IntegrationFactory {

    public function containerRegistry(ContainerImage $image): ?BaseContainerRegistry {
        return match ($image->registry_provider) {
            \ContainerRegistries::ArtifactContainerRegistry => new GoogleCloudArtifactRegistry($image),
            \ContainerRegistries::AzureContainerRegistry => new AzureContainerRegistry($image),
            \ContainerRegistries::Harbor => new HarborRegistry($image),
            default => null,
        };
    }

    public function versionControlSystem(ContainerImage $image): ?BaseVersionControlSystem {
        return match ($image->version_control_provider) {
            \VersionControlProviders::GitHub => new GithubVersionControl($image),
            default => null,
        };
    }

    /**
     * Podio is not chosen per image - there is one way to talk to it - but it belongs here
     * for the same reason: it is an outside system, and the code that uses it has to be
     * testable without one.
     */
    public function podio(): BasePodio {
        return new PodioApi();
    }

    /**
     * Google Pub/Sub, which is how an Artifact Registry tells kso that a tag was pushed.
     */
    public function pubSub(): BasePubSub {
        return new PubSubApi();
    }

    public function commitIdentification(ContainerImage $image): ?BaseCommitIdentificationMethod {
        return match ($image->commit_identification_method) {
            \CommitIdentificationMethods::EnvironmentVariable => new EnvironmentVariableCommitIdentification($image),
            default => null,
        };
    }

}
