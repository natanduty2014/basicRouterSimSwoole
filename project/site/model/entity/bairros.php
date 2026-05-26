<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class bairros extends Model
{
    protected ?string $table = 'tb_bairros';
    protected string $primaryKey = 'bai_id';
    public bool $timestamps = true;
    const CREATED_AT = 'bai_cadastro';
    const UPDATED_AT = null;

    protected array $fillable = [
        'bai_cid_id',
        'bai_titulo',
        'bai_zip_initial',
        'bai_zip_final',
        'bai_ativo',
        'bai_excluido',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    public function cidade()
    {
        return $this->belongsTo(cidades::class, 'bai_cid_id', 'cid_id');
    }

    // ─── Lookup ───────────────────────────────────────────────────────

    public static function listOptions(?int $cidId = null): array
    {
        try {
            $q = self::query()
                ->where('bai_excluido', 0)
                ->where('bai_ativo', 1)
                ->orderBy('bai_titulo', 'asc');

            if ($cidId !== null && $cidId > 0) {
                $q->where('bai_cid_id', $cidId);
            }

            $rows = $q->get(['bai_id', 'bai_cid_id', 'bai_titulo']);

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
            $record->bai_cid_id      = $data['bai_cid_id'] ?? null;
            $record->bai_titulo      = $data['bai_titulo'] ?? '';
            $record->bai_zip_initial = $data['bai_zip_initial'] ?? '';
            $record->bai_zip_final   = $data['bai_zip_final'] ?? '';
            $record->bai_ativo       = $data['bai_ativo'] ?? 1;
            $record->bai_excluido    = 0;
            $record->save();

            Db::commit();

            return ['status' => 201, 'message' => 'Bairro criado com sucesso', 'data' => $record];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function edit($id, $data): array
    {
        try {
            $record = self::query()
                ->where('bai_id', $id)
                ->where('bai_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Bairro não encontrado'];
            }

            if (isset($data['bai_cid_id']))      $record->bai_cid_id      = $data['bai_cid_id'];
            if (isset($data['bai_titulo']))      $record->bai_titulo      = $data['bai_titulo'];
            if (isset($data['bai_zip_initial'])) $record->bai_zip_initial = $data['bai_zip_initial'];
            if (isset($data['bai_zip_final']))   $record->bai_zip_final   = $data['bai_zip_final'];
            if (isset($data['bai_ativo']))       $record->bai_ativo       = $data['bai_ativo'];

            $record->save();

            return ['status' => 200, 'message' => 'Bairro atualizado com sucesso', 'data' => $record];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function deleteRecord($id): array
    {
        try {
            $record = self::query()
                ->where('bai_id', $id)
                ->where('bai_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Bairro não encontrado'];
            }

            $record->bai_excluido = 1;
            $record->bai_ativo = 0;
            $record->save();

            return ['status' => 200, 'message' => 'Bairro removido com sucesso'];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function getById($id): array
    {
        try {
            $record = self::query()
                ->where('bai_id', $id)
                ->where('bai_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Bairro não encontrado'];
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
                ->where('bai_excluido', 0)
                ->orderBy('bai_titulo', 'asc');

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

    public static function listByCidade($cidId, $page = 1, $perPage = 10): array
    {
        try {
            $query = self::query()
                ->where('bai_cid_id', $cidId)
                ->where('bai_excluido', 0)
                ->orderBy('bai_titulo', 'asc');

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
                ->where('bai_id', $id)
                ->where('bai_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Bairro não encontrado'];
            }

            $record->bai_ativo = $record->bai_ativo == 1 ? 0 : 1;
            $record->save();

            $status = $record->bai_ativo == 1 ? 'ativado' : 'desativado';
            return ['status' => 200, 'message' => "Bairro {\$status} com sucesso", 'data' => $record];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
