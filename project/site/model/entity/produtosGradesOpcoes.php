<?php

namespace App\model\entity;

use Hyperf\DbConnection\Model\Model;

class produtosGradesOpcoes extends Model
{
    protected ?string $table = 'tb_produtos_grades_opcoes';
    protected string $primaryKey = 'pgo_id';
    public bool $timestamps = false;

    protected array $fillable = [
        'pgo_prg_id',
        'pgo_titulo',
        'pgo_con_id',
        'pgo_pro_id',
        'pgo_ativo',
        'pgo_excluido',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    public function grade()
    {
        return $this->belongsTo(produtosGrades::class, 'pgo_prg_id', 'prg_id');
    }
}
