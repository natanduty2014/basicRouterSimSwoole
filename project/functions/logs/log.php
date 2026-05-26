<?php

namespace Functions\logs;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class log
{
    static public function logs($methed, $namelog, $type_error, $info = array())
    {
        //ip client
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        //log
        $logger = new Logger('log');
        $logger->pushHandler(
            new StreamHandler(
                '/public/logs/' . $namelog . '.log',
                Logger::DEBUG
            )
        );
        $logger->$type_error(
            'server',
            [
                'debug' => 'Teste',
                'ip_client' => $ip,
                'router' => $_SERVER['REQUEST_URI'],
                'type_router' => $_SERVER['REQUEST_METHOD'],
                'methed_function' => $methed,
                'info' => $info ?? null,
                'php_version' => phpversion(),
                'zend_version ' => zend_version()
            ]
        );
    }
}
