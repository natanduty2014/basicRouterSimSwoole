<?php
//slim load
use Psr\Http\Message\ResponseInterface as ResponseSlim;
use Psr\Http\Message\ServerRequestInterface as RequestSlim;
use Functions\slim\getParsedBody as getParsedBody;
use Slim\Routing\RouteCollectorProxy;
use Hyperf\DbConnection\Db as DB;
//midde
use App\middleware\{
    authorization,
    notifyOnModifie,
    permission,
    unitScope
};
//use App\middleware\home as homeMiddleware;
//controllers load
// use App\controllers\entity\{
//     clients,
//     clients_address,
//     products_categories,
//     requests,
//     products,
//     products_report,
//     requests_report,
//     user,
//     brands,
//     zip_code_ranges,
//     products_grids,
//     products_stocks
// };
use App\controllers\entity\dataExport;
use App\controllers\entity\products;

use App\controllers\entity\clients;
use App\controllers\entity\contratantes;
use App\controllers\entity\cardapio;
use App\controllers\entity\cardapioRelUnidades;
use App\controllers\entity\dashboard;
use App\controllers\entity\reports;
use App\controllers\entity\produtosRelCardapios;
use App\controllers\entity\produtosRelUnidades as produtosRelUnidadesController;
use App\controllers\entity\produtosPrecos as produtosPrecosController;
use App\controllers\entity\unidades as unidadesController;
use App\controllers\entity\estados as estadosController;
use App\controllers\entity\cidades as cidadesController;
use App\controllers\entity\bairros as bairrosController;
use App\controllers\entity\produtosGrades as produtosGradesController;
use App\controllers\entity\cupom as cupomController;
use App\controllers\entity\analyticsGoogle;
use App\controllers\entity\pedidos as pedidosController;
use App\controllers\entity\pagamentosMetodos as pagamentosMetodosController;
use App\controllers\entity\callback;
// use Functions\cryptography\passwordHash;
// use Functions\db\redis;
/*
 Função responsável por pegar os dados do container que contém a instância do swooleServer
*/

/** @var \Slim\App $app */
/** @var \DI\Container $container */
$http = $container->get('swooleServer');

$container->set('swooleServer', function ()  use ($http) {
    return $http;
});

// var_dump(passwordHash::passwordHash('12345678'));

// $SaveCode = redis::save('forgetPassword_', '01010101');
$app->get('/docs/gui', function (RequestSlim $request, ResponseSlim $response, array $args) {
    $file = 'public/swagger/dist/index.html';
    $current = file_get_contents($file);
    $response->getBody()->write($current);
    $response = $response->withStatus(200);
    return $response->withHeader('Content-Type', 'text/html');
});

$swooleServer = $container->get('swooleServer');

use App\controllers\entity\cognitoLogin; // Import adicionado
use App\controllers\entity\user; // Login user CMS


$app->post('/v1/api/dashboard', dashboard::class . ':index');

// Relatórios operacionais (Refacil Serving API)
$app->post('/v1/api/relatorios/pedidos-nao-finalizados', reports::class . ':pedidosNaoFinalizados');
$app->post('/v1/api/relatorios/clientes',               reports::class . ':clientes');
$app->post('/v1/api/relatorios/itens-por-periodo',      reports::class . ':itensPorPeriodo');
$app->post('/v1/api/relatorios/itens-por-cliente',      reports::class . ':itensPorCliente');
$app->post('/v1/api/relatorios/pedidos-dia-hora',       reports::class . ':pedidosDiaHora');
$app->post('/v1/api/relatorios/pedidos-bairro',         reports::class . ':pedidosBairro');
$app->post('/v1/api/relatorios/resumo-pedidos',         reports::class . ':resumoPedidos');
$app->post('/v1/api/dashboard/insights',               dashboard::class . ':insights');

// Google Analytics Data API
$app->post('/v1/api/analytics/google', analyticsGoogle::class . ':getData');

$app->get('/test-redis-token', \App\controllers\entity\testRedis::class . ':testTokenRefresh');

// Nova rota para Login Cognito separado
$app->post('/v1/api/cognito/login', cognitoLogin::class . ':login');

// Login de Usuário CMS
$app->post('/v1/api/login/', user::class . ':login');
$app->post('/v1/api/login', user::class . ':login');

// Recuperação de Senha CMS
$app->post('/v1/api/forget-password', user::class . ':forgetPassword');
$app->post('/v1/api/forget-password-code', user::class . ':forgetPasswordCode');
$app->post('/v1/api/generation-password', user::class . ':generationPassword');



$app->group('/callback', function (RouteCollectorProxy $group) {
    $group->post('', callback::class . ':StatusPayment');
});



