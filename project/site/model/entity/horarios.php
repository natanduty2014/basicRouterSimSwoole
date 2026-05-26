<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

/**
 * Horários de atendimento da unidade (balcão / pedidos online).
 * Cada row em tb_horarios = 1 turno. Múltiplos turnos por dia são suportados
 * naturalmente (várias rows com o mesmo hor_dia).
 *
 * Convenção de dia da semana: ISO-8601 (1=Segunda, 7=Domingo) — alinhado com
 * date('N') que já é usado nos models existentes (ex: pedidos::, contratantes::).
 */
class horarios extends Model
{
    protected ?string $table = 'tb_horarios';
    protected string $primaryKey = 'hor_id';
    public bool $timestamps = false;

    public static function listByUnidade(int $uniId): array
    {
        try {
            if ($uniId <= 0) {
                return ['status' => 400, 'message' => 'uni_id inválido'];
            }

            $rows = Db::table('tb_horarios')
                ->where('hor_uni_id', $uniId)
                ->where('hor_excluido', 0)
                ->where('hor_ativo', 1)
                ->orderBy('hor_dia', 'asc')
                ->orderBy('hor_inicio', 'asc')
                ->get(['hor_id', 'hor_dia', 'hor_inicio', 'hor_fim'])
                ->map(fn($r) => (array)$r)
                ->toArray();

            return ['status' => 200, 'data' => $rows];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    /**
     * Substitui todos os turnos de atendimento da unidade.
     * Estratégia: soft-delete (excluido=1) todos os atuais e insere novos.
     * Segue o padrão histórico já existente na tabela.
     *
     * $turnos = [['dia'=>1,'inicio'=>'09:00','fim'=>'18:00'], ...]
     */
    public static function saveForUnidade(int $uniId, array $turnos): array
    {
        if ($uniId <= 0) {
            return ['status' => 400, 'message' => 'uni_id inválido'];
        }

        Db::beginTransaction();
        try {
            Db::table('tb_horarios')
                ->where('hor_uni_id', $uniId)
                ->where('hor_excluido', 0)
                ->update(['hor_excluido' => 1]);

            $now = date('Y-m-d H:i:s');
            $rows = [];
            foreach ($turnos as $t) {
                $dia = isset($t['dia']) ? (int)$t['dia'] : 0;
                $inicio = isset($t['inicio']) ? (string)$t['inicio'] : '';
                $fim = isset($t['fim']) ? (string)$t['fim'] : '';
                if ($dia < 1 || $dia > 7 || $inicio === '' || $fim === '') {
                    continue;
                }
                if ($inicio >= $fim) {
                    Db::rollBack();
                    return ['status' => 400, 'message' => "Turno inválido para dia $dia: abertura deve ser anterior ao fechamento"];
                }
                $rows[] = [
                    'hor_uni_id' => $uniId,
                    'hor_dia' => $dia,
                    'hor_inicio' => $inicio,
                    'hor_fim' => $fim,
                    'hor_cadastro' => $now,
                    'hor_ativo' => 1,
                    'hor_excluido' => 0,
                ];
            }

            if (! empty($rows)) {
                Db::table('tb_horarios')->insert($rows);
            }

            Db::commit();
            return ['status' => 200, 'message' => 'Horários de atendimento atualizados'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao salvar horários de atendimento', 'details' => $e->getMessage()];
        }
    }
}
