<?php namespace App\Models\Concerns;

use RestExtension\QueryParser;

/**
 * `?filter=health:[healthy,degraded,none]` - those healths, and with `none` the rows that have
 * none: a Draft, or one the health check had nothing to say about.
 *
 * Taken over from the automatic filter for the same reason as `project`: "no health" is a NULL,
 * and `health IN (…)` matches no NULL. The deployments list hides Suspended by listing every
 * other health, and without `none` a deployment that has not been checked yet would vanish from
 * it without a word.
 */
trait FiltersByHealth {

    /**
     * @param QueryParser $queryParser
     */
    protected function applyHealthFilter($queryParser): void {
        if (!$queryParser->hasFilter('health')) {
            return;
        }

        $filter = $queryParser->getFilter('health')[0];
        $values = is_array($filter->value) ? $filter->value : [$filter->value];
        $values = array_values(array_filter(
            array_map(fn ($value) => trim((string) $value), $values),
            fn ($value) => $value !== ''
        ));

        // Nothing chosen is every health, as a cleared picker means.
        $filter->ignoreAuto = true;
        if (!count($values)) {
            return;
        }

        $healths = array_values(array_filter($values, fn ($value) => $value !== 'none'));
        $none = in_array('none', $values, true);

        $this->groupStart();
        if (count($healths)) {
            $this->whereIn('health', $healths);
            if ($none) {
                $this->orWhere('health', null);
            }
        } else {
            $this->where('health', null);
        }
        $this->groupEnd();
    }

}
