<?php

namespace App\middleware;

use Functions\slim\getParsedBody as getParsedBody;
use Slim\Psr7\Response as slimReponse;
use Psr\Http\Message\ResponseInterface as ResponseSlim;
use Psr\Http\Message\ServerRequestInterface as RequestSlim;
use Psr\Http\Server\RequestHandlerInterface as RequestHandlerSlim;
use Functions\jwt\jwt;
use Functions\tasks\onTask;

class notifyOnModifie
{

    static public function req() {
                  //request post used curl
                  $curl = curl_init();
                  curl_setopt_array($curl, array(
                  CURLOPT_URL => 'web:3002/build/astro/website/',
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_ENCODING => '',
                  CURLOPT_MAXREDIRS => 10,
                  CURLOPT_TIMEOUT => 0,
                  CURLOPT_FOLLOWLOCATION => true,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_CUSTOMREQUEST => 'POST',
                  ));
                  $responseBuild = curl_exec($curl);
                  curl_close($curl);
                 return \json_encode($responseBuild);

    }

    static public function notify(RequestSlim $request, RequestHandlerSlim $handler, $swooleServer): ResponseSlim
    {
        $response = $handler->handle($request);
        $status = $response->getStatusCode();
        if($status != 200 && $status != 201){
            return $response;
        }
        if ($request->getMethod() == 'PUT' ||
            $request->getMethod() == 'POST' ||
            $request->getMethod() == 'DELETE' ||
            $request->getMethod() == 'PATCH') {

            \var_dump("status http: " . $status);
            $swooleServer->task(
                [
                    'namespace' => 'App\middleware',
                    'class' => 'notifyOnModifie',
                    'method' => 'req',
                ]
            );
        }

        return $response;
    }
}

    //request post used curl
    $curl = curl_init();
    curl_setopt_array($curl, array(
    CURLOPT_URL => 'web:3002/build/astro/website/',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    ));
    $responseBuild = curl_exec($curl);
    curl_close($curl);
   echo json_encode($responseBuild);
   echo "\n";
