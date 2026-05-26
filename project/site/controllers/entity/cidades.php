<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\cidades as cidadesModel;

class cidades
{
    public function listOptions(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $estId = isset($params['est_id']) ? (int)$params['est_id'] : null;

            $result = cidadesModel::listOptions($estId);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
