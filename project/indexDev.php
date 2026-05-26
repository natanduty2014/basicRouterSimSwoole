<?php
require __DIR__ . '/vendor/autoload.php';

// Carrega arquivo .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use DI\Container;
use Slim\Factory\AppFactory;

// Simulação de um servidor HTTP básico
class SimulatedHttpServer
{
    public function on($event, $callback)
    {
        // Simula o registro de callbacks para eventos
        echo "Evento '$event' registrado.\n";
    }

    public function start()
    {
        // Simula o início do servidor
        echo "Servidor HTTP simulado iniciado.\n";
    }
}

// Criação do container de dependências
$container = new Container();


// Se Swoole não está carregado, use o servidor HTTP simulado
$http = new SimulatedHttpServer();


// Configuração do container
$container->set('swooleServer', $http);

// Configuração do Slim com o container
AppFactory::setContainer($container);
$app = AppFactory::create();

require 'functions/slim/appSlim.php';

$app->run();
#wget -O text.txt http://localhost:8080/v1/api/events/