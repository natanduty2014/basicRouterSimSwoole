<?php

namespace App\model\entity;

use Hyperf\DbConnection\Model\Model;

class produtosCategorias extends Model
{
    protected ?string $table = 'tb_produtos_categorias';
    protected string $primaryKey = 'prc_id';
    public bool $timestamps = false;

    protected array $fillable = [
        'prc_con_id',
        'prc_titulo',
        'prc_img',
        'prc_categoria',
        'prc_ativo',
        'prc_excluido',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    // ─── Queries ──────────────────────────────────────────────────────

    public static function listByContratante($conId): array
    {
        try {
            $data = self::query()
                ->where('prc_con_id', $conId)
                ->where('prc_ativo', 1)
                ->where('prc_excluido', 0)
                ->orderBy('prc_ordem', 'asc')
                ->get()
                ->toArray();

            return ['status' => 200, 'data' => $data];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
