<?php
/** @var string $scope */
/** @var \AuthExtension\Entities\OAuthScope $scopes */
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <title>Swagger</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
</head>
<body style="margin:0;">

<div id="swagger-ui"></div>

<script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-standalone-preset.js"></script>
<script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
<script>
    window.onload = function() {
        // Build a system
        const ui = SwaggerUIBundle({
            urls: [
                {
                    'url': "<?=base_url('/swagger/openapi'.(isset($scope)?'/'.$scope:''))?>",
                    'name': 'API'
                }
            ],
            oauth2RedirectUrl: '<?=base_url('oauth2-redirect.html')?>',
            dom_id: '#swagger-ui',
            deepLinking: true,
            docExpansion: 'none',
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],
            plugins: [
                SwaggerUIBundle.plugins.DownloadUrl
            ],
            layout: "StandaloneLayout",
            defaultModelExpandDepth: 1,
            defaultModelsExpandDepth: 0
        });
        ui.initOAuth({
            clientId: "swagger",
            clientSecret: "",
            usePkceWithAuthorizationCodeGrant: true,
        });

        window.ui = ui
    }
</script>
</body>
</html>
