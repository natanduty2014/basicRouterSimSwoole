<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\produtosGrades as produtosGradesModel;
use Functions\jwt\jwt;

class produtosGrades
{
    public function listByContratante(Request $request, Response $response): Response
    {
        try {
            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $conId = $tokenData->user_con_id ?? 0;

            $grades = produtosGradesModel::query()
                ->where(function ($q) use ($conId) {
                    $q->where('prg_con_id', (int)$conId)
                      ->orWhereNull('prg_con_id');
                })
                ->where('prg_ativo', 1)
                ->where('prg_excluido', 0)
                ->with(['opcoes'])
                ->orderBy('prg_titulo', 'asc')
                ->get();

            $response->getBody()->write(json_encode(['status' => 200, 'data' => $grades->toArray()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function listByProduto(Request $request, Response $response, array $args): Response
    {
        try {
            $proId = $args['pro_id'];

            $grades = \Hyperf\DbConnection\Db::table('tb_produtos_rel_grades as rel')
                ->join('tb_produtos_grades as prg', 'prg.prg_id', '=', 'rel.prr_prg_id')
                ->where('rel.prr_pro_id', (int)$proId)
                ->where('rel.prr_excluido', 0)
                ->where('prg.prg_ativo', 1)
                ->where('prg.prg_excluido', 0)
                ->select('rel.prr_id', 'prg.prg_id', 'prg.prg_titulo', 'prg.prg_obrigatoria')
                ->get();

            $response->getBody()->write(json_encode(['status' => 200, 'data' => $grades->toArray()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function getGradesPublic(Request $request, Response $response, array $args): Response
    {
        try {
            $proId = (int)($args['pro_id'] ?? 0);
            $uniId = (int)($args['uni_id'] ?? 0);
            $carId = (int)($request->getQueryParams()['car_id'] ?? 0);

            $result = produtosGradesModel::getGradesPublic($proId, $uniId, $carId);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['status' => 500, 'message' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function getTiposPublic(Request $request, Response $response, array $args): Response
    {
        try {
            $result = produtosGradesModel::getTiposPublic();

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
