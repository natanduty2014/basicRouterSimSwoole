<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class cupom extends Model
{
    protected ?string $table = 'tb_cupons';
    protected string $primaryKey = 'cup_id';
    public bool $timestamps = true;
    const CREATED_AT = 'cup_cadastro';
    const UPDATED_AT = null;

    public static function create($data, $conId): array
    {
        Db::beginTransaction();
        try {
            $c = new self();
            $c->cup_con_id       = (int) $conId;
            $c->cup_titulo       = $data['cup_titulo'] ?? '';
            $c->cup_cupom        = strtoupper($data['cup_cupom'] ?? '');
            $c->cup_tipo         = isset($data['cup_tipo']) ? (int) $data['cup_tipo'] : 1;
            $c->cup_porcentagem  = isset($data['cup_porcentagem']) && $data['cup_porcentagem'] !== '' ? (int) $data['cup_porcentagem'] : null;
            $c->cup_valor        = isset($data['cup_valor']) && $data['cup_valor'] !== '' ? (float) $data['cup_valor'] : null;
            $c->cup_qtd          = isset($data['cup_qtd']) && $data['cup_qtd'] !== '' ? (int) $data['cup_qtd'] : null;
            $c->cup_unico        = isset($data['cup_unico']) ? (int) $data['cup_unico'] : 0;
            $c->cup_compra       = isset($data['cup_compra']) ? (int) $data['cup_compra'] : 0;
            $c->cup_valor_minimo = isset($data['cup_valor_minimo']) && $data['cup_valor_minimo'] !== '' ? (float) $data['cup_valor_minimo'] : null;
            $c->cup_inicio       = $data['cup_inicio'] ?? null;
            $c->cup_fim          = $data['cup_fim'] ?? null;
            $c->cup_ativo        = isset($data['cup_ativo']) ? (int) $data['cup_ativo'] : 1;

            $c->save();
            Db::commit();

            return ['status' => 201, 'message' => 'Cadastrado com sucesso', 'data' => ['cup_id' => $c->cup_id]];
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
            if (!$c || (int) $c->cup_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Cupom não encontrado'];
            }

            if (isset($data['cup_titulo']))       $c->cup_titulo       = $data['cup_titulo'];
            if (isset($data['cup_cupom']))         $c->cup_cupom        = strtoupper($data['cup_cupom']);
            if (isset($data['cup_tipo']))          $c->cup_tipo         = (int) $data['cup_tipo'];
            if (array_key_exists('cup_porcentagem', $data)) $c->cup_porcentagem = $data['cup_porcentagem'] !== '' && $data['cup_porcentagem'] !== null ? (int) $data['cup_porcentagem'] : null;
            if (array_key_exists('cup_valor', $data))       $c->cup_valor       = $data['cup_valor'] !== '' && $data['cup_valor'] !== null ? (float) $data['cup_valor'] : null;
            if (array_key_exists('cup_qtd', $data))         $c->cup_qtd         = $data['cup_qtd'] !== '' && $data['cup_qtd'] !== null ? (int) $data['cup_qtd'] : null;
            if (isset($data['cup_unico']))         $c->cup_unico        = (int) $data['cup_unico'];
            if (isset($data['cup_compra']))        $c->cup_compra       = (int) $data['cup_compra'];
            if (array_key_exists('cup_valor_minimo', $data)) $c->cup_valor_minimo = $data['cup_valor_minimo'] !== '' && $data['cup_valor_minimo'] !== null ? (float) $data['cup_valor_minimo'] : null;
            if (isset($data['cup_inicio']))        $c->cup_inicio       = $data['cup_inicio'];
            if (isset($data['cup_fim']))           $c->cup_fim          = $data['cup_fim'];
            if (isset($data['cup_ativo']))         $c->cup_ativo        = (int) $data['cup_ativo'];

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
            if (!$c || (int) $c->cup_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Cupom não encontrado'];
            }
            $c->cup_ativo = (int) (!(int) $c->cup_ativo);
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
            if (!$c || (int) $c->cup_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Cupom não encontrado'];
            }
            $c->cup_excluido = 1;
            $c->save();
            Db::commit();
            return ['status' => 200, 'message' => 'Deletado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao deletar'];
        }
    }

    public static function listAll($pag, $conId): array
    {
        $page = (int) ($pag ?? 1);

        try {
            $query = self::query()->where('cup_excluido', 0);

            if ($conId) {
                $query->where('cup_con_id', (int) $conId);
            }

            $p = $query->orderBy('cup_id', 'desc')->paginate(10, ['*'], 'page', $page);

            $rows = $p->items();
            if (count($rows) === 0) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            $ids = array_map(fn($r) => $r->cup_id, $rows);
            $usageCounts = Db::table('tb_pedidos')
                ->whereIn('ped_cup_id', $ids)
                ->whereNotNull('ped_cup_id')
                ->selectRaw('ped_cup_id, COUNT(*) as total_pedidos')
                ->groupBy('ped_cup_id')
                ->pluck('total_pedidos', 'ped_cup_id')
                ->all();

            $data = array_map(function ($r) use ($usageCounts) {
                $arr = is_array($r) ? $r : (method_exists($r, 'toArray') ? $r->toArray() : (array) $r);
                $arr['total_pedidos'] = (int) ($usageCounts[$arr['cup_id']] ?? 0);
                return $arr;
            }, $rows);

            return [
                'pagination' => [
                    'current_page'  => $p->currentPage(),
                    'first_page_url' => 1,
                    'from'          => $p->firstItem(),
                    'last_page'     => $p->lastPage(),
                    'last_page_url' => $p->lastPage(),
                    'next_page_url' => $p->nextPageUrl() ? $p->currentPage() + 1 : null,
                    'per_page'      => $p->perPage(),
                    'prev_page_url' => $p->previousPageUrl() ? $p->currentPage() - 1 : null,
                    'to'            => $p->lastItem(),
                    'total'         => $p->total(),
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
                ->where('cup_id', $id)
                ->where('cup_excluido', 0)
                ->first();

            if (!$row) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            return ['status' => 200, 'data' => $row->toArray()];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
