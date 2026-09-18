<?php namespace App\Tests\Fakes;

use CodeIgniter\CLI\BaseCommand;

/**
 * A spark command that does nothing but say it ran.
 *
 * `Jobby::run()` executes the command its cron job row points at through CodeIgniter's
 * `command()`, and that is a real run rather than a stand-in. The real commands pull
 * registries, talk to the cluster and write rows, so a test that only wants to know
 * *whether* the command was run needs one whose sole side effect is a line in the output
 * buffer.
 *
 * Command discovery does not find it - it lives outside `app/Commands` - so a test writes
 * it into the registry itself. See `JobbyApiTest::registerTheFakeCommands()`.
 */
class HarmlessCommand extends BaseCommand {

    public const NAME = 'kso:harmless';
    public const OUTPUT = 'the harmless command ran';

    protected $group = 'Testing';
    protected $name = self::NAME;
    protected $description = 'Does nothing, and says so.';

    public function run(array $params) {
        // `echo` rather than `CLI::write()`: `command()` captures a command's output with
        // `ob_start()`, and CLI writes with `fwrite(STDOUT)`, which goes around the buffer.
        echo self::OUTPUT;

        return EXIT_SUCCESS;
    }

}
