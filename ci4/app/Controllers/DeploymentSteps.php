<?php namespace App\Controllers;

use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\BaseDeploymentStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\Kubernetes\KubeHelper;
use DebugTool\Data;

class DeploymentSteps extends \App\Core\BaseController {

    private function validateStep(string $identifier): ?array {
        $step = DeploymentStepHelper::GetStep($identifier);
        if (!$step) {
            $this->fail('unknown step');
            return null;
        }

        $deployment = new Deployment();
        $deployment->find($this->request->getGet('deploymentId'));
        if (!$deployment->exists()) {
            $this->fail('unknown deployment');
            return null;
        }

        $spec = $deployment->findDeploymentSpecification();

        if (!$spec->hasDeploymentStep($deployment, get_class($step))) {
            $this->fail('deployment not allowed to perform this step');
            return null;
        }

        return [$step, $deployment];
    }

    /**
     * @route /deployment-steps/{identifier}/status
     * @method get
     * @custom true
     * @param string $identifier
     * @parameter int $deploymentId parameterType=query
     * @responseSchema StringArrayInterface
     */
    public function getStatus(string $identifier): void {
        $valid = $this->validateStep($identifier);
        if (!$valid) {
            return;
        }
        /**
         * @var BaseDeploymentStep $step
         * @var Deployment $deployment
         */
        [$step, $deployment] = $valid;

        try {
            $status = $step->getStatus($deployment);
            Data::set('resource', [
                'values' => is_array($status) ? $status : [$status],
            ]);
        } catch (\Exception $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->success();
    }

    /**
     * @route /deployment-steps/{identifier}/preview
     * @method get
     * @custom true
     * @param string $identifier
     * @parameter int $deploymentId parameterType=query
     * @responseSchema StringInterface
     */
    public function getPreview(string $identifier) {
        $valid = $this->validateStep($identifier);
        if (!$valid) {
            return;
        }
        /**
         * @var BaseDeploymentStep $step
         * @var Deployment $deployment
         */
        [$step, $deployment] = $valid;

        /*$error = $step->validateDeployCommand($deployment);
        if ($error) {
            $this->fail($error);
            return;
        }*/

        try {
            Data::set('resource', [
                'value' => $step->getPreview($deployment)
            ]);
        } catch (\Exception $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->success();
    }

    /**
     * @route /deployment-steps/{identifier}/deploy
     * @method put
     * @custom true
     * @param string $identifier
     * @parameter int $deploymentId parameterType=query
     */
    public function deploy(string $identifier) {
        $valid = $this->validateStep($identifier);
        if (!$valid) {
            return;
        }
        /**
         * @var BaseDeploymentStep $step
         * @var Deployment $deployment
         */
        [$step, $deployment] = $valid;

        $error = $step->tryExecuteDeployCommand($deployment);
        if ($error) {
            $this->fail($error);
            return;
        }

        $deployment->checkStatus();

        Data::set('resource', $step);
        $this->success();
    }

    /**
     * @route /deployment-steps/{identifier}/terminate
     * @method put
     * @custom true
     * @parameter int $deploymentId parameterType=query
     * @param string $identifier
     */
    public function terminate(string $identifier) {
        $valid = $this->validateStep($identifier);
        if (!$valid) {
            return;
        }
        /**
         * @var BaseDeploymentStep $step
         * @var Deployment $deployment
         */
        [$step, $deployment] = $valid;

        $error = $step->validateDeployCommand($deployment);
        if ($error) {
            $this->fail($error);
            return;
        }

        try {
            $step->startTerminateCommand($deployment);
        } catch (\Exception $e) {
            $this->fail(KubeHelper::PrintException($e));
        }

        $deployment->checkStatus();

        Data::set('resource', $step);
        $this->success();
    }

    /**
     * @route /deployment-steps/{identifier}/kubernetes-status
     * @method get
     * @custom true
     * @param string $identifier
     * @parameter int $deploymentId parameterType=query
     * @responseSchema StringInterface
     */
    public function getKubernetesStatus(string $identifier) {
        $valid = $this->validateStep($identifier);
        if (!$valid) {
            return;
        }
        /**
         * @var BaseDeploymentStep $step
         * @var Deployment $deployment
         */
        [$step, $deployment] = $valid;

        $error = $step->validateDeployCommand($deployment);
        if ($error) {
            $this->fail($error);
            return;
        }

        try {
            Data::set('resource', [
                'value' => $step->getKubernetesStatus($deployment)
            ]);
        } catch (\Exception $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->success();
    }

    /**
     * @route /deployment-steps/{identifier}/kubernetes-events
     * @method get
     * @custom true
     * @param string $identifier
     * @parameter int $deploymentId parameterType=query
     * @responseSchema StringInterface
     */
    public function getKubernetesEvents(string $identifier) {
        $valid = $this->validateStep($identifier);
        if (!$valid) {
            return;
        }
        /**
         * @var BaseDeploymentStep $step
         * @var Deployment $deployment
         */
        [$step, $deployment] = $valid;

        $error = $step->validateDeployCommand($deployment);
        if ($error) {
            $this->fail($error);
            return;
        }

        try {
            Data::set('resource', [
                'value' => $step->getKubernetesEvents($deployment)
            ]);
        } catch (\Exception $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->success();
    }

    public function requireAuth(string $method): bool {
        return true;
    }
}
