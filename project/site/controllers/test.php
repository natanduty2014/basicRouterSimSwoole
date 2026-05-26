<?php

namespace App\controllers;

use Psr\Http\Message\ResponseInterface as ResponseSlim;
use Psr\Http\Message\ServerRequestInterface as RequestSlim;
use Functions\slim\getParsedBody as getParsedBody;
use Swoole\Http\Server;

class test
{


    static public function testTask(){
        \sleep(5);
        return 'teste task';
   }

   static public function test(RequestSlim $request, ResponseSlim $response, array $args, Server $swooleServer): ResponseSlim
   {
      //teste task
     $swooleServer->task(
           [
               'namespace' => 'App\controllers\entity',
               'class' => 'user',
               'method' => 'testTask',
           ]
       );
     $response->getBody()->write('teste 1');
     $response = $response->withStatus(200);
     return $response->withHeader('Content-Type', 'text/html');
   }




    static public function PostTeste(RequestSlim $request, ResponseSlim $response, array $args): ResponseSlim
    {
        $data = $request->getParsedBody() ?? (new getParsedBody)->filter($_POST)->getData();
        $response->getBody()->write(($data));
        $response = $response->withStatus(200);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
