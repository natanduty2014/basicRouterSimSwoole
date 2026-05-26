<?php
// Carrega constantes do projeto
require __DIR__ . '/../../site/helpers/constants.php';

//slim load
use Functions\logs\log;
use Slim\Factory\AppFactory;
use Twig\Loader\FilesystemLoader;
use Slim\Psr7\Response as slimReponse;
use Psr\Http\Message\ResponseInterface as ResponseSlim;
use Psr\Http\Message\ServerRequestInterface as RequestSlim;
use Psr\Http\Server\RequestHandlerInterface as RequestHandlerSlim;


$file = 'handler';

/**
 * Create your slim app
 */
/**
 * Create your slim app
 */
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$customErrorHandle = function (
    RequestSlim $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    $logger = null
) use ($app, $file) {
    $payload = ['error' => $exception->getMessage() . ' Route: ' . $request->getUri()->getPath()];

    // Tratamento específico para PDOException
    if ($exception instanceof \PDOException) {
        log::logs('PDOException: ' . $exception->getMessage(), 'server_PDO_error', 'none', 'error', $exception->getMessage());
        $response = $app->getResponseFactory()->createResponse();
        $response = $response->withStatus(500);
        $payload = ['error' => 'Database error occurred', 'details' => $displayErrorDetails ? $exception->getMessage() : 'Internal server error'];
        $response->getBody()->write(json_encode($payload));
        return $response;
    }

    //404
    $notfound = $exception->getCode() == 404;
    if ($notfound == true) {
        //return payload not twig
        $response = $app->getResponseFactory()->createResponse();
        $response = $response->withStatus(404);
        $response->getBody()->write(json_encode($payload));
        return $response;
    }
    //error router handler
    $routerhandler = $exception->getCode() == 405;
    if ($routerhandler == true) {
        $response = $app->getResponseFactory()->createResponse();
        //status 405
        $response = $response->withStatus(405);
        $response->getBody()->write(json_encode($payload));
        return $response;
    }
    //error 0
    $routerhandler = $exception->getCode() == 0;
    if ($routerhandler == true) {
        $response = $app->getResponseFactory()->createResponse();
        //status 404
        $response = $response->withStatus(404);
        $response->getBody()->write(json_encode($payload));
        return $response;
    }

    // Tratamento padrão para qualquer outra exceção
    $response = $app->getResponseFactory()->createResponse();
    $response = $response->withStatus(500);
    $payload = ['error' => 'Internal server error', 'details' => $displayErrorDetails ? $exception->getMessage() : 'An error occurred'];
    $response->getBody()->write(json_encode($payload));
    return $response;
};

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
if (DISPLAY_ERROR == true) {
    $errorMiddleware  = $errorMiddleware->setDefaultErrorHandler($customErrorHandle);
} else {
    $errorMiddleware  = $errorMiddleware->setDefaultErrorHandler(null);
}

$app->add(function (RequestSlim $request, RequestHandlerSlim $handler) {
    // //
    // if (isset($_COOKIE['lang'])) {
    //     $idioma = $_COOKIE['lang'];
    //     //var super global
    //     $GLOBALS['lang'] = $idioma;
    // } else {
    //     $idioma = 'PT';
    //     //var super global
    //     $GLOBALS['lang'] = $idioma;
    // }
    try {
        // Verifica se o cabeçalho Authorization está presente na solicitação
        $authorizationHeader = $request->getHeaderLine('authorization');
        // Verifica se o método da solicitação é OPTIONS
        if ($request->getMethod() === 'OPTIONS') {
            // Retorna uma resposta vazia com os cabeçalhos de controle de acesso apropriados
            $response = new slimReponse();
            //authorization
            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->withHeader('Access-Control-Max-Age', '86400')
                ->withStatus(200);
        }

        // Passa a solicitação para o próximo middleware
        $response = $handler->handle($request);

        // Adiciona headers CORS em todas as respostas
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');

        // Adiciona o cabeçalho Authorization à resposta
        if (!empty($authorizationHeader)) {
            $response = $response->withHeader('authorization', $authorizationHeader);
        }
        return $response;
    } catch (Exception $th) {
        log::logs($th->getMessage(), 'server_Slim_Log_error', 'none', 'error', $th->getMessage());
        // Retornar uma resposta válida em vez da exceção
        $response = new slimReponse();
        $response = $response->withStatus(500);
        $payload = ['error' => 'Middleware error', 'message' => $th->getMessage()];
        $response->getBody()->write(json_encode($payload));
        return $response;
    }
});

//verificar se o diretorio existe e carregar as rotas
if (is_dir('/public/project/site')) {
    require '/public/project/site/helpers/routerSlim.php';
} else {
    throw new Exception('Diretório site não encontrado');
}
