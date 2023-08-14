<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\MicroServices\AuthServiceMock;
use Exception;

class OauthStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::Oauth;
    }

    public function getName(): string {
        return 'OAuth Credentials';
    }

    public function hasPreviewCommand(): bool {
        return false;
    }

    public function hasStatusCommand(): bool {
        return true;
    }

    public function hasDeployCommand(): bool {
        return true;
    }

    public function hasTerminateCommand(): bool {
        return false;
    }

    public function hasKubernetesEvents(): bool {
        return false;
    }

    public function hasKubernetesStatus(): bool {
        return false;
    }

    public function getSuccesStatus(): string {
        return DeploymentStepHelper::Oauthtatus_Success;
    }

    public function getPreview(Deployment $deployment): string {
        return '';
    }

    public function getStatus(Deployment $deployment): string {
        if (strlen($deployment->oauth_client_id) > 0
            && strlen($deployment->oauth_client_secret) > 0) {
            return DeploymentStepHelper::Oauthtatus_Success;
        } else if (strlen($deployment->oauth_client_id) > 0
            || strlen($deployment->oauth_client_secret) > 0) {
            return DeploymentStepHelper::OauthStatus_Failed;
        } else {
            return DeploymentStepHelper::OauthStatus_NotPerformed;
        }
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        return null;
    }

    public function startDeployCommand(Deployment $deployment): void {
        if ($this->getStatus($deployment) == DeploymentStepHelper::Oauthtatus_Success) {
            throw new Exception('OAuth Crendentials already created');
        }

        [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ] = AuthServiceMock::CreateOAuthClient();
        $deployment->oauth_client_id = $clientId;
        $deployment->oauth_client_secret = $clientSecret;
        $deployment->save();
    }

    public function startTerminateCommand(Deployment $deployment): void {
        throw new \Exception('OAuth Crendentials cannot be terminated');
    }

    public function getKubernetesEvents(Deployment $deployment): array {
        return [];
    }

    public function getKubernetesStatus(Deployment $deployment): array {
        return [];
    }

}
