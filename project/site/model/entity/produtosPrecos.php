<?php

namespace App\model\entity;

use Hyperf\DbConnection\Model\Model;

class produtosPrecos extends Model
{
    protected ?string $table = 'tb_produtos_precos';
    protected string $primaryKey = 'prp_id';
    public bool $timestamps = false;

    protected array $fillable = [
        'prp_pro_id',
        'prp_prg_id',
        'prp_pgo_id',
        'prp_uni_id',
        'prp_car_id',
        'prp_ppr_id',
        'prp_pgo_min',
        'prp_pgo_max',
        'prp_preco',
        'prp_embalagem',
        'prp_excluido',
    ];

    protected array $casts = [
        'prp_preco'     => 'decimal:2',
        'prp_embalagem' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    public function produto()
    {
        return $this->belongsTo(produtos::class, 'prp_pro_id', 'pro_id');
    }

    public function grade()
    {
        return $this->belongsTo(produtosGrades::class, 'prp_prg_id', 'prg_id');
    }

    public function opcao()
    {
        return $this->belongsTo(produtosGradesOpcoes::class, 'prp_pgo_id', 'pgo_id');
    }

    public function cardapio()
    {
        return $this->belongsTo(cardapio::class, 'prp_car_id', 'car_id');
    }
}
