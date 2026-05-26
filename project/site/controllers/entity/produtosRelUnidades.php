<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\produtosRelUnidades as produtosRelUnidadesModel;
use App\model\entity\produtos_rel_cardapios as produtosRelCardapiosModel;
use Hyperf\DbConnection\Db;

class produtosRelUnidades
{
    /**
     * Lista os produtos vinculados aos cardápios da unidade,
     * com flag `esgotado` (true se pru_ativo=0).
     */
    public function listByUnidade(Request $request, Response $response, array $args): Response
    {
        try {
            $uniId = (int)($args['uni_id'] ?? 0);
            if ($uniId <= 0) {
                return self::json($response, ['status' => 400, 'message' => 'uni_id inválido'], 400);
            }

            // 1) Cardápios ativos vinculados à unidade
            $carIds = Db::table('tb_cardapios_rel_unidades')
                ->where('cru_uni_id', $uniId)
                ->where('cru_excluido', 0)
                ->where('cru_ativo', 1)
                ->pluck('cru_car_id')
                ->map(fn($v) => (int)$v)
                ->toArray();

            if (count($carIds) === 0) {
                return self::json($response, ['status' => 200, 'data' => []], 200);
            }

            // 2) Produtos únicos desses cardápios (ativos, não excluídos)
            $rows = Db::table('tb_produtos_rel_cardapios as prr')
                ->join('tb_produtos as pro', 'pro.pro_id', '=', 'prr.prr_pro_id')
                ->leftJoin('tb_produtos_imagens as pri', function ($j) {
                    $j->on('pri.pri_id', '=', 'pro.pro_pri_id')
                      ->where('pri.pri_excluido', 0);
                })
                ->whereIn('prr.prr_car_id', $carIds)
                ->where('prr.prr_excluido', 0)
                ->where('prr.prr_ativo', 1)
                ->where('pro.pro_excluido', 0)
                ->select(
                    'pro.pro_id',
                    'pro.pro_titulo',
                    'pro.pro_descricao',
                    'pri.pri_img'
                )
                ->groupBy('pro.pro_id', 'pro.pro_titulo', 'pro.pro_descricao', 'pri.pri_img')
                ->orderBy('pro.pro_titulo', 'asc')
                ->get();

            // 3) IDs de produtos esgotados nesta unidade
            $inativos = array_flip(produtosRelUnidadesModel::inativosByUnidade($uniId));

            $data = [];
            foreach ($rows as $r) {
                $proId = (int)$r->pro_id;
                $data[] = [
                    'pro_id'        => $proId,
                    'pro_titulo'    => $r->pro_titulo,
                    'pro_descricao' => $r->pro_descricao,
                    'pri_img'       => $r->pri_img,
                    'esgotado'      => isset($inativos[$proId]),
                ];
            }

            return self::json($response, ['status' => 200, 'data' => $data], 200);
        } catch (\Throwable $e) {
            return self::json($response, ['status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PATCH: marca produto como disponível/esgotado na unidade.
     * Body: { ativo: 0|1 }
     */
    public function setStatus(Request $request, Response $response, array $args): Response
    {
        try {
            $uniId = (int)($args['uni_id'] ?? 0);
            $proId = (int)($args['pro_id'] ?? 0);
            $body  = $request->getParsedBody();
            $data  = $body['data'] ?? $body;
            $ativo = (int)($data['ativo'] ?? 1);

            if ($uniId <= 0 || $proId <= 0) {
                return self::json($response, ['status' => 400, 'message' => 'uni_id e pro_id são obrigatórios'], 400);
            }

            produtosRelUnidadesModel::setStatus($proId, $uniId, $ativo);

            return self::json($response, [
                'status'   => 200,
                'message'  => $ativo ? 'Produto marcado como disponível' : 'Produto marcado como esgotado',
                'esgotado' => $ativo ? false : true,
            ], 200);
        } catch (\Throwable $e) {
            return self::json($response, ['status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    private static function json(Response $response, array $payload, int $status): Response
    {
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
