<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class InitContainerEnvironmentVariable
 * @package App\Entities
 * @property int $init_container_id
 * @property InitContainer $init_container
 * @property string $name
 * @property string $value
 */
class InitContainerEnvironmentVariable extends Entity {

    public static function Create(string $name, string $value): InitContainerEnvironmentVariable {
        $item = new InitContainerEnvironmentVariable();
        $item->name = $name;
        $item->value = $value;
        $item->save();
        return $item;
    }

    public function generateValue(Workspace $workspace, Deployment $deployment): string {
        if (!$deployment->database_service->exists() && $deployment->database_service_id) {
            $deployment->database_service->find();
        }
        if (!$workspace->email_service->exists() && $workspace->email_service_id) {
            $workspace->email_service->find();
        }

        $modifiers = [
            fn(string $value) => str_replace('${namespace}', $deployment->namespace, $value),

            fn(string $value) => str_replace('${database.host}', $deployment->database_service->host, $value),
            fn(string $value) => str_replace('${database.name}', $deployment->database_name, $value),
            fn(string $value) => str_replace('${database.user}', $deployment->database_service->getDatabaseUser($deployment->database_user), $value),
            fn(string $value) => str_replace('${database.pass}', $deployment->database_pass, $value),

            fn(string $value) => str_replace('${emailService.host}', $workspace->email_service->host, $value),
            fn(string $value) => str_replace('${emailService.port}', $workspace->email_service->port, $value),
            fn(string $value) => str_replace('${emailService.user}', $workspace->email_service->user, $value),
            fn(string $value) => str_replace('${emailService.pass}', $workspace->email_service->pass, $value),
            fn(string $value) => str_replace('${emailService.sender}', $workspace->email_service->from, $value),

            fn(string $value) => str_replace('${workspace.id}', $workspace->id, $value),
            fn(string $value) => str_replace('${workspace.name}', $workspace->namespace, $value),

            fn(string $value) => str_replace('${migration.job.name}', $deployment->name, $value),
        ];

        $value = $this->value;
        foreach ($modifiers as $fn) {
            $value = $fn($value);
        }

        return $value;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|InitContainerEnvironmentVariable[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
