<?php namespace App\Libraries;

use OrmExtension\ModelParser\ModelParser;
use RestExtension\ApiParser\ApiParser;

/**
 * Class OpenApi
 * @package App\Libraries
 */
class OpenApi {

    /**
     * @param string|null $scope
     * @return array
     * @throws \ReflectionException
     */
    public static function run(string $scope = null): array {
        $json = [
            'contact' => [
                'name' => getenv('PROJECT_NAME'),
                'url' => getenv('PROJECT_CONTACT_URL'),
                'email' => getenv('PROJECT_CONTACT_EMAIL')
            ],
            'version' => '1.0.0',
            'openapi' => '3.0.0',
            'info' => [
                'title' => '4 Spaces | Kubernetes Service Orchestrator'
            ],
            'security' => [
                [
                    'OAuth2' => []
                ]
            ],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'OAuth2' => [
                        'type' => 'oauth2',
                        'description' => 'OAuth2 with PKCE',
                        'flows' => [
                            'authorizationCode' => [
                                'authorizationUrl' => base_url('/authorize'),
                                'tokenUrl' => base_url('/token'),
                                'refreshUrl' => base_url('/token'),
                                'scopes' => [],
                                'client_id' => 'swagger'
                            ]
                        ]
                    ],
                ],
                'schemas' => []
            ],
            'definitions' => []
        ];

        $apiParser = ApiParser::run($scope);
        $paths = [];
        foreach ($apiParser->generateSwagger() as $path => $data) {
            $paths['/api'.$path] = $data;
        }
        $json['paths'] = $paths;

        $parser = ModelParser::run(true);
        $json['components']['schemas'] = $parser->generateSwagger($scope == 'third_party', $apiParser->schemaReferences, $scope);

        return $json;
    }

}
