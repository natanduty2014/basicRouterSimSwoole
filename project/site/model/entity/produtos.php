<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class produtos extends Model
{
    protected ?string $table = 'tb_produtos';
    protected string $primaryKey = 'pro_id';
    public bool $timestamps = true;
    const CREATED_AT = 'pro_cadastro';
    const UPDATED_AT = null;

    protected array $fillable = [
        'pro_con_id',
        'pro_codigo',
        'pro_titulo',
        'pro_pri_id',
        'pro_prioridade',
        'pro_descricao',
        'pro_especificacao',
        'pro_peso',
        'pro_altura',
        'pro_largura',
        'pro_comprimento',
        'pro_pem_id',
        'pro_prm_id',
        'pro_max_ingredientes',
        'pro_max_extras',
        'pro_aceitarobservacoes',
        'pro_frase',
        'pro_ativo',
        'pro_excluido',
    ];

    protected array $casts = [
        'pro_peso'        => 'decimal:2',
        'pro_altura'      => 'decimal:2',
        'pro_largura'     => 'decimal:2',
        'pro_comprimento' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    public function contratante()
    {
        return $this->belongsTo(contratantes::class, 'pro_con_id', 'con_id');
    }

    public function cardapios()
    {
        return $this->hasMany(produtos_rel_cardapios::class, 'prr_pro_id', 'pro_id');
    }

    /**
     * Preços do produto por unidade.
     * Para o preço inicial (sem promoção), filtre: prp_ppr_id = 0
     */
    public function precos()
    {
        return $this->hasMany(produtosPrecos::class, 'prp_pro_id', 'pro_id')
            ->where('prp_excluido', 0);
    }

    public function categorias()
    {
        return $this->hasMany(produtosRelCategorias::class, 'pra_pro_id', 'pro_id');
    }

    public function imagens()
    {
        return $this->hasMany(produtosImagens::class, 'pri_pro_id', 'pro_id')
            ->where('pri_excluido', 0);
    }

    // ─── CRUD ─────────────────────────────────────────────────────────

    public static function createRecord($data): array
    {
        try {
            Db::beginTransaction();

            $record = new self();
            $record->pro_con_id             = $data['pro_con_id'] ?? null;
            $record->pro_codigo             = $data['pro_codigo'] ?? null;
            $record->pro_titulo             = $data['pro_titulo'] ?? '';
            $record->pro_pri_id             = $data['pro_pri_id'] ?? null;
            $record->pro_prioridade         = $data['pro_prioridade'] ?? 0;
            $record->pro_descricao          = $data['pro_descricao'] ?? '';
            $record->pro_especificacao      = $data['pro_especificacao'] ?? '';
            $record->pro_peso               = $data['pro_peso'] ?? 0;
            $record->pro_altura             = $data['pro_altura'] ?? 0;
            $record->pro_largura            = $data['pro_largura'] ?? 0;
            $record->pro_comprimento        = $data['pro_comprimento'] ?? 0;
            $record->pro_pem_id             = $data['pro_pem_id'] ?? 0;
            $record->pro_prm_id             = $data['pro_prm_id'] ?? 0;
            $record->pro_max_ingredientes   = $data['pro_max_ingredientes'] ?? 0;
            $record->pro_max_extras         = $data['pro_max_extras'] ?? 0;
            $record->pro_aceitarobservacoes = $data['pro_aceitarobservacoes'] ?? 1;
            $record->pro_frase              = $data['pro_frase'] ?? null;
            $record->pro_ativo              = $data['pro_ativo'] ?? 1;
            $record->pro_excluido           = 0;
            $record->save();

            Db::commit();

            return ['status' => 201, 'message' => 'Produto criado com sucesso', 'data' => $record];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function edit($id, $data): array
    {
        try {
            $record = self::query()
                ->where('pro_id', $id)
                ->where('pro_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Produto não encontrado'];
            }

            if (isset($data['pro_con_id']))             $record->pro_con_id             = $data['pro_con_id'];
            if (isset($data['pro_codigo']))             $record->pro_codigo             = $data['pro_codigo'];
            if (isset($data['pro_titulo']))             $record->pro_titulo             = $data['pro_titulo'];
            if (isset($data['pro_pri_id']))             $record->pro_pri_id             = $data['pro_pri_id'];
            if (isset($data['pro_prioridade']))         $record->pro_prioridade         = $data['pro_prioridade'];
            if (isset($data['pro_descricao']))          $record->pro_descricao          = $data['pro_descricao'];
            if (isset($data['pro_especificacao']))      $record->pro_especificacao      = $data['pro_especificacao'];
            if (isset($data['pro_peso']))               $record->pro_peso               = $data['pro_peso'];
            if (isset($data['pro_altura']))             $record->pro_altura             = $data['pro_altura'];
            if (isset($data['pro_largura']))            $record->pro_largura            = $data['pro_largura'];
            if (isset($data['pro_comprimento']))        $record->pro_comprimento        = $data['pro_comprimento'];
            if (isset($data['pro_pem_id']))             $record->pro_pem_id             = $data['pro_pem_id'];
            if (isset($data['pro_prm_id']))             $record->pro_prm_id             = $data['pro_prm_id'];
            if (isset($data['pro_max_ingredientes']))   $record->pro_max_ingredientes   = $data['pro_max_ingredientes'];
            if (isset($data['pro_max_extras']))         $record->pro_max_extras         = $data['pro_max_extras'];
            if (isset($data['pro_aceitarobservacoes'])) $record->pro_aceitarobservacoes = $data['pro_aceitarobservacoes'];
            if (isset($data['pro_frase']))              $record->pro_frase              = $data['pro_frase'];
            if (isset($data['pro_ativo']))              $record->pro_ativo              = $data['pro_ativo'];

            $record->save();

            return ['status' => 200, 'message' => 'Produto atualizado com sucesso', 'data' => $record];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function deleteRecord($id): array
    {
        try {
            $record = self::query()
                ->where('pro_id', $id)
                ->where('pro_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Produto não encontrado'];
            }

            $record->pro_excluido = 1;
            $record->pro_ativo = 0;
            $record->save();

            return ['status' => 200, 'message' => 'Produto removido com sucesso'];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function getById($id): array
    {
        try {
            $record = self::query()
                ->where('pro_id', $id)
                ->where('pro_excluido', 0)
                ->with(['contratante', 'cardapios', 'categorias.categoria', 'imagens'])
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Produto não encontrado'];
            }

            $data = $record->toArray();

            // Load grades with their opcoes
            $grades = Db::table('tb_produtos_rel_grades as rel')
                ->join('tb_produtos_grades as prg', 'prg.prg_id', '=', 'rel.prr_prg_id')
                ->where('rel.prr_pro_id', $id)
                ->where('rel.prr_excluido', 0)
                ->where('prg.prg_ativo', 1)
                ->where('prg.prg_excluido', 0)
                ->select('prg.prg_id', 'prg.prg_titulo', 'prg.prg_obrigatoria')
                ->get();

            $gradeIds = $grades->pluck('prg_id')->all();

            $opcoes = count($gradeIds) > 0
                ? Db::table('tb_produtos_grades_opcoes')
                    ->whereIn('pgo_prg_id', $gradeIds)
                    ->where('pgo_ativo', 1)
                    ->where('pgo_excluido', 0)
                    ->select('pgo_id', 'pgo_titulo', 'pgo_prg_id')
                    ->get()
                    ->groupBy('pgo_prg_id')
                : collect([]);

            $data['grades'] = $grades->map(function ($grade) use ($opcoes) {
                $g = (array) $grade;
                $g['opcoes'] = isset($opcoes[$grade->prg_id])
                    ? $opcoes[$grade->prg_id]->values()->map(fn($o) => (array) $o)->all()
                    : [];
                return $g;
            })->all();

            return ['status' => 200, 'data' => $data];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function listAll($page = 1, $perPage = 10): array
    {
        try {
            $query = self::query()
                ->where('pro_excluido', 0)
                ->with(['imagens' => fn($q) => $q->where('pri_capa', 1), 'categorias.categoria'])
                ->orderBy('pro_prioridade', 'desc')
                ->orderBy('pro_titulo', 'asc');

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

    public static function searchByTitle($query, $page = 1, $perPage = 10, $conId = null): array
    {
        try {
            $q = self::query()
                ->where('pro_excluido', 0)
                ->where('pro_titulo', 'LIKE', "%{$query}%")
                ->with(['imagens' => fn($qr) => $qr->where('pri_capa', 1), 'categorias.categoria'])
                ->orderBy('pro_titulo', 'asc');

            if ($conId) {
                $q->where('pro_con_id', $conId);
            }

            $p = $q->paginate($perPage, ['*'], 'page', $page);
            $rows = $p->items();
            $data = array_map(fn($r) => is_array($r) ? $r : (method_exists($r, 'toArray') ? $r->toArray() : (array)$r), $rows);

            return [
                'pagination' => [
                    'current_page'   => $p->currentPage(),
                    'total'          => $p->total(),
                ],
                'status' => 200,
                'data'   => $data,
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function listByContratante($conId, $page = 1, $perPage = 10): array
    {
        try {
            $query = self::query()
                ->where('pro_con_id', $conId)
                ->where('pro_excluido', 0)
                ->orderBy('pro_prioridade', 'desc')
                ->orderBy('pro_titulo', 'asc');

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
                ->where('pro_id', $id)
                ->where('pro_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Produto não encontrado'];
            }

            // pro_ativo: 0 = Não, 1 = Sim, 2 = Indisponível
            $record->pro_ativo = $record->pro_ativo == 1 ? 0 : 1;
            $record->save();

            $status = $record->pro_ativo == 1 ? 'ativado' : 'desativado';
            return ['status' => 200, 'message' => "Produto {$status} com sucesso", 'data' => $record];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
