<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\pedidos as pedidosModel;
use App\helpers\unitScope;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Pedidos', description: 'Operações do carrinho e pedidos')]
class pedidos
{
    // ─── POST /v1/api/pedidos/ ────────────────────────────────────────────────

    #[OA\Post(
        path: '/v1/api/pedidos/',
        operationId: 'createPedido',
        summary: 'Criar novo pedido (finalizar carrinho)',
        tags: ['Pedidos']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'cli_id',          type: 'integer', description: 'ID do cliente (opcional se não logado)'),
                new OA\Property(property: 'uni_id',          type: 'integer', description: 'ID da unidade'),
                new OA\Property(property: 'con_id',          type: 'integer', description: 'ID do contratante'),
                new OA\Property(property: 'frete_tipo',      type: 'integer', description: '3=delivery, 4=retirar, 6=cardápio digital'),
                new OA\Property(property: 'frete_preco',     type: 'number'),
                new OA\Property(property: 'pagamento_metodo',type: 'integer'),
                new OA\Property(property: 'pagamento_troco', type: 'number'),
                new OA\Property(property: 'cupom_id',        type: 'integer'),
                new OA\Property(property: 'desconto',        type: 'number'),
                new OA\Property(property: 'obs',             type: 'string'),
                new OA\Property(property: 'endereco_cep',    type: 'string'),
                new OA\Property(property: 'endereco_logradouro', type: 'string'),
                new OA\Property(property: 'endereco_numero', type: 'string'),
                new OA\Property(property: 'endereco_complemento', type: 'string'),
                new OA\Property(property: 'endereco_bairro', type: 'string'),
                new OA\Property(property: 'endereco_cidade', type: 'string'),
                new OA\Property(property: 'endereco_estado', type: 'string'),
                new OA\Property(
                    property: 'itens',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'pro_id',          type: 'integer'),
                            new OA\Property(property: 'titulo',          type: 'string'),
                            new OA\Property(property: 'preco_unitario',  type: 'number'),
                            new OA\Property(property: 'qtd',             type: 'integer'),
                            new OA\Property(property: 'embalagem',       type: 'number'),
                            new OA\Property(property: 'obs',             type: 'string'),
                            new OA\Property(
                                property: 'grades',
                                type: 'array',
                                items: new OA\Items(
                                    properties: [
                                        new OA\Property(property: 'prg_id',      type: 'integer'),
                                        new OA\Property(property: 'titulo',      type: 'string'),
                                        new OA\Property(property: 'descricao',   type: 'string'),
                                        new OA\Property(property: 'tipo',        type: 'integer'),
                                        new OA\Property(property: 'min',         type: 'integer'),
                                        new OA\Property(property: 'gratis',      type: 'integer'),
                                        new OA\Property(property: 'max',         type: 'integer'),
                                        new OA\Property(property: 'obrigatoria', type: 'integer'),
                                        new OA\Property(
                                            property: 'opcoes',
                                            type: 'array',
                                            items: new OA\Items(
                                                properties: [
                                                    new OA\Property(property: 'pgo_id',    type: 'integer'),
                                                    new OA\Property(property: 'titulo',    type: 'string'),
                                                    new OA\Property(property: 'qtd',       type: 'integer'),
                                                    new OA\Property(property: 'preco',     type: 'number'),
                                                    new OA\Property(property: 'embalagem', type: 'number'),
                                                    new OA\Property(property: 'min',       type: 'integer'),
                                                    new OA\Property(property: 'max',       type: 'integer'),
                                                ]
                                            )
                                        ),
                                    ]
                                )
                            ),
                        ]
                    )
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Pedido criado com sucesso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status',  type: 'integer', example: 201),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'ped_id', type: 'integer'),
                        new OA\Property(property: 'total',  type: 'number'),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Dados inválidos')]
    public function create(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            if (empty($body)) {
                $raw  = (string)$request->getBody();
                $body = json_decode($raw, true) ?? [];
            }

            if (empty($body['uni_id'])) {
                $response->getBody()->write(json_encode([
                    'status'  => 400,
                    'message' => 'Informe a unidade (uni_id).',
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            if (empty($body['itens'])) {
                $response->getBody()->write(json_encode([
                    'status'  => 400,
                    'message' => 'O carrinho está vazio.',
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $result = pedidosModel::create($body);
            $response->getBody()->write(json_encode($result));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // ─── GET /v1/api/pedidos/cliente/{cli_id}/{page} ─────────────────────────

    #[OA\Get(
        path: '/v1/api/pedidos/cliente/{cli_id}/{page}',
        operationId: 'listPedidosByClient',
        summary: 'Listar pedidos de um cliente',
        tags: ['Pedidos']
    )]
    #[OA\Parameter(name: 'cli_id', in: 'path', required: true,  schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'page',   in: 'path', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Lista de pedidos')]
    #[OA\Response(response: 404, description: 'Nenhum pedido encontrado')]
    public function listByClient(Request $request, Response $response, array $args): Response
    {
        $cliId = (int)($args['cli_id'] ?? 0);
        $page  = (int)($args['page']   ?? 1);

        if (!$cliId) {
            $response->getBody()->write(json_encode(['status' => 400, 'message' => 'CLI ID inválido.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $result = pedidosModel::listByClient($cliId, $page);
        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    // ─── GET /v1/api/pedido/cliente/{id} ─────────────────────────────────────────────

    #[OA\Get(
        path: '/v1/api/pedido/cliente/{id}',
        operationId: 'getPedidoById',
        summary: 'Buscar pedido completo por ID',
        tags: ['Pedidos']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Pedido encontrado')]
    #[OA\Response(response: 404, description: 'Pedido não encontrado')]
    public function getById(Request $request, Response $response, array $args): Response
    {
        $pedId = (int)($args['id'] ?? 0);

        // Opcionalmente filtra pelo cliente (via query string ?cli_id=X)
        $params = $request->getQueryParams();
        $cliId  = isset($params['cli_id']) ? (int)$params['cli_id'] : null;

        $result = pedidosModel::getById($pedId, $cliId);
        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    // ─── GET /v1/api/pedidos/{id}/status-pagamento ─────────────────────────

    #[OA\Get(
        path: '/v1/api/pedidos/{id}/status-pagamento',
        operationId: 'getPedidoStatusPagamento',
        summary: 'Consultar status de pagamento (OrendaPay)',
        tags: ['Pedidos']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Status do pagamento')]
    public function statusPagamento(Request $request, Response $response, array $args): Response
    {
        try {
            $pedId = (int)($args['id'] ?? 0);
            $result = pedidosModel::checkPagamentoStatus($pedId);
            $response->getBody()->write(json_encode($result));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // ─── PATCH /v1/api/pedidos/{id}/status ───────────────────────────────────

    #[OA\Patch(
        path: '/v1/api/pedidos/{id}/status',
        operationId: 'updatePedidoStatus',
        summary: 'Atualizar situação de entrega do pedido',
        tags: ['Pedidos']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'pse_id', type: 'integer', description: 'ID da nova situação de entrega'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Situação atualizada')]
    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        try {
            $pedId = (int)($args['id'] ?? 0);
            $body  = $request->getParsedBody();
            if (empty($body)) {
                $raw  = (string)$request->getBody();
                $body = json_decode($raw, true) ?? [];
            }

            $pseId = (int)($body['pse_id'] ?? 0);
            if (!$pseId) {
                $response->getBody()->write(json_encode(['status' => 400, 'message' => 'Informe o pse_id.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $result = pedidosModel::updateStatusEntrega($pedId, $pseId);
            $response->getBody()->write(json_encode($result));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // ─── PATCH /v1/api/pedidos/{id}/pagamento ──────────────────────────────

    #[OA\Patch(
        path: '/v1/api/pedidos/{id}/pagamento',
        operationId: 'updatePedidoPagamento',
        summary: 'Trocar metodo de pagamento do pedido',
        tags: ['Pedidos']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'pagamento_metodo', type: 'integer'),
                new OA\Property(property: 'pagamento_troco', type: 'number'),
                new OA\Property(property: 'cartao_numero', type: 'string'),
                new OA\Property(property: 'cartao_nome', type: 'string'),
                new OA\Property(property: 'cartao_validade', type: 'string'),
                new OA\Property(property: 'cartao_cvv', type: 'string'),
                new OA\Property(property: 'cartao_parcelas', type: 'integer'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Metodo atualizado')]
    public function updatePayment(Request $request, Response $response, array $args): Response
    {
        try {
            $pedId = (int)($args['id'] ?? 0);
            $body  = $request->getParsedBody();
            if (empty($body)) {
                $raw  = (string)$request->getBody();
                $body = json_decode($raw, true) ?? [];
            }

            $result = pedidosModel::updatePayment($pedId, $body);
            $response->getBody()->write(json_encode($result));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // ─── POST /v1/api/pedidos/validar-cupom ──────────────────────────────────

    #[OA\Post(
        path: '/v1/api/pedidos/validar-cupom',
        operationId: 'validateCoupon',
        summary: 'Validar cupom e calcular desconto',
        tags: ['Pedidos']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'cupom',    type: 'string',  description: 'Código do cupom'),
                new OA\Property(property: 'cli_id',   type: 'integer', description: 'ID do cliente logado'),
                new OA\Property(property: 'subtotal', type: 'number',  description: 'Subtotal atual do carrinho'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Cupom válido',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status',   type: 'integer'),
                new OA\Property(property: 'message',  type: 'string'),
                new OA\Property(property: 'desconto', type: 'number'),
                new OA\Property(property: 'cup_id',   type: 'integer'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Cupom inválido ou expirado')]
    public function validateCoupon(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            if (empty($body)) {
                $raw  = (string)$request->getBody();
                $body = json_decode($raw, true) ?? [];
            }

            $cupom    = trim($body['cupom']    ?? '');
            $cliId    = (int)($body['cli_id']   ?? 0);
            $subtotal = (float)($body['subtotal'] ?? 0);

            if (!$cupom) {
                $response->getBody()->write(json_encode([
                    'status'  => 400,
                    'message' => 'Informe o código do cupom.',
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            if (!$cliId) {
                $response->getBody()->write(json_encode([
                    'status'  => 401,
                    'message' => 'Faça login para usar cupons.',
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }

            $result = pedidosModel::validateCoupon($cupom, $cliId, $subtotal);
            $response->getBody()->write(json_encode($result));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // ─── GET /v1/api/pedidos/{page} ──────────────────────────────────────

    #[OA\Get(
        path: '/v1/api/pedidos/{page}',
        operationId: 'listAllPedidos',
        summary: 'Listar pedidos com filtros opcionais',
        tags: ['Pedidos']
    )]
    #[OA\Parameter(name: 'page', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'pse_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'ID da situação de entrega')]
    #[OA\Parameter(name: 'con_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'ID do contratante')]
    #[OA\Response(response: 200, description: 'Lista de pedidos')]
    #[OA\Response(response: 404, description: 'Nenhum pedido encontrado')]
    public function listAll(Request $request, Response $response, array $args): Response
    {
        try {
            $page = (int)($args['page'] ?? 1);
            $params = $request->getQueryParams();
            $pseId = isset($params['pse_id']) ? (int)$params['pse_id'] : null;
            $conId = (int)($params['con_id'] ?? 0);

            if (!$conId) {
                $response->getBody()->write(json_encode([
                    'status'  => 400,
                    'message' => 'Informe o con_id.',
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Filtro por escopo de unidade: admin vê todos do contratante;
            // demais usuários veem só pedidos de unidades em user_unidades.
            $allowedUniIds = unitScope::userUnidades($request);

            $result = pedidosModel::listByContratante($conId, $pseId, $page, $allowedUniIds);
            $response->getBody()->write(json_encode($result));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // ─── GET /v1/api/pedidos/counts ───────────────────────────────────────

    #[OA\Get(
        path: '/v1/api/pedidos/counts',
        operationId: 'getPedidosCounts',
        summary: 'Contar pedidos por situação de entrega',
        tags: ['Pedidos']
    )]
    #[OA\Parameter(name: 'con_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Contagem de pedidos por status',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Con ID inválido')]
    public function counts(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $conId = (int)($params['con_id'] ?? 0);

            if (!$conId) {
                $response->getBody()->write(json_encode([
                    'status'  => 400,
                    'message' => 'Informe o con_id.',
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $allowedUniIds = unitScope::userUnidades($request);
            $result = pedidosModel::countsByStatus($conId, $allowedUniIds);
            $response->getBody()->write(json_encode($result));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
