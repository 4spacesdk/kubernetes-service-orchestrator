<?php namespace App\Models\Concerns;

use App\Models\LabelModel;
use RestExtension\Exceptions\InvalidRequestException;
use RestExtension\QueryParser;

/**
 * `?filter=label:name=value` on a resource that has labels.
 *
 * Three models carried a copy of this each - workspaces, deployments and deployment
 * specifications - identical down to the variable names, which is why the two faults below
 * existed in triplicate.
 *
 * The condition is a subquery per selector rather than a join, because every selector has
 * to match: a join would answer a row that carries any one of them.
 */
trait FiltersByLabel {

    /**
     * @param QueryParser $queryParser
     * @throws InvalidRequestException
     */
    protected function applyLabelFilter($queryParser): void {
        // The comma that separates two selectors is the same comma `QueryParser` splits
        // *filters* on, so `label:a=1,b=2` arrives as two filters: `label` with the first
        // selector, and one whose property is the empty string. That second one used to be
        // applied as an ordinary condition on a column called `''`, and the request ended
        // as a database error with `= 'ier=web'` in the message.
        //
        // Refused rather than dropped. Dropping it would answer 200 with the first selector
        // applied and the second silently gone - a narrower question answered wider, which
        // is the whole complaint about this filter.
        foreach ($queryParser->getFilters() as $filter) {
            if ($filter->property === '') {
                throw new InvalidRequestException(
                    'A filter value containing a comma has to be quoted: filter=label:"a=1,b=2"'
                );
            }
        }

        if (!$queryParser->hasFilter('label')) {
            return;
        }

        $filter = $queryParser->getFilter('label')[0];
        $selectors = explode(',', $filter->value);

        foreach ($selectors as $selector) {
            // `explode('=')` on a selector without one used to hand back a single element
            // and read `[1]` off it: `Undefined array key 1`, which is a 500 for a query
            // string anybody can type.
            if (!str_contains($selector, '=')) {
                throw new InvalidRequestException(
                    "A label selector has to be name=value, and '{$selector}' is not"
                );
            }

            [$name, $value] = explode('=', $selector, 2);

            $labelSubQuery = (new LabelModel())
                ->select('COUNT(*) as count', true, false)
                ->whereRelated(static::class, 'id', '${parent}.id', false)
                ->where('name', $name)
                ->where('value', $value)
                ->having('count >', 0, true, false);

            $this->whereSubQuery($labelSubQuery, '', null, false);
        }

        // Set last, and that is the point of this method existing. `ignoreAuto` tells the
        // extension not to apply the filter itself - so anything that sets it and then
        // decides not to add a condition has thrown the filter away rather than widened it.
        // See the status filter on `WorkspaceModel` for what that looked like.
        $filter->ignoreAuto = true;
    }

}
