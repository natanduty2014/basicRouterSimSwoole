<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\produtosPrecos as produtosPrecosModel;
use Functions\jwt\jwt;

class produtosPrecos
{
    public function create(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            $data = $body['data'] ?? $body;

            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $data['prp_usu_id'] = $tokenData->user_id ?? 0;

            if (empty($data['prp_pro_id']) || empty($data['prp_car_id'])) {
                $response->getBody()->write(json_encode(['status' => 422, 'message' => 'prp_pro_id e prp_car_id são obrigatórios']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }

            $record = new produtosPrecosModel();
            $record->prp_pro_id   = $data['prp_pro_id'];
            $record->prp_car_id   = $data['prp_car_id'];
            $record->prp_prg_id   = $data['prp_prg_id'] ?? null;
            $record->prp_pgo_id   = $data['prp_pgo_id'] ?? null;
            $record->prp_pgo_min  = $data['prp_pgo_min'] ?? 0;
            $record->prp_pgo_max  = $data['prp_pgo_max'] ?? 100;
            $record->prp_preco    = $data['prp_preco'] ?? 0;
            $record->prp_embalagem = $data['prp_embalagem'] ?? 0;
            $record->prp_ppr_id   = $data['prp_ppr_id'] ?? 0;
            $record->prp_usu_id   = $data['prp_usu_id'];
            $record->prp_excluido = 0;
            $record->save();

            $response->getBody()->write(json_encode(['status' => 201, 'message' => 'Preço criado com sucesso', 'data' => $record]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $body = $request->getParsedBody();
            $data = $body['data'] ?? $body;

            $record = produtosPrecosModel::where('prp_id', $id)->where('prp_excluido', 0)->first();
            if (!$record) {
                $response->getBody()->write(json_encode(['status' => 404, 'message' => 'Preço não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            if (isset($data['prp_pgo_min']))   $record->prp_pgo_min  = $data['prp_pgo_min'];
            if (isset($data['prp_pgo_max']))   $record->prp_pgo_max  = $data['prp_pgo_max'];
            if (isset($data['prp_preco']))     $record->prp_preco    = $data['prp_preco'];
            if (isset($data['prp_embalagem'])) $record->prp_embalagem = $data['prp_embalagem'];
            $record->save();

            $response->getBody()->write(json_encode(['status' => 200, 'message' => 'Preço atualizado', 'data' => $record]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $record = produtosPrecosModel::where('prp_id', $id)->where('prp_excluido', 0)->first();
            if (!$record) {
                $response->getBody()->write(json_encode(['status' => 404, 'message' => 'Preço não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            $record->prp_excluido = 1;
            $record->save();
            $response->getBody()->write(json_encode(['status' => 200, 'message' => 'Preço removido']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function listByCardapioProduto(Request $request, Response $response, array $args): Response
    {
        try {
            $carId = $args['car_id'];
            $proId = $args['pro_id'];

            $records = produtosPrecosModel::where('prp_car_id', $carId)
                ->where('prp_pro_id', $proId)
                ->where('prp_excluido', 0)
                ->with(['grade', 'opcao'])
                ->get();

            $response->getBody()->write(json_encode(['status' => 200, 'data' => $records]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function listByCardapio(Request $request, Response $response, array $args): Response
    {
        try {
            $carId = $args['car_id'];

            $records = produtosPrecosModel::where('prp_car_id', $carId)
                ->where('prp_excluido', 0)
                ->with(['grade', 'opcao'])
                ->get();

            $response->getBody()->write(json_encode(['status' => 200, 'data' => $records]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }
}
