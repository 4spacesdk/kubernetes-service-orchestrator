<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use RestExtension\Exceptions\InvalidRequestException;

/**
 * Which columns a query string may name - in a filter, a search, `fields` or `ordering`.
 *
 * Only the model's own columns, and none of the ones its entity hides from every answer. A
 * filter reached the query builder as it was written: any column, the password hash included,
 * which `?filter=password:~$2y$10$a` could read a character at a time. The users resource is the
 * one with the most to hide, so it is the one asked here.
 *
 * A refusal is an `InvalidRequestException`, which the application answers with a 400; under
 * test it comes out as the exception, as in `ListSortingApiTest`.
 */
class QueryAllowListApiTest extends ControllerTestCase {

    public function testFilteringOnAHiddenColumnIsRefused(): void {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage("Cannot filter on 'password': it is not a field");

        $this->signedIn()->get('users?filter=password:~$2y');
    }

    /**
     * With the same words as a column that is not there, so the answer does not say it exists.
     */
    public function testAColumnThatIsNotThereIsRefusedInTheSameWords(): void {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage("Cannot filter on 'no_such_column': it is not a field");

        $this->signedIn()->get('users?filter=no_such_column:~$2y');
    }

    public function testSortingByAHiddenColumnIsRefused(): void {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage("Cannot order by 'password': it is not a field");

        $this->signedIn()->get('users?ordering=password:asc');
    }

    public function testSelectingAHiddenColumnIsRefused(): void {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage("Cannot select 'password': it is not a field");

        $this->signedIn()->get('users?fields=password');
    }

    /**
     * Through a relation, too: the field has to be a column of the related model.
     */
    public function testARelationFilterNamesAColumnOfTheRelatedModel(): void {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage("Cannot filter on 'workspace.no_such_column': it is not a field");

        $this->signedIn()->get('deployments?filter=workspace.no_such_column:1');
    }

    public function testAnOrdinaryColumnStillFiltersSortsAndSelects(): void {
        foreach (['users?filter=username:~a', 'users?ordering=username:asc', 'users?fields=username', 'deployments?filter=workspace.name_readable:x'] as $url) {
            $this->assertSame(200, $this->signedIn()->get($url)->response()->getStatusCode(), $url);
        }
    }

}
