<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class EnvironmentVariable
 * @package App\Entities
 * @property int $deployment_id
 * @property Deployment $deployment
 * @property string $name
 * @property string $value
 */
class EnvironmentVariable extends Entity {

    public static function Prepare(string $name, string $value): EnvironmentVariable {
        $item = new EnvironmentVariable();
        $item->name = $name;
        $item->value = $value;
        return $item;
    }

    public static function Create(string $name, string $value): EnvironmentVariable {
        $item = self::Prepare($name, $value);
        $item->save();
        return $item;
    }

    public static function ApplyVariablesToString(string $value, Deployment $deployment): string {
        if (!$deployment->database_service->exists() && $deployment->database_service_id) {
            $deployment->database_service->find();
        }
        if ($deployment->workspace_id) {
            if (!$deployment->workspace->exists()) {
                $deployment->workspace->find();
            }
            if (!$deployment->workspace->email_service->exists() && $deployment->workspace->email_service_id) {
                $deployment->workspace->email_service->find();
            }
            if (!$deployment->workspace->domain->exists() && $deployment->workspace->domain_id) {
                $deployment->workspace->domain->find();
            }
        }

        $modifiers = [
            fn(string $value) => str_replace('${namespace}', $deployment->namespace, $value),

            fn(string $value) => str_replace('${database.host}', $deployment->database_service->host, $value),
            fn(string $value) => str_replace('${database.name}', $deployment->database_name, $value),
            fn(string $value) => str_replace('${database.user}', $deployment->database_service->getDatabaseUser($deployment->database_user), $value),
            fn(string $value) => str_replace('${database.pass}', $deployment->database_pass, $value),

            fn(string $value) => str_replace('${emailService.host}', $deployment->workspace->email_service->host, $value),
            fn(string $value) => str_replace('${emailService.port}', $deployment->workspace->email_service->port, $value),
            fn(string $value) => str_replace('${emailService.user}', $deployment->workspace->email_service->user, $value),
            fn(string $value) => str_replace('${emailService.pass}', $deployment->workspace->email_service->pass, $value),
            fn(string $value) => str_replace('${emailService.sender}', $deployment->workspace->email_service->from, $value),

            fn(string $value) => str_replace('${domain.host}', $deployment->workspace->domain->name, $value),

            fn(string $value) => str_replace('${deployment.subdomain}', $deployment->subdomain, $value),

            fn(string $value) => str_replace('${workspace.id}', $deployment->workspace->id, $value),
            fn(string $value) => str_replace('${workspace.name}', $deployment->workspace->namespace, $value),

            fn(string $value) => str_replace('${migration.job.name}', $deployment->name, $value),
        ];

        foreach ($modifiers as $fn) {
            $value = $fn($value);
        }

        return $value;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|EnvironmentVariable[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
