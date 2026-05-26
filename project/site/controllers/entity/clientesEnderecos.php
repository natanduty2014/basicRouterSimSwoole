<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\clientesEnderecos as clientesEnderecosModel;
use Functions\jwt\jwt;

class clientesEnderecos
{
    private function getCliId(Request $request): int
    {
        $tokenAuth = $request->getHeader('Authorization')[0] ?? null;
        if (!$tokenAuth) throw new \Exception("Nenhum token fornecido", 401);
        $tokenData = jwt::decodetoken($tokenAuth);
        return (int)$tokenData->cli_id;
    }

    public function listAll(Request $request, Response $response): Response
    {
        try {
            $cliId = $this->getCliId($request);
            $result = clientesEnderecosModel::listByClient($cliId);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $code = $e->getCode() ?: 500;
            $code = $code >= 100 && $code < 600 ? $code : 500;
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($code);
        }
    }

    public function create(Request $request, Response $response): Response
    {
        try {
            $cliId = $this->getCliId($request);
            $body = $request->getParsedBody();
            $data = $body['data'] ?? $body;
            
            $result = clientesEnderecosModel::create($data, $cliId);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $code = $e->getCode() ?: 422;
            $code = $code >= 100 && $code < 600 ? $code : 500;
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($code);
        }
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        try {
            $cliId = $this->getCliId($request);
            $body = $request->getParsedBody();
            $data = $body['data'] ?? $body;
            $id = $args['id'];
            
            $result = clientesEnderecosModel::edit($data, $id, $cliId);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $code = $e->getCode() ?: 422;
            $code = $code >= 100 && $code < 600 ? $code : 500;
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($code);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $cliId = $this->getCliId($request);
            $id = $args['id'];
            
            $result = clientesEnderecosModel::deleted($id, $cliId);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $code = $e->getCode() ?: 422;
            $code = $code >= 100 && $code < 600 ? $code : 500;
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($code);
        }
    }
}
