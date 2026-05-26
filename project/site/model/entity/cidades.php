<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class cidades extends Model
{
    protected ?string $table = 'tb_cidades';
    protected string $primaryKey = 'cid_id';
    public bool $timestamps = true;
    const CREATED_AT = 'cid_cadastro';
    const UPDATED_AT = null;

    protected array $fillable = [
        'cid_est_id',
        'cid_titulo',
        'cid_ativo',
        'cid_excluido',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    public function estado()
    {
        return $this->belongsTo(estados::class, 'cid_est_id', 'est_id');
    }

    public function bairros()
    {
        return $this->hasMany(bairros::class, 'bai_cid_id', 'cid_id');
    }

    // ─── Lookup ───────────────────────────────────────────────────────

    public static function listOptions(?int $estId = null): array
    {
        try {
            $q = self::query()
                ->where('cid_excluido', 0)
                ->where('cid_ativo', 1)
                ->orderBy('cid_titulo', 'asc');

            if ($estId !== null && $estId > 0) {
                $q->where('cid_est_id', $estId);
            }

            $rows = $q->get(['cid_id', 'cid_est_id', 'cid_titulo']);

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
            $record->cid_est_id   = $data['cid_est_id'] ?? null;
            $record->cid_titulo   = $data['cid_titulo'] ?? '';
            $record->cid_ativo    = $data['cid_ativo'] ?? 1;
            $record->cid_excluido = 0;
            $record->save();

            Db::commit();

            return ['status' => 201, 'message' => 'Cidade criada com sucesso', 'data' => $record];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function edit($id, $data): array
    {
        try {
            $record = self::query()
                ->where('cid_id', $id)
                ->where('cid_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Cidade não encontrada'];
            }

            if (isset($data['cid_est_id'])) $record->cid_est_id = $data['cid_est_id'];
            if (isset($data['cid_titulo'])) $record->cid_titulo = $data['cid_titulo'];
            if (isset($data['cid_ativo']))  $record->cid_ativo  = $data['cid_ativo'];

            $record->save();

            return ['status' => 200, 'message' => 'Cidade atualizada com sucesso', 'data' => $record];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function deleteRecord($id): array
    {
        try {
            $record = self::query()
                ->where('cid_id', $id)
                ->where('cid_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Cidade não encontrada'];
            }

            $record->cid_excluido = 1;
            $record->cid_ativo = 0;
            $record->save();

            return ['status' => 200, 'message' => 'Cidade removida com sucesso'];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function getById($id): array
    {
        try {
            $record = self::query()
                ->where('cid_id', $id)
                ->where('cid_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Cidade não encontrada'];
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
                ->where('cid_excluido', 0)
                ->orderBy('cid_titulo', 'asc');

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

    public static function listByEstado($estId, $page = 1, $perPage = 10): array
    {
        try {
            $query = self::query()
                ->where('cid_est_id', $estId)
                ->where('cid_excluido', 0)
                ->orderBy('cid_titulo', 'asc');

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
                ->where('cid_id', $id)
                ->where('cid_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Cidade não encontrada'];
            }

            $record->cid_ativo = $record->cid_ativo == 1 ? 0 : 1;
            $record->save();

            $status = $record->cid_ativo == 1 ? 'ativada' : 'desativada';
            return ['status' => 200, 'message' => "Cidade {\$status} com sucesso", 'data' => $record];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
