<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class pagamentosMetodos extends Model
{
    protected ?string $table = 'tb_pagamentos_metodos';
    protected string $primaryKey = 'pag_id';
    public bool $timestamps = false;

    public static function listByUnidade(int $uniId, int $freteTipo): array
    {
        try {
            if ($uniId <= 0) {
                return ['status' => 400, 'message' => 'uni_id invalido'];
            }

            $query = Db::table('tb_pagamentos_metodos as pag')
                ->join('tb_pagamentos_metodos_rel_unidades as rel', function ($join) use ($uniId) {
                    $join->on('rel.pmc_pag_id', '=', 'pag.pag_id')
                        ->where('rel.pmc_uni_id', $uniId);
                })
                ->where('rel.pmc_ativo', 1)
                ->where('rel.pmc_excluido', 0)
                ->where('pag.pag_ativo', 1)
                ->where('pag.pag_excluido', 0);

            if ($freteTipo === 3) {
                $query->where(function ($q) {
                    $q->where('rel.pmc_site', 1)
                        ->orWhere('rel.pmc_entrega', 1);
                });
            } else {
                $query->where(function ($q) {
                    $q->where('rel.pmc_site', 1)
                        ->orWhere('rel.pmc_local', 1);
                });
            }

            $rows = $query
                ->select(
                    'pag.pag_id',
                    'pag.pag_titulo',
                    'pag.pag_img',
                    'rel.pmc_site',
                    'rel.pmc_local',
                    'rel.pmc_entrega'
                )
                ->orderBy('pag.pag_id', 'asc')
                ->get()
                ->map(fn($r) => (array)$r)
                ->toArray();

            return ['status' => 200, 'data' => $rows];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lista TODOS os métodos master ativos + estado atual da relação com a unidade.
     * LEFT JOIN: métodos sem relação ainda aparecem com pmc_* nulos.
     */
    public static function listForCms(int $uniId): array
    {
        try {
            if ($uniId <= 0) {
                return ['status' => 400, 'message' => 'uni_id inválido'];
            }

            $rows = Db::table('tb_pagamentos_metodos as pag')
                ->leftJoin('tb_pagamentos_metodos_rel_unidades as rel', function ($join) use ($uniId) {
                    $join->on('rel.pmc_pag_id', '=', 'pag.pag_id')
                        ->where('rel.pmc_uni_id', '=', $uniId)
                        ->where('rel.pmc_excluido', '=', 0);
                })
                ->where('pag.pag_ativo', 1)
                ->where('pag.pag_excluido', 0)
                ->orderBy('pag.pag_id', 'asc')
                ->select(
                    'pag.pag_id',
                    'pag.pag_titulo',
                    'pag.pag_img',
                    'pag.pag_observacao',
                    'pag.pag_site as pag_site_default',
                    'pag.pag_local as pag_local_default',
                    'pag.pag_entrega as pag_entrega_default',
                    'rel.pmc_id',
                    'rel.pmc_site',
                    'rel.pmc_local',
                    'rel.pmc_entrega',
                    'rel.pmc_ativo'
                )
                ->get()
                ->map(fn($r) => (array)$r)
                ->toArray();

            return ['status' => 200, 'data' => $rows];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    /**
     * UPSERT em tb_pagamentos_metodos_rel_unidades.
     * Para cada item: se já existe relação para o par (uni_id, pag_id), atualiza; senão insere.
     * Atômico via transação.
     *
     * $methods = [['pag_id'=>1,'pmc_site'=>0,'pmc_local'=>1,'pmc_entrega'=>1,'pmc_ativo'=>1], ...]
     */
    public static function saveForUnidade(int $uniId, array $methods): array
    {
        if ($uniId <= 0) {
            return ['status' => 400, 'message' => 'uni_id inválido'];
        }

        Db::beginTransaction();
        try {
            $existing = Db::table('tb_pagamentos_metodos_rel_unidades')
                ->where('pmc_uni_id', $uniId)
                ->where('pmc_excluido', 0)
                ->get()
                ->keyBy('pmc_pag_id')
                ->toArray();

            $validPagIds = Db::table('tb_pagamentos_metodos')
                ->where('pag_ativo', 1)
                ->where('pag_excluido', 0)
                ->pluck('pag_id')
                ->toArray();
            $validPagIds = array_flip($validPagIds);

            foreach ($methods as $m) {
                $pagId = isset($m['pag_id']) ? (int)$m['pag_id'] : 0;
                if ($pagId <= 0 || ! isset($validPagIds[$pagId])) {
                    continue;
                }

                $payload = [
                    'pmc_site'    => isset($m['pmc_site']) ? (int)((bool)$m['pmc_site']) : 0,
                    'pmc_local'   => isset($m['pmc_local']) ? (int)((bool)$m['pmc_local']) : 0,
                    'pmc_entrega' => isset($m['pmc_entrega']) ? (int)((bool)$m['pmc_entrega']) : 0,
                    'pmc_ativo'   => isset($m['pmc_ativo']) ? (int)((bool)$m['pmc_ativo']) : 1,
                ];

                if (isset($existing[$pagId])) {
                    Db::table('tb_pagamentos_metodos_rel_unidades')
                        ->where('pmc_id', $existing[$pagId]->pmc_id)
                        ->update($payload);
                } else {
                    Db::table('tb_pagamentos_metodos_rel_unidades')->insert(array_merge($payload, [
                        'pmc_pag_id' => $pagId,
                        'pmc_uni_id' => $uniId,
                        'pmc_excluido' => 0,
                    ]));
                }
            }

            Db::commit();
            return ['status' => 200, 'message' => 'Métodos de recebimento atualizados'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao salvar métodos', 'details' => $e->getMessage()];
        }
    }
}
