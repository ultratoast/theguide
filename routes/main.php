<?php

require 'modules.php';
require 'projects.php';
require 'tags.php';
require 'feedback.php';

$app->get('/', function (Request $request, Response $response) {
    // $name = $request->getAttribute('name');
    $response->getBody()->write("Don't Panic");
    return $response;
});