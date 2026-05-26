<?php

namespace App\model\entity;

use Hyperf\DbConnection\Model\Model;
use App\model\entity\produtosPrecos;
use App\model\entity\produtosGradesOpcoes;

class produtosGrades extends Model
{
    protected ?string $table = 'tb_produtos_grades';
    protected string $primaryKey = 'prg_id';
    public bool $timestamps = false;

    protected array $fillable = [
        'prg_titulo',
        'prg_obrigatoria',
        'prg_ativo',
        'prg_excluido',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    public function precos()
    {
        return $this->hasMany(produtosPrecos::class, 'prp_prg_id', 'prg_id')
            ->where('prp_excluido', 0);
    }

    public function opcoes()
    {
        return $this->hasMany(produtosGradesOpcoes::class, 'pgo_prg_id', 'prg_id')
            ->where('pgo_ativo', 1)
            ->where('pgo_excluido', 0);
    }

    public static function getGradesPublic(int $proId, int $uniId, int $carId = 0): array
    {
        try {
            if (!$proId || !$uniId) {
                return ['status' => 400, 'message' => 'pro_id e uni_id são obrigatórios.'];
            }

            // Produto marcado como esgotado nesta unidade → não retorna nada
            if (in_array($proId, produtosRelUnidades::inativosByUnidade($uniId), true)) {
                return ['status' => 200, 'grades' => [], 'data' => [], 'esgotado' => true];
            }

            // car_id é opcional para compat, mas sem ele os preços não podem ser
            // resolvidos (preço passou a ser por cardápio). Se ausente, escolhe o
            // cardápio mais recente ativo da unidade como fallback.
            if (!$carId) {
                $carId = (int) \Hyperf\DbConnection\Db::table('tb_cardapios_rel_unidades')
                    ->where('cru_uni_id', $uniId)
                    ->where('cru_excluido', 0)
                    ->where('cru_ativo', 1)
                    ->orderByDesc('cru_id')
                    ->value('cru_car_id');
            }

            $DB = \Hyperf\DbConnection\Db::class;

            $produto = $DB::table('tb_produtos')
                ->where('pro_id', $proId)
                ->first();
            $conId = $produto->pro_con_id ?? null;

            $promocaoId = $DB::table('tb_produtos_promocoes')
                ->where('ppr_pro_id', $proId)
                ->where('ppr_uni_id', $uniId)
                ->where('ppr_ativo', 1)
                ->where('ppr_excluido', 0)
                ->whereRaw('NOW() BETWEEN ppr_inicio AND ppr_fim')
                ->whereRaw('FIND_IN_SET(WEEKDAY(NOW())+1, ppr_dia)')
                ->whereRaw('TIME(NOW()) > ppr_horario_inicio')
                ->whereRaw('TIME(NOW()) < ppr_horario_fim')
                ->value('ppr_id');
            $promocaoId = $promocaoId ?: 0;

            $fetchGrades = function (int $pprId) use ($DB, $proId) {
                return $DB::table('tb_produtos_rel_grades as rel')
                    ->join('tb_produtos_grades as prg', 'prg.prg_id', '=', 'rel.prr_prg_id')
                    ->where('rel.prr_pro_id', $proId)
                    ->where('rel.prr_excluido', 0)
                    ->where('rel.prr_ppr_id', $pprId)
                    ->where('prg.prg_ativo', 1)
                    ->where('prg.prg_excluido', 0)
                    ->select(
                        'prg.prg_id',
                        'prg.prg_titulo',
                        'prg.prg_obrigatoria',
                        'prg.prg_pgt_id',
                        'prg.prg_rel_prg_id',
                        'rel.prr_prg_descricao',
                        'rel.prr_prg_pgt_id',
                        'rel.prr_prg_qtd_min',
                        'rel.prr_prg_qtd_gratis',
                        'rel.prr_prg_qtd_max',
                        'rel.prr_prg_obrigatoria'
                    )
                    ->orderByRaw('FIELD(prg.prg_id, 3) DESC, FIELD(prg.prg_id, 5) DESC, FIELD(prg.prg_id, 7) DESC, FIELD(prg.prg_pgt_id, 4) DESC, rel.prr_id')
                    ->get();
            };

            // 1) Busca grades vinculadas ao produto (tb_produtos_rel_grades + tb_produtos_grades)
            $gradesRaw = $fetchGrades($promocaoId);
            if ($gradesRaw->isEmpty() && $promocaoId !== 0) {
                $promocaoId = 0;
                $gradesRaw = $fetchGrades($promocaoId);
            }

            $baseUrl = defined('URL') ? URL : '';

            $result = [];

            foreach ($gradesRaw as $grade) {
                $grade = (array)$grade;

                // 2) Para cada grade, busca as opções com preço (tb_produtos_precos + tb_produtos_grades_opcoes)
                $opcoesQuery = $DB::table('tb_produtos_grades_opcoes as pgo')
                    ->join('tb_produtos_precos as prp', function ($join) use ($proId, $grade) {
                        $join->on('prp.prp_pgo_id', '=', 'pgo.pgo_id')
                            ->where('prp.prp_prg_id', '=', $grade['prg_id'])
                            ->where('prp.prp_pro_id', '=', $proId)
                            ->where('prp.prp_excluido', '=', 0);
                    })
                    ->join('tb_produtos as pro', 'pro.pro_id', '=', 'prp.prp_pro_id')
                    ->leftJoin('tb_produtos_imagens as pri', function ($join) {
                        $join->on('pri.pri_id', '=', 'pro.pro_pri_id')
                            ->where('pri.pri_ativo', 1)
                            ->where('pri.pri_excluido', 0);
                    })
                    ->where('pgo.pgo_ativo', 1)
                    ->where('pgo.pgo_excluido', 0)
                    ->where('prp.prp_ppr_id', $promocaoId)
                    ->where('prp.prp_car_id', $carId)
                    ->select(
                        'pgo.pgo_id',
                        'pgo.pgo_titulo',
                        'pgo.pgo_img',
                        'prp.prp_id',
                        'prp.prp_preco',
                        'prp.prp_embalagem',
                        'prp.prp_pgo_min',
                        'prp.prp_pgo_max',
                        'prp.prp_ppr_id',
                        'pri.pri_img'
                    )
                    ->orderBy('prp.prp_preco', 'asc')
                    ->orderBy('pgo.pgo_titulo', 'asc');

                if ($conId !== null) {
                    $opcoesQuery->where(function ($q) use ($conId) {
                        $q->where('pgo.pgo_con_id', $conId)
                            ->orWhereNull('pgo.pgo_con_id');
                    });
                }

                $opcoesRaw = $opcoesQuery->get();
                if ($opcoesRaw->isEmpty() && $promocaoId !== 0) {
                    $opcoesRaw = $opcoesQuery
                        ->where('prp.prp_ppr_id', 0)
                        ->get();
                }

                $opcoes = [];
                foreach ($opcoesRaw as $op) {
                    $op = (array)$op;
                    $imgPath = 'images/produtos/default.jpg';
                    if (!empty($op['pgo_img'])) {
                        $imgPath = 'images/grades/' . $op['pgo_img'];
                    } elseif (!empty($op['pri_img'])) {
                        $imgPath = 'images/produtos/' . $op['pri_img'];
                    }

                    $opcoes[(int)$op['pgo_id']] = [
                        'id'        => (int)$op['prp_id'],
                        'opcao'     => (int)$op['pgo_id'],
                        'titulo'    => $op['pgo_titulo'],
                        'img'       => $baseUrl ? rtrim($baseUrl, '/') . '/' . $imgPath : $imgPath,
                        'preco'     => (float)$op['prp_preco'],
                        'embalagem' => (float)($op['prp_embalagem'] ?? 0),
                        'promocao'  => (int)($op['prp_ppr_id'] ?? 0),
                        'min'       => (int)($op['prp_pgo_min'] ?? 0),
                        'max'       => (int)($op['prp_pgo_max'] ?? 100),
                        'qtd'       => 0,
                    ];
                }

                // Só incluir grades que possuem ao menos uma opção com preço
                if (count($opcoes) > 0) {
                    $result[] = [
                        'id'                => (int)$grade['prg_id'],
                        'titulo'            => $grade['prg_titulo'],
                        'descricao'         => $grade['prr_prg_descricao'] ?? '',
                        'tipo'              => (int)($grade['prr_prg_pgt_id'] ?? $grade['prg_pgt_id'] ?? 1),
                        'min'               => (int)($grade['prr_prg_qtd_min'] ?? 0),
                        'gratis'            => (int)($grade['prr_prg_qtd_gratis'] ?? 0),
                        'max'               => (int)($grade['prr_prg_qtd_max'] ?? 100),
                        'obrigatoria'       => (int)($grade['prr_prg_obrigatoria'] ?? $grade['prg_obrigatoria'] ?? 0),
                        'mae'               => (int)($grade['prg_rel_prg_id'] ?? 0),
                        'itens_qtd'         => count($opcoes),
                        'itens_qtd_desejada'=> 0,
                        'itens'             => $opcoes,
                    ];
                }
            }

            return ['status' => 200, 'grades' => $result, 'data' => $result];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function getTiposPublic(): array
    {
        try {
            $precos = \Hyperf\DbConnection\Db::table('tb_produtos_grades_tipos')->get();
            return ['status' => 200, 'data' => $precos->toArray()];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
