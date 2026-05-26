<?php

// Este arquivo inicializa a conexão com o banco de dados usando o framework Hyperf

// Importação das classes necessárias do Hyperf
use Hyperf\DbConnection\Db;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\DbConnection\Pool\PoolFactory as DbPoolFactory;
use Hyperf\DbConnection\ConnectionResolver;
use Hyperf\Database\ConnectionResolverInterface;
use Functions\db\hyperfDB\SimpleContainer;
use Hyperf\Database\Connectors\ConnectionFactory as DbConnectionFactory;
use Hyperf\Contract\StdoutLoggerInterface;
use Functions\db\hyperfDB\TinyStdoutLogger;
use Hyperf\Paginator\Paginator as BasePaginator;
use Psr\Http\Message\ServerRequestInterface; // se você tiver um request real (opcional)
use Hyperf\Contract\LengthAwarePaginatorInterface;
use Hyperf\Contract\PaginatorInterface;
use Hyperf\Paginator\LengthAwarePaginator;
use Hyperf\Paginator\Paginator;
/**
 * Função principal que inicializa a configuração do banco de dados
 * Esta função configura o container de dependências e as configurações de conexão
 */
function initHyperfDb(): void
{
    // Container simples compatível com Hyperf\Contract\ContainerInterface
    $container = new SimpleContainer();

    // Configuração do banco de dados implementando ConfigInterface
    $container->set(ConfigInterface::class, new class implements ConfigInterface {
        public function get(string $key, mixed $default = null): mixed
        {
            if ($key === 'databases.default') {
                // Configuração do MySQL a partir de constantes/env
                $driver = defined('TYPE_DB') ? TYPE_DB : (getenv('DB_DRIVER') ?: 'mysql');
                $host = defined('MYSQL_HOST') ? MYSQL_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
                $port = (int)(getenv('DB_PORT') ?: 3306);
                $database = defined('MYSQL_DB') ? MYSQL_DB : (getenv('DB_NAME') ?: 'app');
                $username = defined('MYSQL_USER') ? MYSQL_USER : (getenv('DB_USER') ?: 'root');
                $password = defined('MYSQL_PASS') ? MYSQL_PASS : (getenv('DB_PASS') ?: '');

                return [
                    'driver' => $driver,
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'username' => $username,
                    'password' => $password,
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    // Opções PDO para evitar unbuffered queries e manter compatibilidade
                    'options' => [
                        \PDO::ATTR_EMULATE_PREPARES => false, // Desativa emulação de prepared statements
                        \PDO::ATTR_STRINGIFY_FETCHES => false, // Mantém tipos nativos ao buscar
                        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, // Habilita queries bufferizadas
                        \PDO::ATTR_PERSISTENT => false, // Desativa conexões persistentes
                    ],
                    // Configurações do pool de conexões
                    'pool' => [
                        'min_connections' => (int)(getenv('DB_POOL_MIN') ?: 1), // Mínimo de conexões no pool
                        'max_connections' => (int)(getenv('DB_POOL_MAX') ?: 80), // Máximo de conexões no pool
                        'connect_timeout' => (float)(getenv('DB_CONNECT_TIMEOUT') ?: 5.0), // Timeout de conexão
                        'wait_timeout' => (float)(getenv('DB_WAIT_TIMEOUT') ?: 3.0), // Timeout de espera por conexão
                        'heartbeat' => (float)(getenv('DB_HEARTBEAT') ?: -1), // Heartbeat para manter conexões vivas
                        'max_idle_time' => (float)(getenv('DB_MAX_IDLE') ?: 60.0) // Tempo máximo ocioso
                    ]
                ];

                /* Exemplo de configuração para PostgreSQL
                return [
                    'driver' => 'pgsql',
                    'host' => '127.0.0.1',
                    'port' => 5432,
                    'database' => 'testdb',
                    'username' => 'postgres',
                    'password' => 'postgres',
                    'charset' => 'utf8',
                    'prefix' => '',
                    'schema' => 'public',
                    'sslmode' => 'prefer',
                    'pool' => [
                        'min_connections' => 1,
                        'max_connections' => 10,
                        'connect_timeout' => 5.0,
                        'wait_timeout' => 3.0,
                        'heartbeat' => -1,
                        'max_idle_time' => 60.0
                    ]
                ];
                */

                /* Exemplo de configuração para SQLite
                return [
                    'driver' => 'sqlite',
                    'database' => __DIR__ . '/database.sqlite',
                    'prefix' => '',
                    'pool' => [
                        'min_connections' => 1,
                        'max_connections' => 10,
                        'connect_timeout' => 5.0,
                        'wait_timeout' => 3.0,
                        'heartbeat' => -1,
                        'max_idle_time' => 60.0
                    ]
                ];
                */

                /* Exemplo de configuração para SQL Server
                return [
                    'driver' => 'sqlsrv',
                    'host' => '127.0.0.1',
                    'port' => 1433,
                    'database' => 'testdb',
                    'username' => 'sa',
                    'password' => 'yourStrong(!)Password',
                    'charset' => 'utf8',
                    'prefix' => '',
                    'pool' => [
                        'min_connections' => 1,
                        'max_connections' => 10,
                        'connect_timeout' => 5.0,
                        'wait_timeout' => 3.0,
                        'heartbeat' => -1,
                        'max_idle_time' => 60.0
                    ]
                ];
                */

                /* oracledb example
                return [
                    'driver' => 'oracle',
                    'host' => '127.0.0.1',
                    'port' => 1521,
                    'database' => 'testdb',
                    'username' => 'oracle',
                    'password' => 'oracle',
                    'charset' => 'utf8',
                    'prefix' => '',
                    'pool' => [
                        'min_connections' => 1,
                        'max_connections' => 10,
                        'connect_timeout' => 5.0,
                        'wait_timeout' => 3.0,
                        'heartbeat' => -1,
                        'max_idle_time' => 60.0
                    ]
                ];
                */
            }

            return $default;
        }

        public function has(string $key): bool
        {
            return $key === 'databases.default';
        }

        public function set(string $key, mixed $value): void
        {
            // Como este é um Config simples apenas para databases.default,
            // aceitaremos set apenas para esta chave e ignoraremos outras.
            // Em cenários reais, poderíamos armazenar em um array interno.
            // Aqui, não há estado necessário para persistir, então é no-op.
            // Implementado para cumprir a interface e evitar Fatal error.
        }
    });

    // Configuração da fábrica de pools de conexão (classe exata esperada pelo ConnectionResolver)
    $container->set(\Hyperf\DbConnection\Pool\PoolFactory::class, new DbPoolFactory($container));

    // Bindings básicos necessários pelo Hyperf DbConnection
    $container->set(DbConnectionFactory::class, new DbConnectionFactory($container));
    $container->set(StdoutLoggerInterface::class, new TinyStdoutLogger());

    // Configuração do resolver de conexões usando o container principal
    $resolver = new ConnectionResolver($container);

    // Registro do resolver no container
    $container->set(ConnectionResolverInterface::class, $resolver);

    // Disponibiliza Db no container e registra o container global da aplicação
    $container->set(Db::class, new Db($container));
    ApplicationContext::setContainer($container);

    // 3) Paginator bindings (ESSENCIAIS p/ paginate())
    // Mapeia interfaces para classes concretas e permite instanciar com parâmetros via make()
    $container->define(LengthAwarePaginatorInterface::class, LengthAwarePaginator::class);
    $container->define(PaginatorInterface::class, Paginator::class);

    // 4) Resolver da página atual (fora de HTTP/CLI, opcional)
    // Se você estiver em jobs/CLI e NÃO tiver request, defina assim:
    BasePaginator::currentPageResolver(static fn () => (int)($_GET['page'] ?? 1));

    // Se você tiver um ServerRequest no container, pode usar:
    // if ($container->has(ServerRequestInterface::class)) {
    //     $req = $container->get(ServerRequestInterface::class);
    //     BasePaginator::currentPageResolver(static fn () => (int)($req->getQueryParams()['page'] ?? 1));
    // }

    ApplicationContext::setContainer($container);
}
