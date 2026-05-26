<?php
require __DIR__ . '/vendor/autoload.php';

use Functions\api\ZenviaClient;
use Imefisto\PsrSwoole\ServerRequest as PsrRequest;
use Imefisto\PsrSwoole\ResponseMerger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Runtime;
use Swoole\Coroutine;
use function Swoole\Coroutine\run;

// Eloquent (standalone)
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
// Hyperf DB (pool/coroutine-friendly)
require_once __DIR__ . '/functions/db/hyperfDB/initHyperfDb.php';

// Limites de upload (além da config do Swoole abaixo)
ini_set('upload_max_filesize', '64M');
ini_set('post_max_size', '64M');
ini_set('memory_limit', '256M');

// Ativa todos os hooks de corrotina (PDO/cURL/etc)
Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

$http = new Swoole\Http\Server('0.0.0.0', 9502);
$uriFactory = new Psr17Factory;
$streamFactory = new Psr17Factory;
$responseFactory = new Psr17Factory;
$uploadedFileFactory = new Psr17Factory;
$responseMerger = new ResponseMerger;

$http->set([
    'open_http2_protocol' => true,
    'enable_static_handler' => true,
    'document_root' =>  './',               // caminho absoluto é mais seguro
    'static_handler_locations' => ['/public'],
    'reload_async' => true,
    'max_wait_time' => 30,
    'http_parse_files' => true,
    'http_parse_post' => true,
    'http_parse_cookie' => false,
    'pid_file' => '/server.pid',
    // 'reactor_num' => 2,
    // 'worker_num' => 4,
    'log_level' => 0,
    'log_file' => '/public/logs/server/swoole/serverSwoole.log',
    'log_rotation' => SWOOLE_LOG_ROTATION_DAILY,
    'log_date_format' => '%Y-%m-%d %H:%M:%S',
    'log_date_with_microseconds' => false,
    'package_max_length' => 1024 * 1024 * 64,
    'enable_coroutine' => true,
    'task_worker_num' => 4,
]);

/**
 * Inicialização por WORKER: Eloquent/DB
 */
$http->on('WorkerStart', function (\Swoole\Server $server, int $workerId): void {
    static $done = false;
    if ($done) return;
    // Usa um Container explícito para permitir gerenciar config/conn dinamicamente
    $container = new Container();
    $capsule = new Capsule($container);

    // \Illuminate\Database\Capsule\Manager::connection()->statement('SET SESSION sql_buffer_result = 1');

    $capsule->addConnection([
        'driver'    => defined('TYPE_DB') ? TYPE_DB : (getenv('DB_DRIVER') ?: 'mysql'),
        'host'      => defined('MYSQL_HOST') ? MYSQL_HOST : (getenv('DB_HOST') ?: '127.0.0.1'),
        'database'  => defined('MYSQL_DB')   ? MYSQL_DB   : (getenv('DB_NAME') ?: 'app'),
        'username'  => defined('MYSQL_USER') ? MYSQL_USER : (getenv('DB_USER') ?: 'root'),
        'password'  => defined('MYSQL_PASS') ? MYSQL_PASS : (getenv('DB_PASS') ?: ''),
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'    => '',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES  => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, // <- ESSENCIAL
            // Garante que toda nova conexão já ative o buffer de resultados no nível da sessão
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_buffer_result = 1',
        ],

    ], 'mysql');

    // Eventos do Eloquent (necessários para features como observers)
    $capsule->setEventDispatcher(new Dispatcher($container));

    // Torna global e inicializa Eloquent UMA vez por worker
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    // Define conexão padrão explicitamente como 'mysql'
    $capsule->getDatabaseManager()->setDefaultConnection('mysql');

    // Disponibiliza o Capsule e Container para uso no ciclo de requisição
    $GLOBALS['capsule'] = $capsule;
    // Inicializa Hyperf DB (pool) por worker
    try {
        initHyperfDb();
    } catch (Throwable $e) { /* log opcional */
    }
    $GLOBALS['db_container'] = $container;

    if ($workerId === 0) {
        \Swoole\Timer::tick(600000, function () {
            try {
                \App\model\entity\pedidos::cancelExpiredPending();
            } catch (\Throwable $e) {
                // Log opcional
            }
        });
    }

    $done = true;
});

$http->on('start', function (Swoole\Http\Server $http) {

    $scanDirectories = [
        __DIR__ . '/site/controllers/OpenApiConfig.php',
        __DIR__ . '/site/controllers/entity',
    ];
    @$swagger = \OpenApi\Generator::scan($scanDirectories);
    @file_put_contents('public/swagger/swagger.json', \json_encode($swagger));
    @file_put_contents('./swagger.json', \json_encode($swagger));
});

