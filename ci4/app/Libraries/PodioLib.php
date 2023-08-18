<?php namespace App\Libraries;

use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\DeploymentStep;
use DebugTool\Data;
use Podio;
use PodioComment;
use PodioItem;

class PodioLib {

    private \PodioClient $client;

    public function __construct() {
        $this->client = new \PodioClient(getenv('PODIO_AUTH_CLIENT_ID'), getenv('PODIO_AUTH_CLIENT_SECRET'));
    }

    /**
     * @throws \Exception
     */
    public static function Notify(Deployment $deployment, \Closure $logger): void {
        $logger("notifyPodio $deployment->name $deployment->namespace");
        if (!$deployment->workspace->exists()) {
            $deployment->workspace->find();
        }

        // Get pod
        $pods = (new DeploymentStep())->getPods($deployment);
        $logger('found ' . count($pods) . ' pods');
        foreach ($pods as $pod) {
            $logger("Pod found for $deployment->name with name {$pod->getName()}");
        }
        if (count($pods) == 0) {
            $logger('ERROR no pods found');
            return;
        }

        $env = [];

        foreach ($pods as $pod) {
            // Get environment variables
            $messages = $pod->exec(['/bin/sh', '-c', "printenv"]);
            $all = collect($messages)->where('channel', 'stdout')->all();
            $lines = [];
            foreach ($all as ['channel' => $channel, 'output' => $output]) {
                $lines[] = $output;
            }
            $lines = explode("\n", implode("\n", $lines)); // K8s returning multiple vars in single line. This will fix that.
            $logger('found ' . count($lines) . ' lines');
            foreach ($lines as $line) {
                $parts = explode('=', $line, 2);
                $key = $parts[0];
                if (count($parts) == 2) {
                    $env[$key] = $parts[1];
                } else if (count($parts) == 1) {
                    $env[$key] = null;
                }
            }
            $logger('found ' . count($env) . ' env vars');
        }

        if (isset($env['SHORT_SHA'])) {
            $spec = $deployment->findDeploymentSpecification();
            $shortSha = $env['SHORT_SHA'];
            $shortSha = str_replace("\r", '', $shortSha);
            $logger('SHORT_SHA: ' . $shortSha);

            // Ask github for commit message
            [$commitSha, $commitMessage] = GitHubLib::getCommitMessage($spec->git_repo, $shortSha, $logger);
            if (!$commitMessage) {
                $logger('ERROR Could not find commit message');
            }

            $logger($commitMessage);

            // Find Podio url
            $podioUrl = PodioLib::grabUrlFromText($commitMessage);
            if (!$podioUrl) {
                $logger('Could not find Podio URL in commit message');
                return;
            }
            $logger($podioUrl);

            // Get Podio Item
            $podioLib = new PodioLib();
            $item = $podioLib->getItemFromUrl($podioUrl);
            if (!$item) {
                $logger('ERROR', 'Could not find Podio Item based on URL');
            }

            // Move to phase test
            if ($podioLib->shouldMoveToTest($item)) {
                $podioLib->moveStoryToTest($item);
            }

            // Add commit comment
            $gitHubCommitUrl = GitHubLib::getCommitUrl($spec->git_repo, $commitSha);
            $comment = "[GibHub]($gitHubCommitUrl)";

            // TODO Udfas login-url - eller gør konfigurerbart
            $baseUrl = str_replace('/api', '/app', base_url());
            $requestSupportTokenUrl = "$baseUrl/workspaces/{$deployment->workspace_id}/request-support-login";
            $loginUrl = base_url("login?redirect_uri=$requestSupportTokenUrl");
            $comment .= "\nWorkspace {$deployment->workspace->name_readable} updated [Login]({$loginUrl})";

            $podioLib->addCommitComment($item, $comment);
        }
    }

    public function getItemFromUrl(string $url): ?PodioItem {
        [$_, $storyId] = explode('items/', $url);
        try {
            $this->client->authenticate_with_app(getenv('PODIO_APP_ID'), getenv('PODIO_APP_TOKEN'));
            return PodioItem::get_by_app_item_id($this->client, getenv('PODIO_APP_ID'), $storyId);
        } catch (\Exception $e) {
            Data::debug(get_class($this), $e);
        }
        return null;
    }

    public function shouldMoveToTest(PodioItem $item): bool {
        $isComplete = false;
        $isPhaseDevelopment = false;
        foreach ($item->fields as &$field) {
            if ($field->field_id == getenv('PODIO_APP_PROGRESS_FIELD_ID')) {
                if ($field->values == getenv('PODIO_APP_PROGRESS_CONDITION_VALUE')) {
                    $isComplete = true;
                }
            }
            if ($field->field_id == getenv('PODIO_APP_PHASE_FIELD_ID')) {
                if ($field->values && count($field->values) == 1 && $field->values[0]['text'] == getenv('PODIO_APP_PHASE_CONDITION_VALUE')) {
                    $isPhaseDevelopment = true;
                }
            }
        }
        return $isComplete && $isPhaseDevelopment;
    }

    public function moveStoryToTest(PodioItem $item): void {
        PodioItem::update_values($this->client, $item->item_id, [
            getenv('PODIO_APP_PHASE_FIELD_ID') => getenv('PODIO_APP_PHASE_NEW_VALUE'),
            getenv('PODIO_APP_PROGRESS_CONDITION_VALUE') => getenv('PODIO_APP_PROGRESS_NEW_VALUE'),
        ]);
    }

    public function addCommitComment(PodioItem $item, string $comment): void {
        PodioComment::create($this->client, 'item', $item->item_id, ['value' => $comment, 'created_by' => 'Robot']);
    }

    public static function grabUrlFromText(string $text): ?string {
        if (str_contains($text, 'ignore')) {
            return null;
        }

        preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $text, $match);
        if (count($match[0]) == 1) {
            return $match[0][0];
        }

        return null;
    }

}
