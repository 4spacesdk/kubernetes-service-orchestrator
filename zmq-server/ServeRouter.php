<?php

if (!file_exists(dirname(__DIR__) . '/zmq-server/vendor/autoload.php')) {
    echo ":: ERROR :: You need to run `composer install`. ";
    echo "1) Open ´deployment/Dockerfile´ in the PushService project. \n";
    echo "2) Comment out last line of docker file (CMD [\"/bin/bash\", \"start.sh\"]) \n";
    echo "3) Rebuild and run docker again (docker-compose build + docker-compose up).\n";
    echo "4) SSH to the container and run then run ´composer install´. \n";
    echo "5) Docker down. Revert dockerfile. ";
	echo "6) Rebuild and run docker again (docker-compose build + docker-compose up).\n";

    return;
}

require dirname(__DIR__) . '/zmq-server/vendor/autoload.php';

$router = new \App\Thruway\Router();
$router->run();
