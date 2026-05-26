<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class contratantesUrl extends Model
{
    protected ?string $table = 'tb_contratantes_urls';
    protected string $primaryKey = 'cou_id';
    public bool $timestamps = true;
    const CREATED_AT = 'cou_cadastro';
    const UPDATED_AT = 'cou_atualizacao';

    public function contratante()
    {
        return $this->belongsTo(contratantes::class, 'cou_con_id', 'con_id');
    }

    public static function create($data, $con_id): array
    {
        Db::beginTransaction();
        try {
            if (self::query()->where('cou_url', $data['cou_url'] ?? '')->where('cou_excluido', 0)->exists()) {
                Db::rollBack();
                return ['status' => 400, 'message' => 'url_already_exists'];
            }

            $c = new self();
            $c->cou_con_id = (int) $con_id;
            $c->cou_url    = $data['cou_url'] ?? '';
            $c->cou_https  = isset($data['cou_https']) ? (int) $data['cou_https'] : 0;
            $c->cou_cardapio = isset($data['cou_cardapio']) ? (int) $data['cou_cardapio'] : 0;
            $c->save();

            Db::commit();
            return ['status' => 201, 'message' => 'Cadastrado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao cadastrar'];
        }
    }

    public static function edit($data, $id): array
    {
        Db::beginTransaction();
        try {
            $c = self::query()->find($id);
            if (! $c || (int)$c->cou_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'URL não encontrada'];
            }

            $c->cou_url      = $data['cou_url'] ?? $c->cou_url;
            $c->cou_https    = isset($data['cou_https']) ? (int)$data['cou_https'] : $c->cou_https;
            $c->cou_cardapio = isset($data['cou_cardapio']) ? (int)$data['cou_cardapio'] : $c->cou_cardapio;

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
            if (! $c || (int)$c->cou_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'URL não encontrada'];
            }
            $c->cou_ativo = (int) (! (int)$c->cou_ativo);
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
            if (! $c || (int)$c->cou_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'URL não encontrada'];
            }
            $c->cou_excluido = 1;
            $c->save();
            Db::commit();
            return ['status' => 200, 'message' => 'Deletado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 404, 'message' => 'Erro ao deletar'];
        }
    }

    public static function listAll($pag, $con_id): array
    {
        $page = (int)($pag ?? 1);

        try {
            $p = self::query()
                ->where('cou_excluido', 0)
                ->where('cou_con_id', $con_id)
                ->orderBy('cou_id', 'asc')
                ->paginate(10, ['*'], 'page', $page);

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
                ->where('cou_id', $id)
                ->where('cou_excluido', 0)
                ->first();

            if (! $row) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            return ['status' => 200, 'data' => $row->toArray()];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
