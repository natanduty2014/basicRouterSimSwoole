<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class produtos_rel_cardapios extends Model
{
    protected ?string $table = 'tb_produtos_rel_cardapios';
    protected string $primaryKey = 'prr_id';
    public bool $timestamps = true;
    const CREATED_AT = 'prr_cadastro';
    const UPDATED_AT = null;

    protected array $fillable = [
        'prr_pro_id',
        'prr_car_id',
        'prr_ativo',
        'prr_excluido',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    public function produto()
    {
        return $this->belongsTo(produtos::class, 'prr_pro_id', 'pro_id')
            ->where('pro_ativo', 1)
            ->where('pro_excluido', 0);
    }

    public function cardapio()
    {
        return $this->belongsTo(cardapio::class, 'prr_car_id', 'car_id');
    }

    /**
     * Preços do produto para um cardápio específico (sem promoção).
     * Usa o model produtosPrecos já com as constraints de grade e opção.
     */
    public function precosCardapio(int $carId)
    {
        return produtosPrecos::where('prp_pro_id', $this->prr_pro_id)
            ->where('prp_car_id', $carId)
            ->where('prp_ppr_id', 0)      // sem promoção
            ->where('prp_excluido', 0)
            ->whereHas('grade', fn($q) => $q
                ->where('prg_obrigatoria', 1)
                ->where('prg_ativo', 1)
                ->where('prg_excluido', 0)
            )
            ->whereHas('opcao', fn($q) => $q
                ->where('pgo_ativo', 1)
                ->where('pgo_excluido', 0)
            );
    }

    // ─── CRUD ─────────────────────────────────────────────────────────

    public static function createRecord($data): array
    {
        try {
            Db::beginTransaction();

            // Verifica duplicidade (mesmo produto + cardápio ativo)
            $exists = self::query()
                ->where('prr_pro_id', $data['prr_pro_id'] ?? null)
                ->where('prr_car_id', $data['prr_car_id'] ?? null)
                ->where('prr_excluido', 0)
                ->first();

            if ($exists) {
                Db::rollBack();
                return ['status' => 400, 'message' => 'Este produto já está vinculado a este cardápio.'];
            }

            $record = new self();
            $record->prr_pro_id   = $data['prr_pro_id'] ?? 0;
            $record->prr_car_id   = $data['prr_car_id'] ?? 0;
            $record->prr_ativo    = $data['prr_ativo'] ?? 1;
            $record->prr_excluido = 0;
            $record->save();

            Db::commit();

            return ['status' => 201, 'message' => 'Produto vinculado ao cardápio com sucesso', 'data' => $record];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function edit($id, $data): array
    {
        try {
            $record = self::query()
                ->where('prr_id', $id)
                ->where('prr_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Registro não encontrado'];
            }

            if (isset($data['prr_pro_id']))   $record->prr_pro_id   = $data['prr_pro_id'];
            if (isset($data['prr_car_id']))   $record->prr_car_id   = $data['prr_car_id'];
            if (isset($data['prr_ativo']))    $record->prr_ativo    = $data['prr_ativo'];

            $record->save();

            return ['status' => 200, 'message' => 'Registro atualizado com sucesso', 'data' => $record];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function deleteRecord($id): array
    {
        try {
            $record = self::query()
                ->where('prr_id', $id)
                ->where('prr_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Registro não encontrado'];
            }

            $record->prr_excluido = 1;
            $record->prr_ativo = 0;
            $record->save();

            return ['status' => 200, 'message' => 'Registro removido com sucesso'];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function getById($id): array
    {
        try {
            $record = self::query()
                ->where('prr_id', $id)
                ->where('prr_excluido', 0)
                ->with(['produto', 'cardapio'])
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Registro não encontrado'];
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
                ->where('prr_excluido', 0)
                ->with(['produto', 'cardapio'])
                ->orderBy('prr_id', 'desc');

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

    public static function listByCardapio($carId): array
    {
        try {
            $records = self::query()
                ->where('prr_car_id', $carId)
                ->where('prr_excluido', 0)
                ->where('prr_ativo', 1)
                ->with(['produto'])
                ->orderBy('prr_id', 'asc')
                ->get();

            return ['status' => 200, 'data' => $records->toArray()];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function listByProduto($proId): array
    {
        try {
            $records = self::query()
                ->where('prr_pro_id', $proId)
                ->where('prr_excluido', 0)
                ->with(['cardapio'])
                ->orderBy('prr_id', 'asc')
                ->get();

            return ['status' => 200, 'data' => $records->toArray()];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function activeDisable($id): array
    {
        try {
            $record = self::query()
                ->where('prr_id', $id)
                ->where('prr_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Registro não encontrado'];
            }

            $record->prr_ativo = $record->prr_ativo == 1 ? 0 : 1;
            $record->save();

            $status = $record->prr_ativo == 1 ? 'ativado' : 'desativado';
            return ['status' => 200, 'message' => "Registro {$status} com sucesso", 'data' => $record];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
