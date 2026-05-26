<?php

use Functions\api\curl;

///ip externo client
if (!defined('IP')) define('IP', json_decode(curl::get('https://api.ipify.org?format=json'))->ip);
//dominio
if (!defined('DOMAIN')) define('DOMAIN', 'https://www.sunshinefloridadrinks.com.br');
// if(!defined('DOMAIN')) define('DOMAIN', 'http://localhost:9602');
//url base
if (!defined('URL_BASE')) define('URL_BASE', DOMAIN . '/public/');

//url twig views
if (!defined('URL_VIEWS')) define('URL_VIEWS', './site/views/site');

//project info
if (!defined('VERSION')) define('VERSION', '1.0.0');
if (!defined('NAME')) define('NAME', 'Refacil API');
if (!defined('DESCRIPTION')) define('DESCRIPTION', 'Refacil API');
if (!defined('URL')) define('URL', 'http://localhost:9502');
if (!defined('NAMEPROJECT')) define('NAMEPROJECT', 'Refacil API');

// AWS Cognito Config (credenciais fixas)
if (!defined('COGNITO_CLIENT_ID')) define('COGNITO_CLIENT_ID', '7f7c7b17ki09f8f256u9pu1dfv');
if (!defined('COGNITO_REGION')) define('COGNITO_REGION', 'us-east-1');
if (!defined('COGNITO_USER_POOL_ID')) define('COGNITO_USER_POOL_ID', 'us-east-1_ENj1Vx8n0');
if (!defined('COGNITO_AUTH_USER')) define('COGNITO_AUTH_USER', 'emanuelbetcel@gmail.com');
if (!defined('COGNITO_AUTH_PASS')) define('COGNITO_AUTH_PASS', 'Refacil@Test2026!');
