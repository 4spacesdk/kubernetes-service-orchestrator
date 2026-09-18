<?php namespace App\Core;

use RestExtension\ResourceEntityInterface;

class Entity extends \RestExtension\Core\Entity implements ResourceEntityInterface {

    public function getClone(): Entity {
        $className = get_called_class();
        $item = new $className();

        // Skip relations
        $tableFields = [];
        foreach ($this->getTableFields() as $tableField) {
            $tableFields[$tableField] = $tableField;
        }
        $original = array_intersect_key($this->original, $tableFields);
        foreach ($original as $key => $value) {
            $item->{$key} = $value;
        }

        return $item;
    }

    /**
     * Columns that say which row this is rather than what it holds. A copy gets its own.
     */
    private const IdentityFields = ['id', 'created', 'updated', 'created_by_id', 'updated_by_id', 'deletion_id'];

    /**
     * An unsaved new row with this row's own columns, for duplicating it.
     *
     * Not `getClone()`: that one keeps the id, because it is the payload of a change event
     * about this very row. Relations are not copied either way - what a copy of them means
     * differs per relation, so the caller decides.
     */
    /**
     * Run a write of several rows as one. An exception rolls all of it back and is thrown on.
     *
     * Inside an outer transaction - every database test runs in one - CodeIgniter only
     * counts the depth, and the outer one decides.
     */
    protected static function inTransaction(callable $write): mixed {
        $db = \Config\Database::connect();
        $db->transBegin();
        try {
            $result = $write();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
        $db->transCommit();
        return $result;
    }

    public function getCopy(): static {
        $copy = new static();
        foreach ($this->getTableFields() as $field) {
            if (!in_array($field, self::IdentityFields, true)) {
                $copy->{$field} = $this->{$field};
            }
        }
        return $copy;
    }

}