$app->group('/v1/api/user/', function (RouteCollectorProxy $group) use ($swooleServer) {
    $group->get('', user::class . ':listAll');
    $group->get('{pag}', user::class . ':listAll');
    $group->put('{id}', user::class . ':edit')
        ->add(function ($request, $handler) use ($swooleServer) {
            return notifyOnModifie::notify($request, $handler, $swooleServer);
        });
    $group->delete('{id}', user::class . ':delete')->add(
        function ($request, $handler) use ($swooleServer) {
            return notifyOnModifie::notify($request, $handler, $swooleServer);
        }
    );
    $group->get('search/{id}', user::class . ':searchId');
    $group->post('', user::class . ':create')->add(
        function ($request, $handler) use ($swooleServer) {
            return notifyOnModifie::notify($request, $handler, $swooleServer);
        }
    );
})->add(new permission('user'))
  ->add(authorization::class . ':authorization');

$app->group('/v1/api/clients/', function (RouteCollectorProxy $group) use ($swooleServer) {
    $group->post('', clients::class . ':create');
    $group->put('{id}', clients::class . ':edit');
    $group->get('{page}', clients::class . ':listAll');
    $group->get('search/{id}', clients::class . ':getById');
    $group->delete('{id}', clients::class . ':delete');
    $group->post('login', clients::class . ':login');
    $group->post('confirm-sms', clients::class . ':confirmSMS');
    $group->post('recover-password', clients::class . ':recoverPassword');
    $group->post('confirm-recover-password', clients::class . ':confirmRecoverPassword');
    $group->post('resend-sms', clients::class . ':resendSMS');
});

use App\controllers\entity\clientesEnderecos;

$app->group('/v1/api/clients-address/', function (RouteCollectorProxy $group) {
    $group->get('', clientesEnderecos::class . ':listAll');
    $group->post('', clientesEnderecos::class . ':create');
    $group->put('{id}', clientesEnderecos::class . ':edit');
    $group->delete('{id}', clientesEnderecos::class . ':delete');
}); // Nota: sem middleware authorization global aqui, pois o token de cliente pode ter uma validação diferente e é verificado manulamente no getCliId.


$app->group('/v1/api/contratantes/', function (RouteCollectorProxy $group) use ($swooleServer) {
    $group->post('', contratantes::class . ':create');
    $group->put('{id}', contratantes::class . ':edit');
    $group->get('{page}', contratantes::class . ':listAll');
    $group->get('search/{id}', contratantes::class . ':getById');
    $group->get('search-url/{url}[/{cep_client}]', contratantes::class . ':searchUrl');
    $group->post('search-by-query/{url}', contratantes::class . ':searchByQuery');
    $group->patch('{id}/status', contratantes::class . ':activeDisable');
    $group->delete('{id}', contratantes::class . ':delete');
});

$app->group('/v1/api/cardapio/', function (RouteCollectorProxy $group) use ($swooleServer) {
    $group->post('', cardapio::class . ':create');
    $group->put('{id}', cardapio::class . ':edit');
    $group->get('{page}', cardapio::class . ':listAll');
    $group->get('search/{id}', cardapio::class . ':getById');
    $group->get('contratante/{con_id}', cardapio::class . ':listByContratante');
    $group->patch('{id}/status', cardapio::class . ':activeDisable');
    $group->delete('{id}', cardapio::class . ':delete');
});

$app->group('/v1/api/cardapio-unidades/', function (RouteCollectorProxy $group) use ($swooleServer) {
    $group->post('', cardapioRelUnidades::class . ':create');
    $group->get('{slug}/{page}', cardapioRelUnidades::class . ':listAll');
    $group->get('search/{id}', cardapioRelUnidades::class . ':getById');
    $group->get('cardapio/{car_id}', cardapioRelUnidades::class . ':listByCardapio');
    $group->get('unidade/{slug}', cardapioRelUnidades::class . ':listByUnidade');
    $group->patch('{id}/status', cardapioRelUnidades::class . ':activeDisable');
    $group->delete('{id}', cardapioRelUnidades::class . ':delete');
});

$app->group('/v1/api/pedidos/', function (RouteCollectorProxy $group) {
    $group->get('counts', pedidosController::class . ':counts');
    $group->get('{page}', pedidosController::class . ':listAll');
    $group->get('search/{id}', pedidosController::class . ':getById');
    $group->patch('{id}/status', pedidosController::class . ':updateStatus');
})->add(new permission('pedido'))
  ->add(authorization::class . ':authorization');

$app->group('/v1/api/cupons/', function (RouteCollectorProxy $group) {
    $group->post('', cupomController::class . ':create');
    $group->put('{id}', cupomController::class . ':edit');
    $group->get('{page}', cupomController::class . ':listAll');
    $group->get('search/{id}', cupomController::class . ':getById');
    $group->patch('{id}/status', cupomController::class . ':activeDisable');
    $group->delete('{id}', cupomController::class . ':delete');
})->add(authorization::class . ':authorization');

