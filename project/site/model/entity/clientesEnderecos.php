<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class clientesEnderecos extends Model
{
    protected ?string $table = 'tb_clientes_enderecos';
    protected string $primaryKey = 'cle_id';
    public bool $timestamps = true;
    const CREATED_AT = 'cle_cadastro';
    const UPDATED_AT = null;

    protected array $fillable = [
        'cle_cli_id',
        'cle_titulo',
        'cle_cep',
        'cle_logradouro',
        'cle_bairro',
        'cle_cidade',
        'cle_estado',
        'cle_numero',
        'cle_bai_id',
        'cle_cid_id',
        'cle_est_id',
        'cle_complemento',
        'cle_pontodereferencia',
        'cle_padrao',
        'cle_ativo',
        'cle_excluido'
    ];

    public function bairro()
    {
        return $this->belongsTo(bairros::class, 'cle_bai_id', 'bai_id');
    }

    public function cidade()
    {
        return $this->belongsTo(cidades::class, 'cle_cid_id', 'cid_id');
    }

    public static function listByClient($cli_id): array
    {
        try {
            $rows = self::query()
                ->where('cle_cli_id', $cli_id)
                ->where('cle_excluido', 0)
                ->with(['bairro', 'cidade'])
                ->orderBy('cle_padrao', 'desc')
                ->orderBy('cle_id', 'desc')
                ->get();

            if ($rows->isEmpty()) {
                return ['status' => 404, 'message' => 'Nenhum endereço encontrado'];
            }

            return ['status' => 200, 'data' => $rows->toArray()];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function create($data, $cli_id): array
    {
        Db::beginTransaction();
        try {
            // Verifica se já existe endereço com o mesmo nome para este cliente
            $titulo = trim($data['cle_titulo'] ?? '');
            if (!empty($titulo)) {
                $exists = self::query()
                    ->where('cle_cli_id', $cli_id)
                    ->where('cle_titulo', $titulo)
                    ->where('cle_excluido', 0)
                    ->exists();
                if ($exists) {
                    Db::rollBack();
                    return ['status' => 409, 'message' => 'Já existe um endereço cadastrado com este nome.'];
                }
            }

            // Se for o primeiro, ou definido como padrão, tira o padrão dos outros
            if (!empty($data['cle_padrao']) && $data['cle_padrao'] == 1) {
                self::query()->where('cle_cli_id', $cli_id)->update(['cle_padrao' => 0]);
            } else {
                // se não tem endereço nenhum, cria como padrão forçado
                $count = self::query()->where('cle_cli_id', $cli_id)->where('cle_excluido', 0)->count();
                if ($count === 0) {
                    $data['cle_padrao'] = 1;
                }
            }

            $c = new self();
            $c->cle_cli_id     = (int)$cli_id;
            $c->cle_titulo     = $data['cle_titulo'] ?? '';
            $c->cle_cep        = str_replace('-', '', $data['cle_cep'] ?? '');
            $c->cle_logradouro = $data['cle_logradouro'] ?? '';
            $c->cle_bairro     = $data['cle_bairro'] ?? '';
            $c->cle_cidade     = $data['cle_cidade'] ?? '';
            $c->cle_estado     = $data['cle_estado'] ?? '';
            $c->cle_numero     = $data['cle_numero'] ?? '';
            $c->cle_bai_id     = isset($data['cle_bai_id']) ? (int)$data['cle_bai_id'] : null;
            $c->cle_cid_id     = isset($data['cle_cid_id']) ? (int)$data['cle_cid_id'] : null;
            $c->cle_est_id     = isset($data['cle_est_id']) ? (int)$data['cle_est_id'] : null;
            $c->cle_complemento= $data['cle_complemento'] ?? '';
            $c->cle_pontodereferencia = $data['cle_pontodereferencia'] ?? '';
            $c->cle_padrao     = isset($data['cle_padrao']) ? (int)$data['cle_padrao'] : 0;
            $c->cle_ativo      = 1;
            $c->cle_excluido   = 0;

            $c->save();
            Db::commit();

            return ['status' => 201, 'message' => 'Endereço cadastrado com sucesso', 'data' => $c->toArray()];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao cadastrar: ' . $e->getMessage()];
        }
    }

    public static function edit($data, $id, $cli_id): array
    {
        Db::beginTransaction();
        try {
            $c = self::query()->where('cle_id', $id)->where('cle_cli_id', $cli_id)->first();
            if (! $c || (int)$c->cle_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Endereço não encontrado'];
            }

            // Verifica se já existe outro endereço com o mesmo nome (excluindo este)
            $titulo = trim($data['cle_titulo'] ?? $c->cle_titulo);
            if (!empty($titulo)) {
                $exists = self::query()
                    ->where('cle_cli_id', $cli_id)
                    ->where('cle_titulo', $titulo)
                    ->where('cle_id', '!=', (int)$id)
                    ->where('cle_excluido', 0)
                    ->exists();
                if ($exists) {
                    Db::rollBack();
                    return ['status' => 409, 'message' => 'Já existe outro endereço cadastrado com este nome.'];
                }
            }

            if (isset($data['cle_padrao']) && $data['cle_padrao'] == 1) {
                self::query()->where('cle_cli_id', $cli_id)->where('cle_id', '!=', $id)->update(['cle_padrao' => 0]);
            }

            $c->cle_titulo     = $data['cle_titulo'] ?? $c->cle_titulo;
            $c->cle_cep        = isset($data['cle_cep']) ? str_replace('-', '', $data['cle_cep']) : $c->cle_cep;
            $c->cle_logradouro = $data['cle_logradouro'] ?? $c->cle_logradouro;
            $c->cle_bairro     = $data['cle_bairro'] ?? $c->cle_bairro;
            $c->cle_cidade     = $data['cle_cidade'] ?? $c->cle_cidade;
            $c->cle_estado     = $data['cle_estado'] ?? $c->cle_estado;
            $c->cle_numero     = $data['cle_numero'] ?? $c->cle_numero;
            $c->cle_bai_id     = isset($data['cle_bai_id']) ? (int)$data['cle_bai_id'] : $c->cle_bai_id;
            $c->cle_cid_id     = isset($data['cle_cid_id']) ? (int)$data['cle_cid_id'] : $c->cle_cid_id;
            $c->cle_est_id     = isset($data['cle_est_id']) ? (int)$data['cle_est_id'] : $c->cle_est_id;
            $c->cle_complemento= $data['cle_complemento'] ?? $c->cle_complemento;
            $c->cle_pontodereferencia = $data['cle_pontodereferencia'] ?? $c->cle_pontodereferencia;
            
            if (isset($data['cle_padrao'])) {
                $c->cle_padrao = (int)$data['cle_padrao'];
            }

            $c->save();
            Db::commit();
            return ['status' => 200, 'message' => 'Editado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao editar'];
        }
    }

    public static function deleted($id, $cli_id): array
    {
        Db::beginTransaction();
        try {
            $c = self::query()->where('cle_id', $id)->where('cle_cli_id', $cli_id)->first();
            if (! $c || (int)$c->cle_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Endereço não encontrado'];
            }
            $c->cle_excluido = 1;
            $c->save();
            Db::commit();
            return ['status' => 200, 'message' => 'Deletado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 404, 'message' => 'Erro ao deletar'];
        }
    }
}
