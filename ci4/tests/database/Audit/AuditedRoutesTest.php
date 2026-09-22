<?php namespace App\Tests\Database\Audit;

use App\DatabaseTestCase;
use RestExtension\ResourceControllerTrait;

/**
 * Every route that can change something has decided what the audit trail says about it, in
 * an `@audit` tag on its method:
 *
 * * `@audit entity` - what it changes it saves through the entities, which record it
 *   themselves (`Audited`).
 * * `@audit <action>` - an action that is not a write, recorded with
 *   `Audit::Record('<action>', ...)` in the method.
 * * `@audit none <why>` - it changes nothing anyone did: a callback, or a read sent as PUT.
 *
 * The REST resource routes the controllers inherit save through the entities, and need none.
 * A new route without a tag fails here, which is the point: nothing reaches the tables
 * without somebody having decided whether it is recorded.
 */
class AuditedRoutesTest extends DatabaseTestCase {

    private const array InheritedWrites = ['post', 'put', 'patch', 'delete'];

    public function testEveryRouteThatWritesHasDecidedWhatTheTrailSays(): void {
        $this->assertSame([], array_keys(array_filter($this->writeRoutes(), fn($tag) => $tag === null)));
    }

    public function testANamedActionIsRecordedByTheMethodThatNamesIt(): void {
        $missing = [];
        foreach ($this->writeRoutes() as $route => $tag) {
            if ($tag === null || in_array(strtok($tag, ' '), ['entity', 'none'], true)) {
                continue;
            }
            $method = $this->methodOf($route);
            $source = implode('', array_slice(file($method->getFileName()), $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
            if (!str_contains($source, "Audit::Record('{$tag}'")) {
                $missing[] = "{$route} ({$tag})";
            }
        }

        $this->assertSame([], $missing);
    }

    public function testNoneSaysWhy(): void {
        $unexplained = array_keys(array_filter($this->writeRoutes(), fn($tag) => $tag === 'none'));

        $this->assertSame([], $unexplained);
    }

    /**
     * @return array<string, ?string> "method path => Controller::method" => the tag, null when
     *   there is none; inherited resource writes left out
     */
    private function writeRoutes(): array {
        $routes = [];
        foreach ($this->db->table('api_routes')->where('method !=', 'get')->get()->getResultArray() as $row) {
            $key = "{$row['method']} {$row['from']} => " . strtok($row['to'], '/');
            $method = $this->methodOf($key);
            if ($method === null) {
                continue;
            }
            if (in_array($method->getName(), self::InheritedWrites, true) && $this->isInherited($method)) {
                continue;
            }
            $routes[$key] = preg_match('/@audit\s+([^\n*]+)/', (string) $method->getDocComment(), $matches) ? trim($matches[1]) : null;
        }
        ksort($routes);
        return $routes;
    }

    private function methodOf(string $route): ?\ReflectionMethod {
        [, $target] = explode(' => ', $route);
        [$class, $name] = explode('::', $target);
        return method_exists($class, $name) ? new \ReflectionMethod($class, $name) : null;
    }

    private function isInherited(\ReflectionMethod $method): bool {
        return $method->getFileName() === (new \ReflectionClass(ResourceControllerTrait::class))->getFileName();
    }

}
