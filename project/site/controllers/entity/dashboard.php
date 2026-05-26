<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\dashboard as dashboardModel;
use Functions\slim\getParsedBody as getParsedBody;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'KPIS',
    description: 'Indicadores Chave de Desempenho',
    properties: [
        new OA\Property(property: 'receitaTotal', type: 'number', format: 'float', example: 1542.50),
        new OA\Property(property: 'numeroPedidos', type: 'integer', example: 12),
        new OA\Property(property: 'ticketMedio', type: 'number', format: 'float', example: 128.54),
        new OA\Property(property: 'novosClientes', type: 'integer', example: 5)
    ]
)]

#[OA\Schema(
    schema: 'DashboardPeriodo',
    description: 'Dados consolidados por período',
    properties: [
        new OA\Property(
            property: 'periodo',
            properties: [
                new OA\Property(property: 'inicio', type: 'string', format: 'date', example: '2026-01-22'),
                new OA\Property(property: 'fim', type: 'string', format: 'date', example: '2026-01-22')
            ]
        ),
        new OA\Property(property: 'kpis', ref: '#/components/schemas/KPIS'),
        new OA\Property(
            property: 'vendasEvolucao',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'data', type: 'string', format: 'date', example: '2026-01-22'),
                    new OA\Property(property: 'valor', type: 'number', format: 'float', example: 120.00),
                    new OA\Property(property: 'quantidade', type: 'integer', example: 1)
                ]
            )
        )
    ]
)]

#[OA\Tag(name: 'Dashboard', description: 'Métricas e KPIs do sistema')]
class dashboard
{
    #[OA\Post(
        path: '/dashboard',
        operationId: 'getDashboardMetrics',
        summary: 'Obter métricas do dashboard',
        description: 'Retorna KPIs e gráficos. Aceita filtros opcionais dentro do objeto "data".',
        security: [['bearerAuth' => []]],
        tags: ['Dashboard']
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'client_id', type: 'integer', example: 1),
                        new OA\Property(property: 'data_inicio', type: 'string', format: 'date', example: '2026-01-01'),
                        new OA\Property(property: 'data_fim', type: 'string', format: 'date', example: '2026-01-31')
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Dados do dashboard recuperados com sucesso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'hoje', ref: '#/components/schemas/DashboardPeriodo'),
                new OA\Property(property: 'ultimos7Dias', ref: '#/components/schemas/DashboardPeriodo'),
                new OA\Property(property: 'esteMes', ref: '#/components/schemas/DashboardPeriodo'),
                new OA\Property(property: 'customizado', ref: '#/components/schemas/DashboardPeriodo', nullable: true)
            ]
        )
    )]
    public function index(Request $request, Response $response): Response
    {
        try {
            // Padrão do projeto para pegar dados do body
            $postData = $request->getParsedBody() ?? (new getParsedBody)->filter($_POST)
                ->jsonToArray($_POST)
                ->objectData('data')
                ->getData();

            // Extract from 'data' property if it exists
            $data = isset($postData['data']) ? $postData['data'] : $postData;

            $startDate = $data['data_inicio'] ?? null;
            $endDate = $data['data_fim'] ?? null;
            $clientId = $data['client_id'] ?? null;

            $result = dashboardModel::getDashboard($startDate, $endDate, $clientId);

            $response->getBody()->write(json_encode($result['data']));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 200);
        } catch (\Throwable $e) {
            $error = ['status' => 500, 'message' => $e->getMessage()];
            $response->getBody()->write(json_encode($error));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Post(
        path: '/dashboard/insights',
        operationId: 'getDashboardInsights',
        summary: 'Insights automáticos do Agente IA',
        description: 'Gera insights baseados nos KPIs de hoje, últimos 7 dias, mês atual, pedidos pendentes e top produto.',
        security: [['bearerAuth' => []]],
        tags: ['Dashboard']
    )]
    #[OA\Response(response: 200, description: 'Lista de insights gerados automaticamente')]
    public function insights(Request $request, Response $response): Response
    {
        try {
            $result = dashboardModel::getInsights();
            $status = $result['status'] ?? 200;
            $body   = $status === 200 ? ($result['data'] ?? []) : $result;
            $response->getBody()->write(json_encode($body));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['status' => 500, 'message' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
