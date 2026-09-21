<?php namespace App\Entities;

use App\Core\Entity;
use App\Entities\Concerns\SecretEnvironmentVariable;

/**
 * Class EnvironmentVariable
 * @package App\Entities
 * @property int $deployment_id
 * @property Deployment $deployment
 * @property string $name
 * @property string $value
 * @property bool $is_secret the value is write-only when set, see SecretEnvironmentVariable
 * @property bool $has_value
 */
class EnvironmentVariable extends Entity {

    use SecretEnvironmentVariable;

    public static function Prepare(string $name, string $value, bool $isSecret = false): EnvironmentVariable {
        $item = new EnvironmentVariable();
        $item->name = $name;
        $item->value = $value;
        $item->is_secret = $isSecret;
        return $item;
    }

    public static function Create(string $name, string $value, bool $isSecret = false): EnvironmentVariable {
        $item = self::Prepare($name, $value, $isSecret);
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
            fn(string $value) => str_replace('${namespace}', (string) $deployment->namespace, $value),

            fn(string $value) => str_replace('${database.host}', (string) $deployment->database_service->host, $value),
            fn(string $value) => str_replace('${database.port}', (string) $deployment->database_service->port, $value),
            fn(string $value) => str_replace('${database.name}', (string) $deployment->database_name, $value),
            fn(string $value) => str_replace('${database.user}', (string) $deployment->database_service->getDatabaseUser($deployment->database_user), $value),
            fn(string $value) => str_replace('${database.pass}', (string) $deployment->database_pass, $value),

            fn(string $value) => str_replace('${emailService.host}', (string) $deployment->workspace->email_service->host, $value),
            fn(string $value) => str_replace('${emailService.port}', (string) $deployment->workspace->email_service->port, $value),
            fn(string $value) => str_replace('${emailService.user}', (string) $deployment->workspace->email_service->user, $value),
            fn(string $value) => str_replace('${emailService.pass}', (string) $deployment->workspace->email_service->pass, $value),
            fn(string $value) => str_replace('${emailService.sender}', (string) $deployment->workspace->email_service->from, $value),

            fn(string $value) => str_replace('${domain.host}', (string) $deployment->workspace->domain->name, $value),

            fn(string $value) => str_replace('${deployment.name}', (string) $deployment->name, $value),

            fn(string $value) => str_replace('${workspace.id}', (string) $deployment->workspace->id, $value),
            fn(string $value) => str_replace('${workspace.name}', (string) $deployment->workspace->namespace, $value),
            fn(string $value) => str_replace('${workspace.subdomain}', (string) $deployment->workspace->subdomain, $value),

            fn(string $value) => str_replace('${migration.job.name}', (string) $deployment->name, $value),
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
