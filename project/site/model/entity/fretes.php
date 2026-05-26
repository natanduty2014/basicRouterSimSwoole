<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

/**
 * Regras de frete por unidade. Suporta múltiplos tipos:
 *  - bairro: usa fre_bai_id + tb_bairros (range de CEP)
 *  - cep: usa fre_criterio (texto "00000-000 a 99999-999")
 *  - fixo: aplica-se a qualquer entrega
 *  - distancia: fre_criterio = limite em km
 *  - poligono: fre_poligono = JSON de pontos [[lat,lng], ...]
 */
class fretes extends Model
{
    protected ?string $table = 'tb_fretes';
    protected string $primaryKey = 'fre_id';
    public bool $timestamps = false;

    public static function listByUnidade(int $uniId): array
    {
        try {
            if ($uniId <= 0) {
                return ['status' => 400, 'message' => 'uni_id inválido'];
            }

            $rows = Db::table('tb_fretes')
                ->leftJoin('tb_bairros', 'tb_bairros.bai_id', '=', 'tb_fretes.fre_bai_id')
                ->where('tb_fretes.fre_uni_id', $uniId)
                ->where('tb_fretes.fre_excluido', 0)
                ->orderBy('tb_fretes.fre_id', 'asc')
                ->get([
                    'tb_fretes.fre_id', 'tb_fretes.fre_uni_id', 'tb_fretes.fre_tipo',
                    'tb_fretes.fre_titulo', 'tb_fretes.fre_poligono', 'tb_fretes.fre_criterio',
                    'tb_fretes.fre_bai_id', 'tb_fretes.fre_preco',
                    'tb_fretes.fre_tempo_estimado_min', 'tb_fretes.fre_ativo',
                    'tb_bairros.bai_titulo as fre_bai_titulo',
                ])
                ->map(function ($r) {
                    $arr = (array)$r;
                    if (!empty($arr['fre_poligono'])) {
                        $decoded = json_decode($arr['fre_poligono'], true);
                        $arr['fre_poligono'] = is_array($decoded) ? $decoded : null;
                    } else {
                        $arr['fre_poligono'] = null;
                    }
                    return $arr;
                })
                ->toArray();

            return ['status' => 200, 'data' => $rows];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    /**
     * Avalia regras de frete de uma unidade e retorna a primeira que casa, ou null.
     * Ordem: poligono > cep > bairro > distancia > fixo.
     * Aceita lat/lng opcionais para polígono/distância e CEP numérico para cep/bairro.
     */
    public static function lookupFrete(
        int $uniId,
        int $cepClientNumeric = 0,
        ?float $clientLat = null,
        ?float $clientLng = null,
        ?float $unitLat = null,
        ?float $unitLng = null
    ): ?array {
        $regras = Db::table('tb_fretes')
            ->where('fre_uni_id', $uniId)
            ->where('fre_ativo', 1)
            ->where('fre_excluido', 0)
            ->orderByRaw("FIELD(fre_tipo, 'poligono','cep','bairro','distancia','fixo')")
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();

        foreach ($regras as $r) {
            $tipo = $r['fre_tipo'] ?? 'bairro';

            if ($tipo === 'poligono' && $clientLat !== null && $clientLng !== null && !empty($r['fre_poligono'])) {
                $points = json_decode($r['fre_poligono'], true);
                if (is_array($points) && \App\helpers\geo::pointInPolygon($clientLat, $clientLng, $points)) {
                    return $r;
                }
            } elseif ($tipo === 'cep' && $cepClientNumeric > 0 && !empty($r['fre_criterio'])) {
                $crit = preg_replace('/\s+/', '', $r['fre_criterio']);
                if (preg_match('/(\d+)[^\d](\d+)/', $crit, $m)) {
                    $ini = (int)$m[1];
                    $fim = (int)$m[2];
                    if ($cepClientNumeric >= $ini && $cepClientNumeric <= $fim) {
                        return $r;
                    }
                }
            } elseif ($tipo === 'bairro' && $cepClientNumeric > 0 && !empty($r['fre_bai_id'])) {
                $hit = Db::table('tb_bairros')
                    ->where('bai_id', $r['fre_bai_id'])
                    ->whereRaw("CAST(REPLACE(REPLACE(bai_zip_initial, '-', ''), ' ', '') AS UNSIGNED) <= ?", [$cepClientNumeric])
                    ->whereRaw("CAST(REPLACE(REPLACE(bai_zip_final, '-', ''), ' ', '') AS UNSIGNED) >= ?", [$cepClientNumeric])
                    ->where('bai_ativo', 1)
                    ->where('bai_excluido', 0)
                    ->exists();
                if ($hit) return $r;
            } elseif ($tipo === 'distancia' && $clientLat !== null && $clientLng !== null && !empty($r['fre_criterio'])) {
                $maxKm = (float)preg_replace('/[^0-9.]/', '', $r['fre_criterio']);
                if ($maxKm > 0 && $unitLat && $unitLng) {
                    $d = \App\helpers\geo::distanceKm($unitLat, $unitLng, $clientLat, $clientLng);
                    if ($d <= $maxKm) return $r;
                }
            } elseif ($tipo === 'fixo') {
                return $r;
            }
        }

        // Fallback legado: JOIN direto sem fre_tipo
        if ($cepClientNumeric > 0) {
            $legacy = Db::table('tb_bairros')
                ->join('tb_fretes', 'tb_fretes.fre_bai_id', '=', 'tb_bairros.bai_id')
                ->whereRaw("CAST(REPLACE(REPLACE(tb_bairros.bai_zip_initial, '-', ''), ' ', '') AS UNSIGNED) <= ?", [$cepClientNumeric])
                ->whereRaw("CAST(REPLACE(REPLACE(tb_bairros.bai_zip_final, '-', ''), ' ', '') AS UNSIGNED) >= ?", [$cepClientNumeric])
                ->where('tb_fretes.fre_uni_id', $uniId)
                ->where('tb_fretes.fre_ativo', 1)
                ->where('tb_fretes.fre_excluido', 0)
                ->where('tb_bairros.bai_ativo', 1)
                ->where('tb_bairros.bai_excluido', 0)
                ->first();
            if ($legacy) return (array)$legacy;
        }

        return null;
    }

    /**
     * Substitui todas as regras de frete de uma unidade pela lista informada.
     * Cada regra: ['tipo','titulo','poligono','criterio','bai_id','preco','tempo_estimado_min','ativo']
     */
    public static function saveForUnidade(int $uniId, array $regras): array
    {
        if ($uniId <= 0) {
            return ['status' => 400, 'message' => 'uni_id inválido'];
        }

        $tiposValidos = ['bairro', 'cep', 'fixo', 'distancia', 'poligono'];

        Db::beginTransaction();
        try {
            // Soft-delete das regras atuais
            Db::table('tb_fretes')
                ->where('fre_uni_id', $uniId)
                ->where('fre_excluido', 0)
                ->update(['fre_excluido' => 1]);

            $now = date('Y-m-d H:i:s');
            $rows = [];
            foreach ($regras as $r) {
                $tipo = isset($r['tipo']) ? (string)$r['tipo'] : 'bairro';
                if (!in_array($tipo, $tiposValidos, true)) continue;

                $preco = isset($r['preco']) ? (float)$r['preco'] : 0;
                $ativo = !empty($r['ativo']) ? 1 : 0;
                $titulo = isset($r['titulo']) ? substr((string)$r['titulo'], 0, 100) : null;
                $tempo = isset($r['tempo_estimado_min']) ? (int)$r['tempo_estimado_min'] : null;

                $poligonoJson = null;
                if ($tipo === 'poligono') {
                    $pts = $r['poligono'] ?? [];
                    if (!is_array($pts) || count($pts) < 3) {
                        Db::rollBack();
                        return ['status' => 400, 'message' => 'Polígono requer ao menos 3 pontos'];
                    }
                    $poligonoJson = json_encode(array_values($pts));
                }

                $baiId = null;
                if ($tipo === 'bairro') {
                    $baiId = isset($r['bai_id']) ? (int)$r['bai_id'] : null;
                    if (!$baiId) {
                        // permite criterio textual mesmo sem FK por compatibilidade
                    }
                }

                $criterio = isset($r['criterio']) ? substr((string)$r['criterio'], 0, 255) : null;

                $rows[] = [
                    'fre_uni_id' => $uniId,
                    'fre_tipo' => $tipo,
                    'fre_titulo' => $titulo,
                    'fre_poligono' => $poligonoJson,
                    'fre_criterio' => $criterio,
                    'fre_bai_id' => $baiId,
                    'fre_preco' => $preco,
                    'fre_tempo_estimado_min' => $tempo,
                    'fre_cadastro' => $now,
                    'fre_ativo' => $ativo,
                    'fre_excluido' => 0,
                ];
            }

            if (!empty($rows)) {
                Db::table('tb_fretes')->insert($rows);
            }

            Db::commit();
            return ['status' => 200, 'message' => 'Fretes atualizados'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 500, 'message' => 'Erro ao salvar fretes', 'details' => $e->getMessage()];
        }
    }
}
