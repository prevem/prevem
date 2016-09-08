<?php

$action = $argv[1];
switch($action) {
  case 'about':
    echo json_encode(array(
      'title' => 'ThunderBird 9.0 (Ubuntu Xenial)',
      'os' => 'linux',
      'osVersion' => 'Ubuntu Xenial',
      'app' => 'thunderbird',
      'appVersion' => '9.0',
      'icons' => array(),
      'options' => array(),
    ));
    break;
  case 'render':
    $job = json_decode(file_get_contents('php://stdin'),1);
    echo json_encode(array(
      'id' => $job['PreviewTask']['id'],
      'image' => base64_encode(file_get_contents(__DIR__ . '/thunderbird.png')),
    ));
    break;

  default:
    fwrite(STDERR, "Unrecognized action: " . $action);
    exit(1);
}