// ─── Pedidos / Carrinho ───────────────────────────────────────────────────────
// Criar pedido (Finalizar Carrinho) — não requer autenticação para suportar
// clientes não cadastrados (guest checkout).
$app->post('/v1/api/pedidos/', pedidosController::class . ':create');

// Validar cupom de desconto → requer CLI ID no body (autenticação implícita)
$app->post('/v1/api/pedidos/validar-cupom', pedidosController::class . ':validateCoupon');
// << conflito ajustar

// Buscar pedido completo por ID
$app->get('/v1/api/pedido/cliente/{id}', pedidosController::class . ':getById');
// Consultar status de pagamento (OrendaPay)
$app->get('/v1/api/pedidos/{id}/status-pagamento', pedidosController::class . ':statusPagamento');
// Trocar metodo de pagamento
$app->patch('/v1/api/pedidos/{id}/pagamento', pedidosController::class . ':updatePayment');

// Atualizar situação do pedido (uso interno / painel)
// $app->patch('/v1/api/pedidos/{id}/status', pedidosController::class . ':updateStatus')
//     ->add(authorization::class . ':authorization');

// >> conflito ajustar

// Listar pedidos de um cliente (história do cliente)
$app->get('/v1/api/pedidos/cliente/{cli_id}', pedidosController::class . ':listByClient');
$app->get('/v1/api/pedidos/cliente/{cli_id}/{page}', pedidosController::class . ':listByClient');

// ─── Rotas públicas para o frontend de delivery (sem auth) ────────────────────
// Usadas pelo SSR do Astro nas páginas de produto e preços
$app->get('/v1/api/public/produto/{id}', products::class . ':getById');
$app->get('/v1/api/public/produto/{pro_id}/preco/{cardapio_id}', produtosPrecosController::class . ':listByCardapioProduto');
$app->get('/v1/api/public/pagamentos-metodos/{uni_id}/{frete_tipo}', pagamentosMetodosController::class . ':listByUnidade');

// Grades completas de um produto (com opções e preços por unidade)
// Replica a lógica legada de getProdutoGrades + getProdutoGradeOpcoes

$app->get('/v1/api/public/debug_precos', produtosGradesController::class . ':getTiposPublic');

$app->get('/v1/api/public/unidade/{slug}', unidadesController::class . ':getBySlugPublic');

$app->get('/v1/api/public/unidade/{slug}/frete/{cep_client}', unidadesController::class . ':getFretePublic');

$app->get('/v1/api/public/produto/{pro_id}/grades/{uni_id}', produtosGradesController::class . ':getGradesPublic');
// Disponibilidade do produto na unidade (esgotado / disponível) — usado pelo atendente
$app->group('/v1/api/produtos-disponibilidade/{uni_id}/', function (RouteCollectorProxy $group) {
    $group->get('', produtosRelUnidadesController::class . ':listByUnidade');
    $group->patch('produto/{pro_id}', produtosRelUnidadesController::class . ':setStatus');
})->add(new unitScope('uni_id'))
  ->add(new permission('produto', 'edit'))
  ->add(authorization::class . ':authorization');

$app->group('/v1/api/produtos-rel-cardapios/', function (RouteCollectorProxy $group) {
    $group->post('', produtosRelCardapios::class . ':create');
    $group->delete('{id}', produtosRelCardapios::class . ':delete');
    $group->get('cardapio/{car_id}', produtosRelCardapios::class . ':listByCardapio');
})->add(new permission('produto'))
  ->add(authorization::class . ':authorization');

$app->group('/v1/api/produtos/precos/', function (RouteCollectorProxy $group) {
    $group->post('', produtosPrecosController::class . ':create');
    $group->put('{id}', produtosPrecosController::class . ':update');
    $group->delete('{id}', produtosPrecosController::class . ':delete');
    $group->get('cardapio/{car_id}', produtosPrecosController::class . ':listByCardapio');
    $group->get('cardapio/{car_id}/produto/{pro_id}', produtosPrecosController::class . ':listByCardapioProduto');
})->add(new permission('produto'))
  ->add(authorization::class . ':authorization');

$app->get('/v1/api/unidades/contratante', unidadesController::class . ':listByContratante')
    ->add(authorization::class . ':authorization');

$app->post('/v1/api/unidades', unidadesController::class . ':create')
    ->add(authorization::class . ':authorization');

