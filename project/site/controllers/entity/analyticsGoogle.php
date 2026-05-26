<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\analyticsGoogle as analyticsGoogleModel;
use Functions\slim\getParsedBody as getParsedBody;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Analytics', description: 'Google Analytics Data API integration')]
class analyticsGoogle
{
    #[OA\Post(
        path: '/analytics/google',
        operationId: 'getGoogleAnalyticsData',
        summary: 'Google Analytics GA4 data',
        description: 'Returns sessions, users, traffic sources, devices, top pages and geo locations from Google Analytics.',
        security: [['bearerAuth' => []]],
        tags: ['Analytics']
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'startDate', type: 'string', format: 'date', example: '2026-03-12'),
                        new OA\Property(property: 'endDate', type: 'string', format: 'date', example: '2026-04-09'),
                    ]
                )
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Analytics data retrieved successfully')]
    public function getData(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody() ?? (new getParsedBody)->filter($_POST)
                ->jsonToArray($_POST)
                ->objectData('data')
                ->getData();

            $startDate = $data['startDate'] ?? $data['data']['startDate'] ?? null;
            $endDate   = $data['endDate']   ?? $data['data']['endDate']   ?? null;

            if (!$startDate || !$endDate) {
                $endDate   = date('Y-m-d');
                $startDate = date('Y-m-d', strtotime('-27 days'));
            }

            $result = analyticsGoogleModel::getData($startDate, $endDate);

            $response->getBody()->write(json_encode($result['data'] ?? $result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 200);
        } catch (\Throwable $e) {
            $error = ['status' => 500, 'message' => $e->getMessage()];
            $response->getBody()->write(json_encode($error));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
