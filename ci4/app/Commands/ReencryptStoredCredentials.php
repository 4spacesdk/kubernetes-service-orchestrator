<?php namespace App\Commands;

use App\Libraries\Reencryption;
use AuthExtension\AuthExtension;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * The last step of rotating `ENCRYPTION_KEY`: write every stored credential again with the
 * new key. Afterwards the old one can be taken out of `ENCRYPTION_PREVIOUS_KEYS`.
 *
 * Run once, by hand, in a pod that has both keys. Running it again is harmless.
 */
class ReencryptStoredCredentials extends BaseCommand {

    public $group = 'app';
    public $name = 'app:reencrypt';
    public $description = 'Write every stored credential again with the current encryption key';
    protected $arguments = [

    ];
    protected $options = [

    ];

    public function run(array $params) {
        $result = (new Reencryption())->run();

        // The OAuth signing keys, which CI4AuthExtension encrypts with the same key. Here rather
        // than in `Reencryption`: the OAuth storage writes on a connection of its own, which a
        // test's transaction does not roll back.
        $signingKeys = AuthExtension::reencryptSigningKeys();
        $result['rewritten'] += $signingKeys['rewritten'];
        $result['unreadable'] = [...$result['unreadable'], ...$signingKeys['unreadable']];

        CLI::write("Rewrote {$result['rewritten']} values with the current key.");
        if ($result['unreadable'] !== []) {
            CLI::error(count($result['unreadable']) . ' could not be read with any key, and were left as they are:');
            foreach ($result['unreadable'] as $value) {
                CLI::write("  {$value}");
            }
            CLI::error('Keep the old keys in ENCRYPTION_PREVIOUS_KEYS until these are sorted out.');
            return EXIT_ERROR;
        }
        CLI::write('The old keys can be removed from ENCRYPTION_PREVIOUS_KEYS.');

        return EXIT_SUCCESS;
    }

}
