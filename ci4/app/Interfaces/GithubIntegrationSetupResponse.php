<?php namespace App\Interfaces;

/**
 * Where to send the operator next in setting up a GitHub integration, and for creating the
 * App, the manifest to post there - as JSON, which is how GitHub takes it.
 *
 * Interface GithubIntegrationSetupResponse
 * @package App\Interfaces
 * @property string $url
 * @property string $manifest
 */
interface GithubIntegrationSetupResponse {

}