$http->on(
    'request',
    function (
        Request $swooleRequest,
        Response $swooleResponse
    ) use (
        $uriFactory,
        $streamFactory,
        $uploadedFileFactory,
        $responseFactory,
        $responseMerger,
        $http
    ) {
        go(function () use (
            $swooleRequest,
            $swooleResponse,
            $uriFactory,
            $streamFactory,
            $uploadedFileFactory,
            $responseFactory,
            $responseMerger,
            $http
        ) {
            try {

                // CORS / preflight
                $swooleResponse->header('Access-Control-Allow-Origin', '*');
                $swooleResponse->header('Access-Control-Allow-Headers', $swooleRequest->header['access-control-request-headers'] ?? 'Content-Type, Authorization, Accept');
                $swooleResponse->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                $swooleResponse->header('Access-Control-Allow-Credentials', 'true');
                $swooleResponse->header('Server', 'VibeCriativa');
                if (($swooleRequest->server['request_method'] ?? 'GET') === 'OPTIONS') {
                    $swooleResponse->status(204);
                    $swooleResponse->end();
                    return;
                }

                $swooleResponse->header('Content-Type', 'application/json');

                /**
                 * cria PSR-7 request a partir do Swoole request
                 */
                $psrRequest = new PsrRequest(
                    $swooleRequest,
                    $uriFactory,
                    $streamFactory,
                    $uploadedFileFactory
                );

                // popular superglobais (se o seu appSlim depende delas)
                $_SERVER['REQUEST_URI'] = $swooleRequest->server['request_uri'] ?? '/';
                $_SERVER['REQUEST_METHOD'] = $swooleRequest->server['request_method'] ?? 'GET';
                $_SERVER['REMOTE_ADDR'] = $swooleRequest->server['remote_addr'] ?? '';
                $_SERVER['Authorization'] = $swooleRequest->header['authorization'] ?? '';
                $_SERVER['refreshtoken'] = $swooleRequest->header['refreshtoken'] ?? '';
                $_SERVER['HTTP_USER_AGENT'] = $swooleRequest->header['user-agent'] ?? '';
                $_SERVER['HTTP_ACCEPT'] = $swooleRequest->header['accept'] ?? '';
                $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $swooleRequest->header['accept-language'] ?? '';
                $_SERVER['HTTP_ACCEPT_ENCODING'] = $swooleRequest->header['accept-encoding'] ?? '';
                $_SERVER['HTTP_CONNECTION'] = $swooleRequest->header['connection'] ?? '';
                $_SERVER['HTTP_HOST'] = $swooleRequest->header['host'] ?? '';
                $_SERVER['HTTP_REFERER'] = $swooleRequest->header['referer'] ?? '';
                $_SERVER['HTTP_ORIGIN'] = $swooleRequest->header['origin'] ?? '';
                $_SERVER['HTTP_SEC_FETCH_SITE'] = $swooleRequest->header['sec-fetch-site'] ?? '';
                $_SERVER['HTTP_SEC_FETCH_MODE'] = $swooleRequest->header['sec-fetch-mode'] ?? '';
                $_SERVER['HTTP_SEC_FETCH_DEST'] = $swooleRequest->header['sec-fetch-dest'] ?? '';
                $_SERVER['HTTP_SEC_CH_UA'] = $swooleRequest->header['sec-ch-ua'] ?? '';
                $_SERVER['HTTP_SEC_CH_UA_MOBILE'] = $swooleRequest->header['sec-ch-ua-mobile'] ?? '';
                $_SERVER['HTTP_SEC_FETCH_USER'] = $swooleRequest->header['sec-fetch-user'] ?? '';
                $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] = $swooleRequest->header['sec-ch-ua-platform'] ?? '';
                $_SERVER['HTTP_SEC_CH_UA_ARCH'] = $swooleRequest->header['sec-ch-ua-arch'] ?? '';
                $_SERVER['HTTP_SEC_CH_UA_MODEL'] = $swooleRequest->header['sec-ch-ua-model'] ?? '';
                $_SERVER['HTTP_SEC_CH_UA_PLATFORM_VERSION'] = $swooleRequest->header['sec-ch-ua-platform-version'] ?? '';
                $_SERVER['HTTP_SEC_CH_UA_FULL_VERSION'] = $swooleRequest->header['sec-ch-ua-full-version'] ?? '';
                $_SERVER['HTTP_CF_CONNECTING_IP'] = $swooleRequest->header['cf-connecting-ip'] ?? '';
                $_SERVER['HTTP_CF_IPCOUNTRY'] = $swooleRequest->header['cf-ipcountry'] ?? '';
                $_SERVER['HTTP_CF_RAY'] = $swooleRequest->header['cf-ray'] ?? '';
                $_SERVER['HTTP_CF_VISITOR'] = $swooleRequest->header['cf-visitor'] ?? '';
                $_SERVER['HTTP_CF_WARP_TAG'] = $swooleRequest->header['cf-warp-tag'] ?? '';
                $_SERVER['HTTP_CF_WARP_ZONE'] = $swooleRequest->header['cf-warp-zone'] ?? '';
                $_SERVER['HTTP_CLIENT_IP'] = $swooleRequest->header['client-ip'] ?? '';
                $_SERVER['HTTP_X_FORWARDED_FOR'] = $swooleRequest->header['x-forwarded-for'] ?? '';
                $_SERVER['PROXY_REMOTE_ADDR'] = $swooleRequest->header['proxy-remote-addr'] ?? '';
                $_SERVER['HTTP_X_REAL_IP'] = $swooleRequest->header['x-real-ip'] ?? '';

                $_GET = $swooleRequest->get ?? [];
                $_POST = $swooleRequest->post ?? $swooleRequest->rawContent();
                $_FILES = $swooleRequest->files ?? $swooleRequest->rawContent();
                $_COOKIE = $swooleRequest->cookie ?? [];

                // DI do Slim + Swoole server
                $container = new DI\Container();
                $container->set('swooleServer', $http);

                // carrega sua app Slim
                require 'functions/slim/appSlim.php';

                // processa a request via Slim
                $psrResponse = $app->handle($psrRequest);

                // responde via Swoole
                $responseMerger->toSwoole($psrResponse, $swooleResponse)->end();
            } catch (Throwable $e) {
                // Garante resposta ao cliente mesmo em exceções fora do Slim
                if (!$swooleResponse->isWritable()) {
                    // nada a fazer
                } else {
                    $swooleResponse->header('Content-Type', 'application/json');
                    $swooleResponse->status(500);
                    $payload = [
                        'error' => 'Internal server error',
                        'message' => 'Falha ao processar a requisição.'
                    ];
                    $swooleResponse->end(json_encode($payload));
                }
                // Log detalhado para depuração
                try {
                    $cid = \Swoole\Coroutine::getCid();
                    $appLog =  './public/logs/server/swoole/app_errors.log';
                    $msg = sprintf(
                        '[CID %s] %s in %s:%d\nTrace: %s',
                        (string)$cid,
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine(),
                        $e->getTraceAsString()
                    );

                    @file_put_contents($appLog, date('c') . ' ' . $msg . "\n", FILE_APPEND);
                } catch (\Throwable $ignore) {
                }
            } finally {
                // Importante em ambiente Swoole: evitar reuso de conexões com cursores
                // abertos entre corrotinas/requests. Desconecta ao fim da requisição.
                try {
                    $cid = \Swoole\Coroutine::getCid();
                    if (isset($GLOBALS['capsule']) && $cid > 0) {
                        $manager = $GLOBALS['capsule']->getDatabaseManager();
                        $connName = 'mysql_' . $cid;
                        // Desconecta e elimina a conexão do CID atual
                        $manager->disconnect($connName);
                        if (method_exists($manager, 'purge')) {
                            $manager->purge($connName);
                        }
                        // Remove configuração criada para evitar crescimento da lista
                        if (isset($GLOBALS['db_container']['config']['database.connections'][$connName])) {
                            $connections = $GLOBALS['db_container']['config']['database.connections'];
                            unset($connections[$connName]);
                            $GLOBALS['db_container']['config']['database.connections'] = $connections;
                        }
                        // Restaura conexão padrão
                        $manager->setDefaultConnection('mysql');
                    } else {
                        Capsule::connection()->disconnect();
                    }
                } catch (Throwable $e) {
                    // silencioso: se não houver conexão ativa, segue o fluxo
                }
            }
        });
    }
);

$http->on('task', function ($serv, $task_id, $from_id, $data) {
    if (class_exists($data['namespace'] . '\\' . $data['class'])) {
        $class = $data['namespace'] . '\\' . $data['class'];
        if (method_exists($class, $data['method'])) {
            $method = $data['method'];
            $serv->finish($class::$method());
        } else {
            $serv->finish("O método '{$data['method']}' não existe na classe '{$class}'.");
        }
    } else {
        $serv->finish("A classe '{$data['class']}' não existe no namespace '{$data['namespace']}'.");
    }
});

$http->on('finish', function ($serv, $task_id, $data) {
    echo "AsyncTask[$task_id] finished: {$data}\n";
});

$http->start();
