<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class estados extends Model
{
    protected ?string $table = 'tb_estados';
    protected string $primaryKey = 'est_id';
    public bool $timestamps = true;
    const CREATED_AT = 'est_cadastro';
    const UPDATED_AT = 'est_cadastro'; // O banco utiliza o mesmo campo para ON UPDATE

    protected array $fillable = [
        'est_titulo',
        'est_sigla',
        'est_ativo',
        'est_excluido',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    public function cidades()
    {
        return $this->hasMany(cidades::class, 'cid_est_id', 'est_id');
    }

    // ─── Lookup ───────────────────────────────────────────────────────

    public static function listOptions(): array
    {
        try {
            $rows = self::query()
                ->where('est_excluido', 0)
                ->where('est_ativo', 1)
                ->orderBy('est_titulo', 'asc')
                ->get(['est_id', 'est_titulo', 'est_sigla']);

            return [
                'status' => 200,
                'data' => $rows->toArray(),
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    // ─── CRUD ─────────────────────────────────────────────────────────

    public static function createRecord($data): array
    {
        try {
            Db::beginTransaction();

            $record = new self();
            $record->est_titulo   = $data['est_titulo'] ?? '';
            $record->est_sigla    = $data['est_sigla'] ?? '';
            $record->est_ativo    = $data['est_ativo'] ?? 1;
            $record->est_excluido = 0;
            $record->save();

            Db::commit();

            return ['status' => 201, 'message' => 'Estado criado com sucesso', 'data' => $record];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function edit($id, $data): array
    {
        try {
            $record = self::query()
                ->where('est_id', $id)
                ->where('est_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Estado não encontrado'];
            }

            if (isset($data['est_titulo'])) $record->est_titulo = $data['est_titulo'];
            if (isset($data['est_sigla']))  $record->est_sigla  = $data['est_sigla'];
            if (isset($data['est_ativo']))  $record->est_ativo  = $data['est_ativo'];

            $record->save();

            return ['status' => 200, 'message' => 'Estado atualizado com sucesso', 'data' => $record];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function deleteRecord($id): array
    {
        try {
            $record = self::query()
                ->where('est_id', $id)
                ->where('est_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Estado não encontrado'];
            }

            $record->est_excluido = 1;
            $record->est_ativo = 0;
            $record->save();

            return ['status' => 200, 'message' => 'Estado removido com sucesso'];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function getById($id): array
    {
        try {
            $record = self::query()
                ->where('est_id', $id)
                ->where('est_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Estado não encontrado'];
            }

            return ['status' => 200, 'data' => $record];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function listAll($page = 1, $perPage = 10): array
    {
        try {
            $query = self::query()
                ->where('est_excluido', 0)
                ->orderBy('est_titulo', 'asc');

            $p = $query->paginate($perPage, ['*'], 'page', $page);
            $rows = $p->items();
            $data = array_map(fn($r) => is_array($r) ? $r : (method_exists($r, 'toArray') ? $r->toArray() : (array)$r), $rows);

            return [
                'pagination' => [
                    'current_page'   => $p->currentPage(),
                    'first_page_url' => 1,
                    'from'           => $p->firstItem(),
                    'last_page'      => $p->lastPage(),
                    'last_page_url'  => $p->lastPage(),
                    'next_page_url'  => $p->currentPage() < $p->lastPage() ? $p->currentPage() + 1 : null,
                    'per_page'       => $p->perPage(),
                    'prev_page_url'  => $p->currentPage() > 1 ? $p->currentPage() - 1 : null,
                    'to'             => $p->lastItem(),
                    'total'          => $p->total(),
                ],
                'status' => 200,
                'data'   => $data,
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function activeDisable($id): array
    {
        try {
            $record = self::query()
                ->where('est_id', $id)
                ->where('est_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Estado não encontrado'];
            }

            $record->est_ativo = $record->est_ativo == 1 ? 0 : 1;
            $record->save();

            $status = $record->est_ativo == 1 ? 'ativado' : 'desativado';
            return ['status' => 200, 'message' => "Estado {\$status} com sucesso", 'data' => $record];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