$app->group('/v1/api/unidades/', function (RouteCollectorProxy $group) {
    $group->get('{uni_id}', unidadesController::class . ':getById');
    $group->put('{uni_id}/configuracoes', unidadesController::class . ':editConfiguracoes');
    $group->get('{uni_id}/recebimento', unidadesController::class . ':getRecebimento');
    $group->put('{uni_id}/recebimento', unidadesController::class . ':editRecebimento');
    $group->get('{uni_id}/horarios', unidadesController::class . ':getHorarios');
    $group->put('{uni_id}/horarios', unidadesController::class . ':editHorarios');
    $group->get('{uni_id}/fretes', unidadesController::class . ':getFretes');
    $group->put('{uni_id}/fretes', unidadesController::class . ':editFretes');
    $group->delete('{uni_id}', unidadesController::class . ':delete');
})->add(new unitScope('uni_id'))
  ->add(new permission('unidade'))
  ->add(authorization::class . ':authorization');

$app->get('/v1/api/estados', estadosController::class . ':listOptions')
    ->add(authorization::class . ':authorization');
$app->get('/v1/api/cidades', cidadesController::class . ':listOptions')
    ->add(authorization::class . ':authorization');
$app->get('/v1/api/bairros', bairrosController::class . ':listOptions')
    ->add(authorization::class . ':authorization');

$app->group('/v1/api/produtos/', function (RouteCollectorProxy $group) {
    $group->post('', products::class . ':create');
    $group->put('{id}', products::class . ':edit');
    $group->get('cms/{page}', products::class . ':listAll');
    $group->get('show/{id}', products::class . ':getById');
    $group->get('search/{query}', products::class . ':search');
    $group->get('categorias/{con_id}', products::class . ':listCategorias');
    $group->patch('{id}/status', products::class . ':activeDisable');
    $group->delete('{id}', products::class . ':delete');
    // Imagens
    $group->post('{id}/imagens', products::class . ':uploadImage');
    $group->delete('imagens/{pri_id}', products::class . ':deleteImage');
    $group->patch('imagens/{pri_id}/capa', products::class . ':setCoverImage');
    // Grades
    $group->get('grades/contratante', produtosGradesController::class . ':listByContratante');
    $group->get('grades/produto/{pro_id}', produtosGradesController::class . ':listByProduto');
})->add(new permission('produto'))
  ->add(authorization::class . ':authorization');

$app->get('/health/db', function (RequestSlim $request, ResponseSlim $response, array $args) {
    try {
        // Garante que o container está inicializado
        try {
            \Hyperf\Context\ApplicationContext::getContainer();
        } catch (\Throwable $e) {
            // Container não existe, inicializa
            if (function_exists('initHyperfDb')) {
                initHyperfDb();
            }
        }

        // Verifica se o container foi inicializado corretamente
        $container = \Hyperf\Context\ApplicationContext::getContainer();
        if ($container === null) {
            throw new \Exception('Container do Hyperf não pôde ser inicializado');
        }

        DB::select('SELECT 1');

        $response->getBody()->write(json_encode(['ok' => true, 'message' => 'Conexão com banco de dados OK']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (\PDOException $e) {
        $errorMsg = $e->getMessage();
        $statusCode = 500;
        $errorType = 'Erro de conexão';

        if (strpos($errorMsg, 'Access denied') !== false) {
            $errorType = 'Acesso negado';
            $statusCode = 401;
            $message = 'Usuário ou senha do banco de dados incorretos';
        } elseif (strpos($errorMsg, 'Unknown database') !== false) {
            $errorType = 'Banco não encontrado';
            $statusCode = 404;
            $message = 'Banco de dados não existe';
        } elseif (strpos($errorMsg, 'DNS Lookup resolve failed') !== false || strpos($errorMsg, 'php_network_getaddresses') !== false) {
            $errorType = 'Host não encontrado';
            $statusCode = 503;
            $message = 'Não foi possível resolver o host do banco de dados (DNS). Verifique se o nome do host está correto';
        } elseif (strpos($errorMsg, 'Connection refused') !== false || strpos($errorMsg, "Can't connect") !== false) {
            $errorType = 'Servidor não acessível';
            $statusCode = 503;
            $message = 'Não foi possível conectar ao servidor do banco de dados. Verifique se o servidor está rodando';
        } else {
            $message = 'Erro ao conectar ao banco de dados';
        }

        $response->getBody()->write(json_encode([
            'ok' => false,
            'error' => $errorType,
            'message' => $message,
            'details' => $errorMsg
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    } catch (\Throwable $e) {
        $response->getBody()->write(json_encode([
            'ok' => false,
            'error' => 'Erro inesperado',
            'message' => 'Erro ao verificar conexão com banco de dados',
            'details' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

// Rotas de extração de dados para Engenharia de Dados
$app->group('/api/v1/dados', function (RouteCollectorProxy $group) {
    $group->get('/{table}/info', dataExport::class . ':getInfo');
    $group->get('/{table}/{page}/{per_page}', dataExport::class . ':get');
    $group->get('/{table}/{page}', dataExport::class . ':get');
    $group->get('/{table}', dataExport::class . ':get');
})->add(authorization::class . ':authorization');
