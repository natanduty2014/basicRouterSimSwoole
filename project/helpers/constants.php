<?php

use Functions\api\curl;
//configurações da hora/data
//header('Content-type: text/html; charset=utf-8');
setlocale(LC_ALL, 'pt_BR.utf-8', 'pt_BR', 'Portuguese_Brazil');
date_default_timezone_set('America/Sao_Paulo');

//display errors with json for api mode
define('DISPLAY_ERROR', true);
define('DISPLAY_ERROR_JSON', true);


//configuração do banco de dados
define('TYPE_DB', 'mysql'); //mysql, pgsql, sqlsrv, sqlite
define('MYSQL_HOST', 'db');
define('MYSQL_DB', 'refacilc_db');
define('MYSQL_PASS', 'db_user_pass');
define('MYSQL_USER', 'db_user');
define('APP_DEBUG', true);
define('DEBUG', true);

//configuração do redis
define('REDIS_HOST', 'redis');
define('REDIS_PASS', 'my_secret_password');
define('REDIS_PORT', '6379');

//configuração do email
define('EMAIL_HOST', 'smtp.gmail.com');
define('EMAIL_PORT', '465');
define('EMAIL_USER', 'natanduty2014@gmail.com');
define('EMAIL_PASS', 'klhxsgbvlngqdjht');
define('EMAIL_DEBUG', 0);
define('EMAIL_FROM', 'SMARTDOCS');


//dominio
define('DOMAIN', 'https://www.exemple.com.br');
//url base
define('URL_BASE', DOMAIN . '/public/');
///ip externo client
 #define('IP', json_decode(curl::get('https://api.ipify.org?format=json'))->ip);
