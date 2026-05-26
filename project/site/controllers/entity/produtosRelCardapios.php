<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\produtos_rel_cardapios as produtosRelCardapiosModel;
use App\model\entity\produtosPrecos;

class produtosRelCardapios
{
    public function create(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            $data = $body['data'] ?? $body;

            $result = produtosRelCardapiosModel::createRecord($data);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $carId = null;
            $proId = null;

            // Busca o registro para saber qual produto/cardápio remover os preços
            $rel = produtosRelCardapiosModel::where('prr_id', $id)->where('prr_excluido', 0)->first();
            if ($rel) {
                $carId = $rel->prr_car_id;
                $proId = $rel->prr_pro_id;
            }

            $result = produtosRelCardapiosModel::deleteRecord($id);

            // Remove também os preços vinculados a este produto + cardápio
            if ($result['status'] === 200 && $carId && $proId) {
                produtosPrecos::where('prp_car_id', $carId)
                    ->where('prp_pro_id', $proId)
                    ->where('prp_excluido', 0)
                    ->update(['prp_excluido' => 1]);
            }

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function listByCardapio(Request $request, Response $response, array $args): Response
    {
        try {
            $carId = $args['car_id'];

            $records = produtosRelCardapiosModel::where('prr_car_id', $carId)
                ->where('prr_excluido', 0)
                ->where('prr_ativo', 1)
                ->whereHas('produto', fn($q) => $q->where('pro_excluido', 0))
                ->with([
                    'produto' => function ($q) {
                        $q->where('pro_excluido', 0)
                          ->with([
                            'imagens' => fn($qi) => $qi->where('pri_capa', 1)->where('pri_excluido', 0),
                          ]);
                    }
                ])
                ->orderBy('prr_id', 'asc')
                ->get();

            // Para cada produto, carrega os preços do cardápio
            $data = $records->map(function ($rel) use ($carId) {
                $arr = $rel->toArray();
                $arr['precos'] = produtosPrecos::where('prp_car_id', $carId)
                    ->where('prp_pro_id', $rel->prr_pro_id)
                    ->where('prp_excluido', 0)
                    ->with(['grade', 'opcao'])
                    ->get()
                    ->toArray();
                return $arr;
            })->toArray();

            $response->getBody()->write(json_encode(['status' => 200, 'data' => $data]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }
}
