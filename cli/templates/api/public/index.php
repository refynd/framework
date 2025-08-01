<?php

use Refynd\Bootstrap\Engine;
use {{APP_NAMESPACE}}\Bootstrap\AppProfile;

require_once '../vendor/autoload.php';

try {
    $engine = new Engine(new AppProfile());
    $response = $engine->runHttp();
    $response->send();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}
