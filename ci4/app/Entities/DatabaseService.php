<?php namespace App\Entities;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;
use DatabaseDrivers;
use DebugTool\Data;
use RestExtension\Core\Entity;

/**
 * Class DatabaseService
 * @package App\Entities
 * @property string $name
 * @property string $driver
 * @property string $host
 * @property string $azure_host
 * @property string $port
 * @property string $user
 * @property string $pass
 *
 * Many
 * @property Workspace $workspaces
 * @property Deployment $deployments
 */
class DatabaseService extends Entity {

    public function getDatabaseUser($name): string {
        if (getenv('IS_AZURE')) {
            return  "{$name}@{$this->azure_host}";
        } else {
            return $name;
        }
    }

    public function prepareConnection(): BaseConnection {
        return Database::connect([
            'DSN'      => '',
            'hostname' => $this->host,
            'username' => $this->user,
            'password' => $this->pass,
            'database' => '',
            'DBDriver' => match ($this->driver) {
                \DatabaseDrivers::MySQL => 'MySQLi',
                \DatabaseDrivers::MSSQL => 'SQLSRV',
            },
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug'  => (ENVIRONMENT !== 'production'),
            'charset'  => 'utf8',
            'DBCollat' => 'utf8_general_ci',
            'swapPre'  => '',
            'encrypt'  => false,
            'compress' => false,
            'strictOn' => false,
            'failover' => [],
            'port' => $this->port,
        ]);
    }

    public function testConnection(): bool {
        try {
            $db = $this->prepareConnection();
            switch ($this->driver) {
                case DatabaseDrivers::MySQL:
                    $test = $db->query('SELECT version()');
                    Data::debug($test);
                    break;
                case DatabaseDrivers::MSSQL:
                    $test = $db->query('SELECT 1');
                    Data::debug($test);
                    break;
            }
            return true;
        } catch (\Exception|DatabaseException $e) {
            Data::debug($e->getMessage());
        }
        return false;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DatabaseService[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
