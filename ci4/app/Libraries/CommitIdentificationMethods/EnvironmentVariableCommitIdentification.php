<?php namespace App\Libraries\CommitIdentificationMethods;

use App\Entities\ContainerImage;
use App\Entities\Deployment;
use App\Libraries\Kubernetes\RunJobHelper;
use DebugTool\Data;

class EnvironmentVariableCommitIdentification extends BaseCommitIdentificationMethod {

    private ContainerImage $containerImage;

    public function __construct(ContainerImage $containerImage) {
        $this->containerImage = $containerImage;
    }

    public function getCommitShortSha(Deployment $deployment): string {
        if (!$deployment->workspace->exists()) {
            $deployment->workspace->find();
        }

        $env = [];

        try {
            $runJobHelper = new RunJobHelper();
            $log = $runJobHelper->runJob($deployment, 'printenv');
            if (!$log) {
                Data::debug("EnvironmentVariableCommitIdentification failed cause of missing log from run-job");
                return "";
            }
            $lines = explode("\n", $log); // K8s returning multiple vars in single line. This will fix that.
            Data::debug('found ' . count($lines) . ' lines');
            foreach ($lines as $line) {
                $parts = explode('=', $line, 2);
                $key = $parts[0];
                if (count($parts) == 2) {
                    $env[$key] = $parts[1];
                } else if (count($parts) == 1) {
                    $env[$key] = null;
                }
            }
            Data::debug('found ' . count($env) . ' env vars');
        } catch (\Exception $e) {
            Data::debug($e->getMessage());
        }

        return str_replace("\r", '', $env[$this->containerImage->commit_identification_environment_variable_name] ?? '');
    }

}
