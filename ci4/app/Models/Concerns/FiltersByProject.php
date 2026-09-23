<?php namespace App\Models\Concerns;

use RestExtension\QueryParser;

/**
 * `?filter=project:[1,2,none]` - the workspaces in those projects, and with `none` those in no
 * project, or what belongs to such a workspace: its deployments, their auto updates.
 *
 * A filter of its own rather than `project_id:[…]`, because "in no project" is a NULL, and
 * `project_id IN (…, NULL)` matches no NULL.
 */
trait FiltersByProject {

    /**
     * @param QueryParser $queryParser
     * @param string|array|null $relation how to reach the workspace from this model; null when
     *                                    this is the workspace
     */
    protected function applyProjectFilter($queryParser, $relation = null): void {
        if (!$queryParser->hasFilter('project')) {
            return;
        }

        $filter = $queryParser->getFilter('project')[0];
        $values = is_array($filter->value) ? $filter->value : [$filter->value];
        $values = array_filter(array_map(fn ($value) => trim((string) $value), $values), fn ($value) => $value !== '');

        // Nothing chosen is every project, as a cleared picker means.
        $filter->ignoreAuto = true;
        if (!count($values)) {
            return;
        }

        $ids = array_values(array_map('intval', array_filter($values, fn ($value) => ctype_digit($value))));
        $none = in_array('none', $values, true);

        $this->groupStart();
        if (count($ids)) {
            $relation === null
                ? $this->whereIn('project_id', $ids)
                : $this->whereInRelated($relation, 'project_id', $ids);
        } else {
            // Only "none": the group must not start with an OR.
            $relation === null
                ? $this->where('project_id', null)
                : $this->whereRelated($relation, 'project_id', null);
            $none = false;
        }
        if ($none) {
            $relation === null
                ? $this->orWhere('project_id', null)
                : $this->orWhereRelated($relation, 'project_id', null);
        }
        $this->groupEnd();
    }

}
