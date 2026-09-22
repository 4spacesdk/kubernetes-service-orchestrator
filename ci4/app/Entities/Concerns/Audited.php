<?php namespace App\Entities\Concerns;

use App\Libraries\Audit\Audit;

/**
 * Every save, delete and change of a relation writes its row in the audit trail, in the same
 * transaction - see `Audit`.
 *
 * Here rather than in the controllers because this is the one way into the tables: the REST
 * routes, the custom endpoints, cron, the queue and the commands all save through it. What
 * goes around it - the query builder - is held to a list by the tests.
 *
 * The writes the ORM does on the way - the save that marks a row deleted, the foreign key a
 * relation sets - are part of the one change being recorded, not changes of their own.
 */
trait Audited {

    /** Set while this entity is written as part of a change recorded elsewhere. */
    private bool $auditPaused = false;

    public function save($related = null, $relatedField = null) {
        if ($related !== null || $this->auditPaused || !Audit::Covers($this)) {
            parent::save($related, $relatedField);
            return;
        }

        $isNew = !$this->exists();
        $before = $isNew ? [] : Audit::StoredFields($this, true);

        Audit::Atomically(function () use ($isNew, $before) {
            parent::save();

            $changes = Audit::Changes($this, $before, Audit::StoredFields($this, false));
            if ($isNew || $changes !== []) {
                Audit::Record($isNew ? Audit::Created : Audit::Updated, $this, ['changes' => $changes]);
            }
        });
    }

    public function delete($related = null) {
        if ($related !== null || $this->auditPaused || !Audit::Covers($this) || !$this->exists()) {
            parent::delete($related);
            return;
        }

        $before = Audit::StoredFields($this, true);

        Audit::Atomically(function () use ($before) {
            $this->withAuditPaused(fn() => parent::delete());

            Audit::Record(Audit::Deleted, $this, ['changes' => Audit::Changes($this, $before, [])]);
        });
    }

    public function saveRelation($related, $relationName = null) {
        $this->writeRelation($related, $relationName, Audit::RelationAdded, fn() => parent::saveRelation($related, $relationName));
    }

    public function deleteRelation($related, $relationName = null) {
        $this->writeRelation($related, $relationName, Audit::RelationRemoved, fn() => parent::deleteRelation($related, $relationName));
    }

    private function writeRelation($related, ?string $relationName, string $action, callable $write): void {
        if ($this->auditPaused || !Audit::Covers($this) || !$this->exists()) {
            $write();
            return;
        }

        Audit::Atomically(function () use ($related, $relationName, $action, $write) {
            $this->withAuditPaused(function () use ($related, $write) {
                if (is_object($related) && method_exists($related, 'withAuditPaused')) {
                    $related->withAuditPaused($write);
                } else {
                    $write();
                }
            });

            Audit::Record($action, $this, ['relation' => array_filter([
                'name' => $relationName,
                'type' => is_object($related) ? Audit::TypeOf($related) : (string) $related,
                'id' => is_object($related) && $related->id ? (int) $related->id : null,
            ], fn($value) => $value !== null)]);
        });
    }

    /**
     * @template T
     * @param callable(): T $write
     * @return T
     */
    public function withAuditPaused(callable $write): mixed {
        $wasPaused = $this->auditPaused;
        $this->auditPaused = true;
        try {
            return $write();
        } finally {
            $this->auditPaused = $wasPaused;
        }
    }

}
