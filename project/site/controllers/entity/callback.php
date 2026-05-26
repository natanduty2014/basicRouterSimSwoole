<?php

namespace App\controllers\entity;

use App\model\entity\pedidos as pedidosModel;
use Psr\Http\Message\ResponseInterface as ResponseSlim;
use Psr\Http\Message\ServerRequestInterface as RequestSlim;

class callback
{
    public static function StatusPayment(RequestSlim $request, ResponseSlim $response, array $args): ResponseSlim
    {
        try {
            $body = $request->getParsedBody();
            if (empty($body)) {
                $raw = (string)$request->getBody();
                $body = json_decode($raw, true) ?? [];
            }

            $payload = [
                'numero' => $body['numero'] ?? null,
                'situacao' => $body['situacao'] ?? null,
                'codigo_custom' => $body['codigo_custom'] ?? null,
                'seu_codigo' => $body['seu_codigo'] ?? null,
            ];

            $result = pedidosModel::updatePagamentoCallback($payload);
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
    }
}
