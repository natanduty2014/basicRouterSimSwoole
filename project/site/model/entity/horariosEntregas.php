<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

/**
 * Horários de entrega (delivery) por unidade. Espelha o pattern de tb_horarios
 * — cada row = 1 turno, múltiplos turnos por dia suportados.
 */
class horariosEntregas extends Model
{
    protected ?string $table = 'tb_horarios_entregas_rel_unidades';
    protected string $primaryKey = 'heu_id';
    public bool $timestamps = false;

    public static function listByUnidade(int $uniId): array
    {
        try {
            if ($uniId <= 0) {
                return ['status' => 400, 'message' => 'uni_id inválido'];
            }

            $rows = Db::table('tb_horarios_entregas_rel_unidades')
                ->where('heu_uni_id', $uniId)
                ->where('heu_excluido', 0)
                ->where('heu_ativo', 1)
                ->orderBy('heu_dia', 'asc')
                ->orderBy('heu_inicio', 'asc')
                ->get(['heu_id', 'heu_dia', 'heu_inicio', 'heu_fim'])
                ->map(fn($r) => (array)$r)
                ->toArray();

            return ['status' => 200, 'data' => $rows];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function saveForUnidade(int $uniId, array $turnos): array
    {
        if ($uniId <= 0) {
            return ['status' => 400, 'message' => 'uni_id inválido'];
        }

        Db::beginTransaction();
        try {
            Db::table('tb_horarios_entregas_rel_unidades')
                ->where('heu_uni_id', $uniId)
                ->where('heu_excluido', 0)
                ->update(['heu_excluido' => 1]);

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
                    'heu_uni_id' => $uniId,
                    'heu_dia' => $dia,
                    'heu_inicio' => $inicio,
                    'heu_fim' => $fim,
                    'heu_cadastro' => $now,
                    'heu_ativo' => 1,
                    'heu_excluido' => 0,
                ];
            }

            if (! empty($rows)) {
                Db::table('tb_horarios_entregas_rel_unidades')->insert($rows);
            }

            Db::commit();
            return ['status' => 200, 'message' => 'Horários de entrega atualizados'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao salvar horários de entrega', 'details' => $e->getMessage()];
        }
    }
}
