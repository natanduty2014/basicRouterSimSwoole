<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\cupom as cupomModel;
use OpenApi\Attributes as OA;
use Functions\jwt\jwt;

#[OA\Tag(name: 'Cupom', description: 'Operações relacionadas a cupons de desconto')]
class cupom
{
    public function create(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            $data = $body['data'] ?? $body;

            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $conId = $tokenData->user_con_id;

            $result = cupomModel::create($data, $conId);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        try {
            $body = $request->getParsedBody();
            $data = $body['data'] ?? $body;

            $id = $args['id'];
            $result = cupomModel::edit($data, $id);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    public function listAll(Request $request, Response $response, array $args): Response
    {
        try {
            $page = $args['page'] ?? 1;

            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $conId = $tokenData->user_con_id;

            $result = cupomModel::listAll($page, $conId);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $result = cupomModel::search($id);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    public function activeDisable(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $result = cupomModel::activeDisable($id);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $result = cupomModel::deleted($id);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }
}
