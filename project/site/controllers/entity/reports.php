<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\reports as reportsModel;
use Functions\slim\getParsedBody as getParsedBody;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Relatórios', description: 'Relatórios operacionais via Refacil Serving API')]
class reports
{
    /**
     * Extrai data_inicio, data_fim e id_cliente do body da requisição.
     */
    private function params(Request $request): array
    {
        $body = $request->getParsedBody() ?? [];
        $data = is_array($body) && isset($body['data'])
            ? $body['data']
            : ((new getParsedBody)->filter($_POST)->jsonToArray($_POST)->objectData('data')->getData() ?? []);

        return [
            'data_inicio' => $data['data_inicio'] ?? null,
            'data_fim'    => $data['data_fim']    ?? null,
            'id_cliente'  => isset($data['id_cliente']) ? (int) $data['id_cliente'] : null,
        ];
    }

    private function respond(Response $response, array $result): Response
    {
        $status = $result['status'] ?? 200;
        $body   = $status === 200 ? ($result['data'] ?? []) : $result;
        $response->getBody()->write(json_encode($body));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/relatorios/pedidos-nao-finalizados',
        operationId: 'getPedidosNaoFinalizados',
        summary: 'Pedidos não finalizados',
        description: 'Pedidos com status diferente de "Paga" no período informado.',
        security: [['bearerAuth' => []]],
        tags: ['Relatórios']
    )]
    #[OA\RequestBody(required: false, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'data_inicio', type: 'string', format: 'date', example: '2025-01-01'),
            new OA\Property(property: 'data_fim',    type: 'string', format: 'date', example: '2025-12-31'),
        ])
    ]))]
    #[OA\Response(response: 200, description: 'Lista de pedidos não finalizados')]
    public function pedidosNaoFinalizados(Request $request, Response $response): Response
    {
        try {
            $p = $this->params($request);
            return $this->respond($response, reportsModel::getPedidosNaoFinalizados($p['data_inicio'], $p['data_fim']));
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['status' => 500, 'message' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/relatorios/clientes',
        operationId: 'getClientesRelatorio',
        summary: 'Clientes com métricas de pedidos',
        description: 'Lista de clientes com quantidade de pedidos, ticket médio e valor total gasto.',
        security: [['bearerAuth' => []]],
        tags: ['Relatórios']
    )]
    #[OA\Response(response: 200, description: 'Lista de clientes')]
    public function clientes(Request $request, Response $response): Response
    {
        try {
            $p = $this->params($request);
            return $this->respond($response, reportsModel::getClientes($p['data_inicio'], $p['data_fim']));
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['status' => 500, 'message' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/relatorios/itens-por-periodo',
        operationId: 'getItensPorPeriodo',
        summary: 'Itens mais pedidos por período',
        description: 'Ranking de produtos ordenado por quantidade no período.',
        security: [['bearerAuth' => []]],
        tags: ['Relatórios']
    )]
    #[OA\Response(response: 200, description: 'Ranking de itens por período')]
    public function itensPorPeriodo(Request $request, Response $response): Response
    {
        try {
            $p = $this->params($request);
            return $this->respond($response, reportsModel::getItensPorPeriodo($p['data_inicio'], $p['data_fim']));
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['status' => 500, 'message' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/relatorios/itens-por-cliente',
        operationId: 'getItensPorCliente',
        summary: 'Itens mais pedidos por cliente',
        description: 'Ranking de produtos agrupados por cliente. Aceita id_cliente para filtrar um cliente específico.',
        security: [['bearerAuth' => []]],
        tags: ['Relatórios']
    )]
    #[OA\RequestBody(required: false, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'data_inicio', type: 'string', format: 'date', example: '2025-01-01'),
            new OA\Property(property: 'data_fim',    type: 'string', format: 'date', example: '2025-12-31'),
            new OA\Property(property: 'id_cliente',  type: 'integer', example: 2581),
        ])
    ]))]
    #[OA\Response(response: 200, description: 'Ranking de itens por cliente')]
    public function itensPorCliente(Request $request, Response $response): Response
    {
        try {
            $p = $this->params($request);
            return $this->respond($response, reportsModel::getItensPorCliente($p['data_inicio'], $p['data_fim'], $p['id_cliente']));
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['status' => 500, 'message' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/relatorios/pedidos-dia-hora',
        operationId: 'getPedidosDiaHora',
        summary: 'Pedidos por dia da semana e horário',
        description: 'Distribuição de pedidos por dia (Segunda–Domingo) e hora (0–23).',
        security: [['bearerAuth' => []]],
        tags: ['Relatórios']
    )]
    #[OA\Response(response: 200, description: 'Distribuição por dia e hora')]
    public function pedidosDiaHora(Request $request, Response $response): Response
    {
        try {
            $p = $this->params($request);
            return $this->respond($response, reportsModel::getPedidosDiaHora($p['data_inicio'], $p['data_fim']));
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['status' => 500, 'message' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/relatorios/pedidos-bairro',
        operationId: 'getPedidosBairro',
        summary: 'Bairros que mais compram',
        description: 'Ranking de bairros por quantidade de pedidos, clientes distintos, total de vendas e ticket médio.',
        security: [['bearerAuth' => []]],
        tags: ['Relatórios']
    )]
    #[OA\Response(response: 200, description: 'Ranking de bairros')]
    public function pedidosBairro(Request $request, Response $response): Response
    {
        try {
            $p = $this->params($request);
            return $this->respond($response, reportsModel::getPedidosBairro($p['data_inicio'], $p['data_fim']));
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['status' => 500, 'message' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/relatorios/resumo-pedidos',
        operationId: 'getResumoPedidos',
        summary: 'Resumo consolidado de pedidos',
        description: 'KPIs gerais, top 10 itens e distribuição dia/hora em uma única chamada.',
        security: [['bearerAuth' => []]],
        tags: ['Relatórios']
    )]
    #[OA\Response(response: 200, description: 'Resumo consolidado')]
    public function resumoPedidos(Request $request, Response $response): Response
    {
        try {
            $p = $this->params($request);
            return $this->respond($response, reportsModel::getResumoPedidos($p['data_inicio'], $p['data_fim']));
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['status' => 500, 'message' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
