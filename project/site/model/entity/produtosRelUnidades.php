<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

/**
 * Disponibilidade do produto por unidade.
 * Semântica:
 *   - ausência de linha OU pru_ativo=1 → produto disponível na unidade
 *   - pru_ativo=0 → produto esgotado na unidade (some do menu público)
 */
class produtosRelUnidades extends Model
{
    protected ?string $table = 'tb_produtos_rel_unidades';
    protected string $primaryKey = 'pru_id';
    public bool $timestamps = false;

    protected array $fillable = [
        'pru_pro_id',
        'pru_uni_id',
        'pru_ativo',
        'pru_excluido',
    ];

    /**
     * Marca o produto como disponível (1) ou esgotado (0) numa unidade.
     * Upsert idempotente.
     */
    public static function setStatus(int $proId, int $uniId, int $ativo): void
    {
        if ($proId <= 0 || $uniId <= 0) return;
        $ativo = $ativo ? 1 : 0;

        Db::beginTransaction();
        try {
            $row = Db::table('tb_produtos_rel_unidades')
                ->where('pru_pro_id', $proId)
                ->where('pru_uni_id', $uniId)
                ->first();

            if ($row) {
                Db::table('tb_produtos_rel_unidades')
                    ->where('pru_id', $row->pru_id)
                    ->update(['pru_ativo' => $ativo, 'pru_excluido' => 0]);
            } else {
                Db::table('tb_produtos_rel_unidades')->insert([
                    'pru_pro_id'   => $proId,
                    'pru_uni_id'   => $uniId,
                    'pru_ativo'    => $ativo,
                    'pru_excluido' => 0,
                    'pru_cadastro' => date('Y-m-d H:i:s'),
                ]);
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * IDs de produtos marcados como esgotados (pru_ativo=0) numa unidade.
     */
    public static function inativosByUnidade(int $uniId): array
    {
        if ($uniId <= 0) return [];
        return Db::table('tb_produtos_rel_unidades')
            ->where('pru_uni_id', $uniId)
            ->where('pru_ativo', 0)
            ->where('pru_excluido', 0)
            ->pluck('pru_pro_id')
            ->map(fn($v) => (int)$v)
            ->toArray();
    }
}
