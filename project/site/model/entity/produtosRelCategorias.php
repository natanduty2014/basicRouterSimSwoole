<?php

namespace App\model\entity;

use Hyperf\DbConnection\Model\Model;

class produtosRelCategorias extends Model
{
    protected ?string $table = 'tb_produtos_rel_categorias';
    protected string $primaryKey = 'pra_id';
    public bool $timestamps = false;

    protected array $fillable = [
        'pra_pro_id',
        'pra_prc_id',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    public function categoria()
    {
        return $this->belongsTo(produtosCategorias::class, 'pra_prc_id', 'prc_id')
            ->where('prc_ativo', 1)
            ->where('prc_excluido', 0);
    }
}
