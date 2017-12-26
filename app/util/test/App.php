<?php
/**
 * Created by PhpStorm.
 * User: Xc
 * Date: 2017/8/18
 * Time: 14:44
 */

use App\Server\Server;

require_once __DIR__.'/../bootstrap/app.php';
require_once 'BaseApp.php';

global $command;

switch ($command)
{
    case "e":
        new Server();
        break;
    default:
        echo "Nothing to do!\n";
}