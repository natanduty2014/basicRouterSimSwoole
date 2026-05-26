<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class cardapioRelUnidades extends Model
{
    protected ?string $table = 'tb_cardapios_rel_unidades';
    protected string $primaryKey = 'cru_id';
    public bool $timestamps = true;
    const CREATED_AT = 'cru_cadastro';
    const UPDATED_AT = null;

    protected array $appends = ['cardapio_dia'];

    // ─── Relationships ────────────────────────────────────────────────

    public function cardapio()
    {
        return $this->belongsTo(cardapio::class, 'cru_car_id', 'car_id');
    }

    /**
     * Produtos vinculados ao cardápio desta relação, junto com seus dados.
     * Eager-load via: with('produtosRelCardapios.produto')
     */
    public function produtosRelCardapios()
    {
        return $this->hasMany(produtos_rel_cardapios::class, 'prr_car_id', 'cru_car_id')
            ->where('prr_ativo', 1)
            ->where('prr_excluido', 0);
    }

    public function getCardapioDiaAttribute()
    {
        // Pega o "car_dia" de dentro do objeto do cardapio. O Eloquent do cardapio.php já o formata para array.
        return $this->cardapio ? $this->cardapio->car_dia : [];
    }

    // ─── CRUD ─────────────────────────────────────────────────────────

    public static function create($data): array
    {
        Db::beginTransaction();
        try {
            // Evitar duplicata: mesma relação cardápio x unidade já existente e não deletada
            $exists = self::query()
                ->where('cru_car_id', (int)$data['cru_car_id'])
                ->where('cru_uni_id', (int)$data['cru_uni_id'])
                ->where('cru_excluido', 0)
                ->first();

            if ($exists) {
                Db::rollBack();
                return ['status' => 409, 'message' => 'Relação já cadastrada para este cardápio e unidade'];
            }

            $c = new self();
            $c->cru_car_id = (int)$data['cru_car_id'];
            $c->cru_uni_id = (int)$data['cru_uni_id'];

            $c->save();
            Db::commit();

            return ['status' => 201, 'message' => 'Cadastrado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao cadastrar: ' . $e->getMessage()];
        }
    }

    public static function activeDisable($id): array
    {
        Db::beginTransaction();
        try {
            $c = self::query()->find($id);
            if (! $c || (int)$c->cru_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Relação não encontrada'];
            }
            $c->cru_ativo = (int) (! (int)$c->cru_ativo);
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
            if (! $c || (int)$c->cru_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Relação não encontrada'];
            }
            $c->cru_excluido = 1;
            $c->save();
            Db::commit();
            return ['status' => 200, 'message' => 'Deletado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 404, 'message' => 'Erro ao deletar'];
        }
    }

    /**
     * Lista todas as unidades vinculadas a um cardápio
     */
    public static function listByCardapio($carId): array
    {
        try {
            $rows = self::query()
                ->where('cru_car_id', (int)$carId)
                ->where('cru_excluido', 0)
                ->orderBy('cru_id', 'asc')
                ->get();

            return ['status' => 200, 'data' => $rows->toArray()];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lista todos os cardápios vinculados a uma unidade
     */
    public static function listByUnidade($slug): array
    {
        try {
            $uniId = unidades::where('uni_slug', $slug)->value('uni_id');
            $rows = self::query()
                ->where('cru_uni_id', (int)$uniId)
                ->where('cru_excluido', 0)
                ->orderBy('cru_id', 'asc')
                ->get();

            if ($rows->isEmpty()) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            return ['status' => 200, 'data' => $rows->toArray()];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function search($id): array
    {
        try {
            $row = self::query()
                ->where('cru_id', $id)
                ->where('cru_excluido', 0)
                ->first();

            if (! $row) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            return ['status' => 200, 'data' => $row->toArray()];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lista os cardápios de uma unidade com os produtos e preços iniciais.
     *
     * Estrutura retornada por item de data[]:
     *   cru_id, cru_car_id, cru_uni_id, ...
     *   cardapio: { car_id, car_titulo, car_dia, ... }
     *   produtos_rel_cardapios: [
     *     {
     *       prr_id, prr_pro_id, prr_car_id, ...
     *       produto: { pro_id, pro_titulo, pro_descricao, pro_frase, ... }
     *       precos_unidade: [
     *         { prp_prg_id, preco_minimo, embalagem_minima }
     *       ]
     *       preco_inicial:        float  (soma dos mínimos por grade obrigatória)
     *       preco_com_embalagem:  float
     *     }
     *   ]
     */
    public static function listAll($pag, $slug): array
    {
        $page  = (int)($pag ?? 1);
       

        try {
            // 1. Carrega os cardápios da unidade com eager loading em cadeia:
            //    cardapio → produtosRelCardapios → produto → precos (filtrado por unidade)
            $uniId = unidades::where('uni_slug', $slug)->value('uni_id');

            // Cardápios ativos vinculados à unidade — usados para escopar os preços
            $carIds = self::query()
                ->where('cru_uni_id', $uniId)
                ->where('cru_excluido', 0)
                ->where('cru_ativo', 1)
                ->pluck('cru_car_id')
                ->toArray();

            // Produtos marcados como esgotados nesta unidade (atendente)
            $produtosEsgotados = produtosRelUnidades::inativosByUnidade($uniId);

            $p = self::query()
                ->where('cru_uni_id', $uniId)
                ->where('cru_excluido', 0)
                ->orderBy('cru_id', 'desc')
                ->with([
                    // Cardápio ativo e não excluído
                    'cardapio' => fn($q) => $q
                        ->where('car_ativo', 1)
                        ->where('car_excluido', 0),

                    // Rel produtos × cardápio, ativos (exclui esgotados da unidade)
                    'produtosRelCardapios.produto' => fn($q) => $q
                        ->where('pro_ativo', 1)
                        ->where('pro_excluido', 0)
                        ->when(!empty($produtosEsgotados), fn($qq) => $qq->whereNotIn('pro_id', $produtosEsgotados))
                        ->orderBy('pro_prioridade', 'desc')
                        ->orderBy('pro_titulo', 'asc'),

                    // Preços por cardápio (não mais por unidade), sem promoção,
                    // somente para grades obrigatórias com opções ativas.
                    // O filtro fino por cardápio específico é feito in-memory no loop abaixo.
                    'produtosRelCardapios.produto.precos' => fn($q) => $q
                        ->whereIn('prp_car_id', $carIds)
                        ->where('prp_ppr_id', 0)       // sem promoção
                        ->where('prp_excluido', 0)
                        ->whereHas('grade', fn($gq) => $gq
                            ->where('prg_obrigatoria', 1)
                            ->where('prg_ativo', 1)
                            ->where('prg_excluido', 0)
                        )
                        ->whereHas('opcao', fn($oq) => $oq
                            ->where('pgo_ativo', 1)
                            ->where('pgo_excluido', 0)
                        )
                        ->with(['grade', 'opcao']),
                        
                    // Carrega categorias do produto
                    'produtosRelCardapios.produto.categorias.categoria',
                    'produtosRelCardapios.produto.imagens' => fn($q) => $q->where('pri_capa', 1)

                ])
                ->paginate(10, ['*'], 'page', $page);

            $rows = $p->items();
            if (count($rows) === 0) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            // 2. Monta o array de saída calculando preco_inicial por produto
            $data = [];
            foreach ($rows as $cru) {
                $cruArr = $cru->toArray();

                // Ignora entradas sem cardápio ativo
                if (empty($cruArr['cardapio'])) {
                    $data[] = $cruArr;
                    continue;
                }

                // Para cada rel produto × cardápio, calcula o preço inicial
                $produtosFormatados = [];
                foreach ($cru->produtosRelCardapios as $prc) {
                    if (! $prc->produto) {
                        continue;
                    }

                    // Filtra preços pelo cardápio do prr atual (não confundir com
                    // preços de outros cardápios da mesma unidade carregados juntos)
                    $precosDoCardapio = $prc->produto->precos
                        ->where('prp_car_id', $prc->prr_car_id);

                    // Agrupa MIN(prp_preco) por grade obrigatória e soma
                    // (replicando getProdutoPrecoInicial do legado)
                    $precosAgrupados = $precosDoCardapio
                        ->groupBy('prp_prg_id')
                        ->map(fn($grupo) => [
                            'prp_prg_id'      => $grupo->first()->prp_prg_id,
                            'preco_minimo'    => (float)$grupo->min('prp_preco'),
                            'embalagem_minima'=> (float)$grupo->min('prp_embalagem'),
                        ])
                        ->values();

                    $somaPreco     = $precosAgrupados->sum('preco_minimo');
                    $somaEmbalagem = $precosAgrupados->sum('embalagem_minima');
                    $temPreco      = $precosAgrupados->isNotEmpty();

                    // Pega a primeira categoria, se houver
                    $categoria = $prc->produto->categorias->first()?->categoria;

                    $prcArr                    = $prc->toArray();
                    $prcArr['precos_unidade']  = $precosAgrupados->toArray();
                    $prcArr['preco_inicial']   = $temPreco ? $somaPreco : null;
                    $prcArr['preco_com_embalagem'] = $temPreco
                        ? $somaPreco + $somaEmbalagem
                        : null;
                        
                    $imgNome = null;
                    if (!empty($prc->produto->imagens) && $prc->produto->imagens->first()) {
                        $imgNome = $prc->produto->imagens->first()->pri_img;
                    } elseif (!empty($prc->produto->pro_pri_id)) {
                        // Fallback: produto aponta direto para um pri_id em tb_produtos_imagens
                        $imgRec = \App\model\entity\produtosImagens::query()
                            ->where('pri_id', $prc->produto->pro_pri_id)
                            ->where('pri_excluido', 0)
                            ->first();
                        if ($imgRec) {
                            $imgNome = $imgRec->pri_img;
                        }
                    }

                    $prcArr['produto']['imagem_url'] = $imgNome ? '/public/uploads/midias/produtos/' . $imgNome : null;

                    $prcArr['categoria'] = $categoria ? [
                        'id' => $categoria->prc_id,
                        'titulo' => $categoria->prc_titulo,
                        'ordem' => $categoria->prc_ordem,
                    ] : null;

                    $produtosFormatados[] = $prcArr;
                }

                $cruArr['produtos_rel_cardapios'] = $produtosFormatados;
                $data[] = $cruArr;
            }

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
}
