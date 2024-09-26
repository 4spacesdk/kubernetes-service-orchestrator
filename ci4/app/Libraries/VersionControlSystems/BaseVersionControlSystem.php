<?php namespace App\Libraries\VersionControlSystems;

abstract class BaseVersionControlSystem {

    public abstract function getCommitMessage(string $shortSha): string;
    public abstract function getCommitUrl(string $shortSha): string;

}
