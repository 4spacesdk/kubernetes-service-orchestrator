<?php namespace App\Database\Migrations;

use App\Controllers\OAuthAgent;
use App\Entities\DeploymentSpecification;
use App\Models\ContainerImageModel;
use App\Models\DeploymentSpecificationModel;
use AuthExtension\Entities\OAuthClient;
use AuthExtension\Entities\OAuthPublicKey;
use AuthExtension\Entities\OAuthScope;
use AuthExtension\Migration\Upgrade_1_1_0;
use AuthExtension\Models\OAuthClientModel;
use AuthExtension\Models\OAuthScopeModel;
use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class UpdateAuthExtension extends Migration {

    public function up() {
        Upgrade_1_1_0::migrateUp();

        Database::connect()->table('oauth_clients')->truncate();
        /** @var OAuthClient $oauthClient */
        $oauthClient = (new OAuthClientModel())
            ->where('client_id', 'swagger')
            ->find();
        $oauthClient->client_id = 'swagger';
        $oauthClient->redirect_uri = base_url('oauth2-redirect.html');
        $oauthClient->grant_types = 'authorization_code';
        $oauthClient->scope = 'openid offline_access';
        $oauthClient->insert();

        /** @var OAuthClient $oauthClient */
        $oauthClient = (new OAuthClientModel())
            ->where('client_id', 'webclient')
            ->find();
        $oauthClient->client_id = 'webclient';
        $oauthClient->redirect_uri = getFrontendUrl() . '/app/login';
        $oauthClient->grant_types = 'authorization_code refresh_token';
        $oauthClient->scope = 'openid offline_access';
        $oauthClient->insert();

        Database::connect()->table('oauth_public_keys')->truncate();
        $privateKeyObject = openssl_pkey_new([
            'digest_alg' => 'sha512',
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($privateKeyObject, $privateKey);
        $publicKeyObject = openssl_pkey_get_public(openssl_pkey_get_details($privateKeyObject)['key']);

        $oauthPublicKey = new OAuthPublicKey();
        $oauthPublicKey->encryption_algorithm = 'RS512';
        $oauthPublicKey->private_key = $privateKey;
        $oauthPublicKey->public_key = openssl_pkey_get_details($publicKeyObject)['key'];
        $oauthPublicKey->save();

        /** @var OAuthScope $oauthScope */
        $oauthScope = (new OAuthScopeModel())
            ->where('scope', 'openid')
            ->find();
        if (!$oauthScope->exists()) {
            $oauthScope->scope = 'openid';
            $oauthScope->insert();
        }
        /** @var OAuthScope $oauthScope */
        $oauthScope = (new OAuthScopeModel())
            ->where('scope', 'offline_access')
            ->find();
        if (!$oauthScope->exists()) {
            $oauthScope->scope = 'offline_access';
            $oauthScope->insert();
        }

        ApiRoute::public('oauth-agent/token', OAuthAgent::class, 'token', 'post');
        ApiRoute::public('oauth-agent/refresh', OAuthAgent::class, 'refresh', 'post');


        // Remove after release
        /** @var DeploymentSpecification $specs */
        $specs = (new DeploymentSpecificationModel())
            ->includeRelated(ContainerImageModel::class)
            ->find();
        foreach ($specs as $spec) {
            if ($spec->container_image->exists() && strlen($spec->git_repo)) {
                $spec->container_image->version_control_repository_name = $spec->git_repo;
                $spec->container_image->save();
            }
        }

        Table::init('deployment_specifications')
            ->dropColumn('git_repo');
    }

    public function down() {

    }

}
