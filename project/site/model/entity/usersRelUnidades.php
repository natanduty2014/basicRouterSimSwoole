<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

/**
 * Pivot N:M entre usuários e unidades.
 * Define quais unidades o usuário pode acessar quando seu role NÃO é
 * "Administrador do Contratante" (admin ignora a pivot e acessa todas).
 */
class usersRelUnidades extends Model
{
    protected ?string $table = 'tb_users_rel_unidades';
    protected string $primaryKey = 'uru_id';
    public bool $timestamps = false;

    protected array $fillable = [
        'uru_use_id',
        'uru_uni_id',
        'uru_ativo',
        'uru_excluido',
    ];

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * Retorna array de uni_id ativos para um usuário.
     */
    public static function listUniIdsByUser(int $useId): array
    {
        if ($useId <= 0) return [];
        return Db::table('tb_users_rel_unidades')
            ->where('uru_use_id', $useId)
            ->where('uru_ativo', 1)
            ->where('uru_excluido', 0)
            ->pluck('uru_uni_id')
            ->map(fn($v) => (int)$v)
            ->toArray();
    }

    /**
     * Substitui o conjunto de unidades do usuário pelas informadas.
     * Soft-deleta as removidas e (re)insere as novas. Idempotente.
     */
    public static function syncForUser(int $useId, array $uniIds): void
    {
        if ($useId <= 0) return;
        $uniIds = array_values(array_unique(array_map('intval', $uniIds)));

        Db::beginTransaction();
        try {
            // 1) Soft-delete tudo que não está na lista nova
            Db::table('tb_users_rel_unidades')
                ->where('uru_use_id', $useId)
                ->where('uru_excluido', 0)
                ->when(!empty($uniIds), fn($q) => $q->whereNotIn('uru_uni_id', $uniIds))
                ->update(['uru_excluido' => 1, 'uru_ativo' => 0]);

            // 2) Para cada uni desejado, reativa ou cria
            foreach ($uniIds as $uniId) {
                $existing = Db::table('tb_users_rel_unidades')
                    ->where('uru_use_id', $useId)
                    ->where('uru_uni_id', $uniId)
                    ->first();
                if ($existing) {
                    Db::table('tb_users_rel_unidades')
                        ->where('uru_id', $existing->uru_id)
                        ->update(['uru_excluido' => 0, 'uru_ativo' => 1]);
                } else {
                    Db::table('tb_users_rel_unidades')->insert([
                        'uru_use_id'   => $useId,
                        'uru_uni_id'   => $uniId,
                        'uru_ativo'    => 1,
                        'uru_excluido' => 0,
                        'uru_cadastro' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }
}
