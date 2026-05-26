<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class unidades extends Model
{
    protected ?string $table = 'tb_unidades';
    protected string $primaryKey = 'uni_id';
    public bool $timestamps = true;
    const CREATED_AT = 'uni_cadastro';
    const UPDATED_AT = null;

    public function contratante()
    {
        return $this->belongsTo(contratantes::class, 'uni_con_id', 'con_id');
    }

    public static function create($data, $con_id): array
    {
        Db::beginTransaction();
        try {
            $u = new self();
            $u->uni_con_id = (int)$con_id;
            $u->uni_codigo = $data['uni_codigo'] ?? substr(md5(uniqid((string)mt_rand(), true)), 0, 20);
            $u->uni_titulo = $data['uni_titulo'] ?? null;
            $u->uni_slug = $data['uni_slug'] ?? substr(md5(uniqid((string)mt_rand(), true)), 0, 20);
            $u->uni_cnpj = $data['uni_cnpj'] ?? null;
            $u->uni_razaosocial = $data['uni_razaosocial'] ?? null;
            $u->uni_marca = $data['uni_marca'] ?? '';
            $u->uni_siteinstitucional = $data['uni_siteinstitucional'] ?? null;
            $u->uni_email = $data['uni_email'] ?? null;
            $u->uni_valorminimo = isset($data['uni_valorminimo']) ? (float)$data['uni_valorminimo'] : null;
            $u->uni_tempodeespera = $data['uni_tempodeespera'] ?? null;
            $u->uni_pagseguro_email = $data['uni_pagseguro_email'] ?? null;
            $u->uni_pagseguro_token = $data['uni_pagseguro_token'] ?? null;
            $u->uni_pagseguro_token_sandbox = $data['uni_pagseguro_token_sandbox'] ?? null;
            $u->uni_orenda_token = $data['uni_orenda_token'] ?? null;
            $u->uni_orenda_id = $data['uni_orenda_id'] ?? null;
            $u->uni_telefone = $data['uni_telefone'] ?? null;
            $u->uni_cep = $data['uni_cep'] ?? null;
            $u->uni_logradouro = $data['uni_logradouro'] ?? null;
            $u->uni_endereco = $data['uni_endereco'] ?? null;
            $u->uni_numero = $data['uni_numero'] ?? null;
            $u->uni_bai_id = isset($data['uni_bai_id']) ? (int)$data['uni_bai_id'] : null;
            $u->uni_cid_id = isset($data['uni_cid_id']) ? (int)$data['uni_cid_id'] : null;
            $u->uni_est_id = isset($data['uni_est_id']) ? (int)$data['uni_est_id'] : null;
            $u->uni_complemento = $data['uni_complemento'] ?? null;
            $u->uni_referencia = $data['uni_referencia'] ?? null;
            $u->uni_facebook = $data['uni_facebook'] ?? null;
            $u->uni_instagram = $data['uni_instagram'] ?? null;
            $u->uni_googlemeunegocio = $data['uni_googlemeunegocio'] ?? null;
            $u->uni_sms = $data['uni_sms'] ?? '';
            $u->uni_ligacao = $data['uni_ligacao'] ?? '';
            $u->uni_pedidooffline = isset($data['uni_pedidooffline']) ? (int)$data['uni_pedidooffline'] : 0;
            $u->uni_online = isset($data['uni_online']) ? (int)$data['uni_online'] : 0;
            $u->uni_status_notificar = isset($data['uni_status_notificar']) ? (int)$data['uni_status_notificar'] : 0;
            $u->uni_status_notificar_sms = $data['uni_status_notificar_sms'] ?? null;
            $u->uni_catalogo = isset($data['uni_catalogo']) ? (int)$data['uni_catalogo'] : null;
            $u->uni_promocoes = isset($data['uni_promocoes']) ? (int)$data['uni_promocoes'] : 1;
            
            $u->save();
            Db::commit();
            return ['status' => 201, 'message' => 'Cadastrado com sucesso', 'data' => $u->toArray()];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao cadastrar', 'details' => $e->getMessage()];
        }
    }

    public static function edit($data, $id): array
    {
        Db::beginTransaction();
        try {
            $u = self::query()->find($id);
            if (! $u || (int)$u->uni_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Unidade não encontrada'];
            }

            // Atribuindo valores somente se foram enviados para o edit, caso contrário não altera.
            $fields = [
                'uni_codigo', 'uni_titulo', 'uni_cnpj', 'uni_razaosocial', 'uni_marca',
                'uni_siteinstitucional', 'uni_email', 'uni_valorminimo', 'uni_tempodeespera',
                'uni_pagseguro_email', 'uni_pagseguro_token', 'uni_pagseguro_token_sandbox',
                'uni_orenda_token', 'uni_orenda_id', 'uni_telefone', 'uni_cep', 'uni_logradouro',
                'uni_endereco', 'uni_numero', 'uni_bai_id', 'uni_cid_id', 'uni_est_id',
                'uni_complemento', 'uni_referencia', 'uni_facebook', 'uni_instagram',
                'uni_googlemeunegocio', 'uni_sms', 'uni_ligacao', 'uni_pedidooffline',
                'uni_online', 'uni_status_notificar', 'uni_status_notificar_sms',
                'uni_catalogo', 'uni_promocoes'
            ];

            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $u->$field = $data[$field];
                }
            }

            $u->save();
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
            $u = self::query()->find($id);
            if (! $u || (int)$u->uni_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Unidade não encontrada'];
            }
            $u->uni_ativo = (int) (! (int)$u->uni_ativo);
            $u->save();
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
            $u = self::query()->find($id);
            if (! $u || (int)$u->uni_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Unidade não encontrada'];
            }
            $u->uni_excluido = 1;
            $u->save();
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
                ->from('tb_unidades as u')
                ->leftJoin('tb_bairros as b', 'b.bai_id', '=', 'u.uni_bai_id')
                ->leftJoin('tb_cidades as c', 'c.cid_id', '=', 'u.uni_cid_id')
                ->leftJoin('tb_estados as e', 'e.est_id', '=', 'u.uni_est_id')
                ->where('u.uni_excluido', 0)
                ->where('u.uni_con_id', $con_id)
                ->orderBy('u.uni_id', 'desc')
                ->select(
                    'u.*',
                    'b.bai_titulo as bairro_nome',
                    'c.cid_titulo as cidade_nome',
                    'e.est_sigla as estado_sigla',
                )
                ->paginate(10, ['*'], 'page', $page);

            $rows = $p->items();
            if (count($rows) === 0) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            $data = array_map(fn($r) => is_array($r) ? $r : (method_exists($r, 'toArray') ? $r->toArray() : (array)$r), $rows);

            return [
                'pagination' => [
                    'current_page' => $p->currentPage(),
                    'first_page_url' => 1,
                    'from' => $p->firstItem(),
                    'last_page' => $p->lastPage(),
                    'last_page_url' => $p->lastPage(),
                    'next_page_url' => $p->nextPageUrl() ? $p->currentPage() + 1 : null,
                    'per_page' => $p->perPage(),
                    'prev_page_url' => $p->previousPageUrl() ? $p->currentPage() - 1 : null,
                    'to' => $p->lastItem(),
                    'total' => $p->total(),
                ],
                'status' => 200,
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    /**
     * Whitelist de campos editáveis pela aba "Configurações" do CMS.
     * Mantém separado de edit() (mais amplo) para evitar mass-assignment indesejado.
     */
    private const CONFIGURACOES_FIELDS = [
        'uni_titulo', 'uni_slug', 'uni_codigo', 'uni_cnpj', 'uni_razaosocial',
        'uni_email', 'uni_telefone', 'uni_whatsapp',
        'uni_cep', 'uni_logradouro', 'uni_endereco', 'uni_numero',
        'uni_complemento', 'uni_referencia',
        'uni_bai_id', 'uni_cid_id', 'uni_est_id',
        'uni_ativo',
    ];

    public static function editConfiguracoes(array $data, int $id, int $con_id): array
    {
        Db::beginTransaction();
        try {
            $u = self::query()
                ->where('uni_id', $id)
                ->where('uni_con_id', $con_id)
                ->where('uni_excluido', 0)
                ->first();

            if (! $u) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Unidade não encontrada'];
            }

            if (array_key_exists('uni_slug', $data)) {
                $slug = trim((string)$data['uni_slug']);
                if ($slug === '') {
                    Db::rollBack();
                    return ['status' => 400, 'message' => 'Slug é obrigatório'];
                }
                $exists = self::query()
                    ->where('uni_slug', $slug)
                    ->where('uni_con_id', $con_id)
                    ->where('uni_excluido', 0)
                    ->where('uni_id', '!=', $id)
                    ->exists();
                if ($exists) {
                    Db::rollBack();
                    return ['status' => 409, 'message' => 'Já existe outra unidade com este slug'];
                }
                $data['uni_slug'] = $slug;
            }

            foreach (self::CONFIGURACOES_FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    if (in_array($field, ['uni_bai_id', 'uni_cid_id', 'uni_est_id'], true)) {
                        $u->$field = $data[$field] === null || $data[$field] === '' ? null : (int)$data[$field];
                    } elseif ($field === 'uni_ativo') {
                        $u->$field = (int)((bool)$data[$field]);
                    } else {
                        $u->$field = $data[$field];
                    }
                }
            }

            $u->save();
            Db::commit();

            return [
                'status' => 200,
                'message' => 'Configurações atualizadas com sucesso',
                'data' => $u->toArray(),
            ];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao atualizar configurações', 'details' => $e->getMessage()];
        }
    }

    public static function search($id, $con_id = null): array
    {
        try {
            $q = self::query()
                ->where('uni_id', $id)
                ->where('uni_excluido', 0);
                
            if ($con_id !== null) {
                $q->where('uni_con_id', $con_id);
            }

            $row = $q->first();

            if (! $row) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            return ['status' => 200, 'data' => $row->toArray()];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function getFretePublic(string $slug, string $cepClient, ?float $lat = null, ?float $lng = null): array
    {
        try {
            if ($slug === '') {
                return ['status' => 400, 'message' => 'Slug é obrigatório.'];
            }

            $unit = self::query()
                ->where('uni_slug', $slug)
                ->where('uni_excluido', 0)
                ->first();

            if (! $unit) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            $frete = ['fre_preco' => 'indisponivel'];
            $cepClient = $cepClient !== '' ? preg_replace('/\D+/', '', $cepClient) : '';
            $cepClientNumeric = $cepClient !== '' ? (int) $cepClient : 0;

            $matched = \App\model\entity\fretes::lookupFrete(
                (int)$unit->uni_id,
                $cepClientNumeric,
                $lat,
                $lng,
                (float)($unit->uni_latitude ?? 0) ?: null,
                (float)($unit->uni_longitude ?? 0) ?: null
            );

            if ($matched) {
                $frete = [
                    'fre_id' => $matched['fre_id'] ?? null,
                    'fre_preco' => (float)($matched['fre_preco'] ?? 0),
                    'fre_tipo' => $matched['fre_tipo'] ?? 'bairro',
                    'fre_titulo' => $matched['fre_titulo'] ?? null,
                    'fre_tempo_estimado_min' => $matched['fre_tempo_estimado_min'] ?? null,
                ];
            }

            return [
                'status' => 200,
                'data' => [
                    'uni_id' => $unit->uni_id,
                    'uni_con_id' => $unit->uni_con_id,
                    'frete' => $frete,
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function getBySlugPublic(string $slug): array
    {
        try {
            $uni = self::query()
                ->where('uni_slug', $slug)
                ->where('uni_excluido', 0)
                ->where('uni_ativo', 1)
                ->first();

            if ($uni) {
                $data = $uni->toArray();
                // Remove dados sensíveis
                $sensitiveFields = [
                    'uni_pagseguro_email', 'uni_pagseguro_token', 'uni_pagseguro_token_sandbox',
                    'uni_orenda_token', 'uni_orenda_id',
                    'uni_sms', 'uni_ligacao', 'uni_status_notificar_sms',
                ];
                foreach ($sensitiveFields as $field) {
                    unset($data[$field]);
                }

                $uniId = (int)($data['uni_id'] ?? 0);

                // Horários de entrega (delivery) e de atendimento (cardápio digital)
                $horariosEntrega = Db::table('tb_horarios_entregas_rel_unidades')
                    ->where('heu_uni_id', $uniId)
                    ->where('heu_excluido', 0)
                    ->where('heu_ativo', 1)
                    ->orderBy('heu_dia', 'asc')
                    ->orderBy('heu_inicio', 'asc')
                    ->get(['heu_id', 'heu_dia', 'heu_inicio', 'heu_fim'])
                    ->map(fn($r) => (array)$r)
                    ->toArray();

                $horariosAtendimento = Db::table('tb_horarios')
                    ->where('hor_uni_id', $uniId)
                    ->where('hor_excluido', 0)
                    ->where('hor_ativo', 1)
                    ->orderBy('hor_dia', 'asc')
                    ->orderBy('hor_inicio', 'asc')
                    ->get(['hor_id', 'hor_dia', 'hor_inicio', 'hor_fim'])
                    ->map(fn($r) => (array)$r)
                    ->toArray();

                $data['horarios_entrega'] = $horariosEntrega;
                $data['horarios_atendimento'] = $horariosAtendimento;

                // Calcula disponibilidade com base no horário de entrega atual
                $todayWeekDay = (int)date('N');
                $turno = Db::table('tb_horarios_entregas_rel_unidades')
                    ->where('heu_inicio', '<=', Db::raw('TIME(NOW())'))
                    ->where('heu_fim', '>=', Db::raw('TIME(NOW())'))
                    ->where('heu_dia', $todayWeekDay)
                    ->where('heu_ativo', 1)
                    ->where('heu_excluido', 0)
                    ->where('heu_uni_id', $uniId)
                    ->first();
                $data['uni_is_disponivel'] = $turno !== null;

                return ['status' => 200, 'data' => $data];
            }

            return ['status' => 404, 'message' => 'Not found'];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
