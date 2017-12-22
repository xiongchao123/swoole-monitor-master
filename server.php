<?php

require_once __DIR__ . '/bootstrap/app.php';

$app = new Symfony\Component\Console\Application("<info>Swoole Tcp Protocol</info> Console Tool.");
$app->add(new \App\Console\Command\LoaderServer());
$app->run();