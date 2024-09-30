<?php namespace App\Libraries\CommitIdentificationMethods;

use App\Entities\ContainerImage;
use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\DeploymentStep;
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
            $pods = (new DeploymentStep())->getPods($deployment);
            Data::debug('found ' . count($pods) . ' pods');
            foreach ($pods as $pod) {
                Data::debug("Pod found for $deployment->name with name {$pod->getName()}");
            }
            if (count($pods) == 0) {
                Data::debug('ERROR no pods found');
                return "";
            }

            foreach ($pods as $pod) {
                // Get environment variables
                $messages = $pod->exec(['/bin/sh', '-c', "printenv"]);
                $all = collect($messages)->where('channel', 'stdout')->all();
                $lines = [];
                foreach ($all as ['channel' => $channel, 'output' => $output]) {
                    $lines[] = $output;
                }
                $lines = explode("\n", implode("\n", $lines)); // K8s returning multiple vars in single line. This will fix that.
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
            }
        } catch (\Exception $e) {
            Data::debug($e->getMessage());
        }

        return str_replace("\r", '', $env[$this->containerImage->commit_identification_environment_variable_name] ?? '');
    }

}