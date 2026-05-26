<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class cardapio extends Model
{
    protected ?string $table = 'tb_cardapios';
    protected string $primaryKey = 'car_id';
    public bool $timestamps = true;
    const CREATED_AT = 'car_cadastro';
    const UPDATED_AT = null;

    public function getCarDiaAttribute($value)
    {
        return $value ? explode(',', $value) : [];
    }

    public function setCarDiaAttribute($value)
    {
        $this->attributes['car_dia'] = is_array($value) ? implode(',', $value) : $value;
    }

    public function unidades()
    {
        return $this->hasMany(cardapioRelUnidades::class, 'cru_car_id', 'car_id');
    }

    public static function create($data): array
    {
        Db::beginTransaction();
        try {
            $c = new self();
            $c->car_con_id          = isset($data['car_con_id']) ? (int)$data['car_con_id'] : null;
            $c->car_titulo          = $data['car_titulo'] ?? '';
            $c->car_hora_abertura   = $data['car_hora_abertura'] ?? '00:00:00';
            $c->car_hora_fechamento = $data['car_hora_fechamento'] ?? '23:59:59';
            $c->car_dia             = is_array($data['car_dia'] ?? '') ? implode(',', $data['car_dia']) : ($data['car_dia'] ?? '');
            $c->car_ativo           = isset($data['car_ativo']) ? (int)$data['car_ativo'] : 1;

            $c->save();
            Db::commit();

            return ['status' => 201, 'message' => 'Cadastrado com sucesso', 'data' => ['car_id' => $c->car_id]];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao cadastrar: ' . $e->getMessage()];
        }
    }

    public static function edit($data, $id): array
    {
        Db::beginTransaction();
        try {
            $c = self::query()->find($id);
            if (! $c || (int)$c->car_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Cardápio não encontrado'];
            }

            $c->car_con_id          = isset($data['car_con_id']) ? (int)$data['car_con_id'] : $c->car_con_id;
            $c->car_titulo          = $data['car_titulo'] ?? $c->car_titulo;
            $c->car_hora_abertura   = $data['car_hora_abertura'] ?? $c->car_hora_abertura;
            $c->car_hora_fechamento = $data['car_hora_fechamento'] ?? $c->car_hora_fechamento;
            $c->car_dia             = isset($data['car_dia']) ? (is_array($data['car_dia']) ? implode(',', $data['car_dia']) : $data['car_dia']) : $c->car_dia;
            $c->car_ativo           = isset($data['car_ativo']) ? (int)$data['car_ativo'] : $c->car_ativo;

            $c->save();
            Db::commit();
            return ['status' => 200, 'message' => 'Editado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao editar'];
        }
    }

    public static function activeDisable($id): array
    {
        Db::beginTransaction();
        try {
            $c = self::query()->find($id);
            if (! $c || (int)$c->car_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Cardápio não encontrado'];
            }
            $c->car_ativo = (int) (! (int)$c->car_ativo);
            $c->save();
            Db::commit();
            return ['status' => 200, 'message' => 'Editado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao editar'];
        }
    }

    public static function deleted($id): array
    {
        Db::beginTransaction();
        try {
            $c = self::query()->find($id);
            if (! $c || (int)$c->car_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Cardápio não encontrado'];
            }
            $c->car_excluido = 1;
            $c->save();
            Db::commit();
            return ['status' => 200, 'message' => 'Deletado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 404, 'message' => 'Erro ao deletar'];
        }
    }

    public static function listAll($pag, $conID): array
    {
        $page = (int)($pag ?? 1);

        try {
            $query = self::query()->where('car_excluido', 0);

            if ($conID) {
                $query->where('car_con_id', (int)$conID);
            }

            $p = $query->orderBy('car_id', 'asc')->paginate(10, ['*'], 'page', $page);

            $rows = $p->items();
            if (count($rows) === 0) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            $data = array_map(fn($r) => is_array($r) ? $r : (method_exists($r, 'toArray') ? $r->toArray() : (array)$r), $rows);

            return [
                'pagination' => [
                    'current_page'   => $p->currentPage(),
                    'first_page_url' => 1,
                    'from'           => $p->firstItem(),
                    'last_page'      => $p->lastPage(),
                    'last_page_url'  => $p->lastPage(),
                    'next_page_url'  => $p->nextPageUrl() ? $p->currentPage() + 1 : null,
                    'per_page'       => $p->perPage(),
                    'prev_page_url'  => $p->previousPageUrl() ? $p->currentPage() - 1 : null,
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

    public static function search($id): array
    {
        try {
            $row = self::query()
                ->where('car_id', $id)
                ->where('car_excluido', 0)
                ->first();

            if (! $row) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            $data = $row->toArray();

            $data['unidade_ids'] = \Hyperf\DbConnection\Db::table('tb_cardapios_rel_unidades')
                ->where('cru_car_id', $id)
                ->where('cru_excluido', 0)
                ->pluck('cru_uni_id')
                ->map(fn($v) => (int) $v)
                ->values()
                ->all();

            return ['status' => 200, 'data' => $data];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function listByContratante($conId): array
    {
        try {
            $rows = self::query()
                ->where('car_con_id', (int)$conId)
                ->where('car_excluido', 0)
                ->orderBy('car_id', 'asc')
                ->get();

            if ($rows->isEmpty()) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            return ['status' => 200, 'data' => $rows->toArray()];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
