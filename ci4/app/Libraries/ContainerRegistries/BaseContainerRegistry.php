<?php namespace App\Libraries\ContainerRegistries;

abstract class BaseContainerRegistry {

    public abstract function getRepoName(): string;

    public abstract function getTags(): array;

}
