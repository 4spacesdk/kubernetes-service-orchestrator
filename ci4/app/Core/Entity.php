<?php namespace App\Core;

use RestExtension\ResourceEntityInterface;

class Entity extends \RestExtension\Core\Entity implements ResourceEntityInterface {

    /**
     * The payload of a change event: this row's own columns, detached from this entity, as
     * it is pushed to every open browser. Two things about it are worth
     * knowing before calling it, and neither is obvious from the code. `EntityCloneTest`
     * holds both.
     *
     * **`original` is not "the row as it was loaded".** OrmExtension's `completeSave()`
     * calls `syncOriginal()`, which folds whatever is in `attributes` at that moment into
     * `original` - loaded relations included, each serialised into a JSON blob of the whole
     * related row. After any save, `original` on a deployment being deployed holds its
     * workspace and its specification beside its columns. The `array_intersect_key` below is
     * the only thing keeping them out of the message, and without it the clone is not merely
     * heavy: the blob lands where a column is expected and `toArray()` is a fatal error. The
     * same five lines are in `OrmExtension\Extensions\Entity::hasChanged()`, for the same
     * reason.
     *
     * **It clones the values from before the last save,** not the ones the entity is
     * holding now. That is only safe because every call site sends its event *after*
     * saving. Announce a change before saving it and the old value is pushed to every
     * browser, which shows it as the new one.
     */
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

    public static function post($data) {
        return parent::post(static::withoutRelationsTheirIdsDecide($data));
    }

    public static function patch($id, $data) {
        return parent::patch($id, static::withoutRelationsTheirIdsDecide($data));
    }

    /**
     * Drops a has-one relation sent as an object when its id column is sent as well.
     *
     * RestExtension writes such an object back: on a patch it patches the related row with
     * it and links it again, on a post it creates a new one. The frontend sends them without
     * meaning to - a dialog loads a row by id, which includes its relations, and saves the
     * whole thing - so changing `container_image_id` on a specification was undone by the
     * `container_image` it had been loaded with, and that image was overwritten with the
     * copy the dialog had held since it opened.
     *
     * The id column is what the form edits, so it decides. An object sent without its id
     * column is left alone.
     */
    protected static function withoutRelationsTheirIdsDecide(mixed $data): mixed {
        if (!is_array($data)) {
            return $data;
        }

        $model = (new static())->_getModel();
        $tableFields = $model->getTableFields();
        foreach ($model->getRelations() as $relation) {
            if ($relation->getType() !== \OrmExtension\DataMapper\RelationDef::HasOne) {
                continue;
            }
            $idField = $relation->getJoinOtherAs();
            if (in_array($idField, $tableFields, true) && array_key_exists($idField, $data)) {
                unset($data[$relation->getSimpleName()]);
            }
        }

        return $data;
    }

    /**
     * Columns that say which row this is rather than what it holds. A copy gets its own.
     */
    private const IdentityFields = ['id', 'created', 'updated', 'created_by_id', 'updated_by_id', 'deletion_id'];

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

    /**
     * An unsaved new row with this row's own columns, for duplicating it.
     *
     * Not `getClone()`: that one keeps the id, because it is the payload of a change event
     * about this very row. Relations are not copied either way - what a copy of them means
     * differs per relation, so the caller decides.
     */
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
