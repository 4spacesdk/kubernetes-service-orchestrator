<?php namespace App\Tests\Fakes;

use CodeIgniter\CLI\BaseCommand;

/**
 * A spark command that throws the one exception `Jobby::run()` catches.
 *
 * The controller catches `\ReflectionException` around `command()`, and the shape of the
 * code suggests an unknown command name is what raises it. It is not: `Commands::run()`
 * looks the name up in its own registry, and when it is not there it writes "Command not
 * found" and returns `EXIT_ERROR`. No reflection, no exception.
 *
 * So the branch can only be reached by a command that throws on its own - and that is what
 * this is, so that the controller answering `success()` anyway, with the message in the
 * log, is pinned down.
 */
class ReflectionFailingCommand extends BaseCommand {

    public const NAME = 'kso:reflection-failure';
    public const MESSAGE = 'the command could not be reflected';

    protected $group = 'Testing';
    protected $name = self::NAME;
    protected $description = 'Throws a ReflectionException.';

    public function run(array $params) {
        throw new \ReflectionException(self::MESSAGE);
    }

}
