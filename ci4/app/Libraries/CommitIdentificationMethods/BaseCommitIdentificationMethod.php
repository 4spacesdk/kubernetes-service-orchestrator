<?php namespace App\Libraries\CommitIdentificationMethods;

use App\Entities\Deployment;

abstract class BaseCommitIdentificationMethod {

    public abstract function getCommitShortSha(Deployment $deployment): string;

}
