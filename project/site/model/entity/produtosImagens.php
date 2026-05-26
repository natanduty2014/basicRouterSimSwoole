<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class produtosImagens extends Model
{
    protected ?string $table = 'tb_produtos_imagens';
    protected string $primaryKey = 'pri_id';
    public bool $timestamps = true;
    const CREATED_AT = 'pri_cadastro';
    const UPDATED_AT = null;

    protected array $fillable = [
        'pri_pro_id',
        'pri_img',
        'pri_capa',
        'pri_ativo',
        'pri_excluido',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    public function produto()
    {
        return $this->belongsTo(produtos::class, 'pri_pro_id', 'pro_id');
    }

    // ─── CRUD ─────────────────────────────────────────────────────────

    /**
     * Salva imagem em base64 no disco e cria registro no banco.
     */
    public static function createImage($proId, $imgBase64, $isCapa = false): array
    {
        try {
            // Extrair extensao e dados do base64
            if (preg_match('/^data:image\/(\w+);base64,/', $imgBase64, $matches)) {
                $extension = $matches[1];
                $imgData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $imgBase64));
            } else {
                return ['status' => 400, 'message' => 'Formato de imagem inválido'];
            }

            // Gerar nome do arquivo
            $fileName = 'produto-' . $proId . '-' . uniqid() . '.' . $extension;

            // Salvar no disco
            $uploadDir = __DIR__ . '/../../../../public/uploads/produtos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            file_put_contents($uploadDir . $fileName, $imgData);

            // Se for capa, resetar capa das outras imagens do produto
            if ($isCapa) {
                self::query()
                    ->where('pri_pro_id', $proId)
                    ->where('pri_excluido', 0)
                    ->update(['pri_capa' => 0]);
            }

            // Criar registro
            $record = new self();
            $record->pri_pro_id = $proId;
            $record->pri_img = $fileName;
            $record->pri_capa = $isCapa ? 1 : 0;
            $record->pri_ativo = 1;
            $record->pri_excluido = 0;
            $record->save();

            return ['status' => 201, 'message' => 'Imagem salva com sucesso', 'data' => $record];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    /**
     * Soft delete de uma imagem.
     */
    public static function deleteImage($priId): array
    {
        try {
            $record = self::query()
                ->where('pri_id', $priId)
                ->where('pri_excluido', 0)
                ->first();

            if (!$record) {
                return ['status' => 404, 'message' => 'Imagem não encontrada'];
            }

            $record->pri_excluido = 1;
            $record->pri_ativo = 0;
            $record->save();

            return ['status' => 200, 'message' => 'Imagem removida com sucesso'];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    /**
     * Define uma imagem como capa do produto.
     */
    public static function setCapa($priId, $proId): array
    {
        try {
            Db::beginTransaction();

            // Resetar capa de todas as imagens do produto
            self::query()
                ->where('pri_pro_id', $proId)
                ->where('pri_excluido', 0)
                ->update(['pri_capa' => 0]);

            // Definir a imagem escolhida como capa
            $record = self::query()
                ->where('pri_id', $priId)
                ->where('pri_pro_id', $proId)
                ->where('pri_excluido', 0)
                ->first();

            if (!$record) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Imagem não encontrada'];
            }

            $record->pri_capa = 1;
            $record->save();

            Db::commit();

            return ['status' => 200, 'message' => 'Imagem de capa definida com sucesso', 'data' => $record];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lista imagens de um produto (capa primeiro).
     */
    public static function listByProduct($proId): array
    {
        try {
            $images = self::query()
                ->where('pri_pro_id', $proId)
                ->where('pri_excluido', 0)
                ->orderBy('pri_capa', 'desc')
                ->get()
                ->toArray();

            return ['status' => 200, 'data' => $images];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
