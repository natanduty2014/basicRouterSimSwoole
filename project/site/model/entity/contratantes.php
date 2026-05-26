<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class contratantes extends Model
{
    protected ?string $table = 'tb_contratantes';
    protected string $primaryKey = 'con_id';
    public bool $timestamps = true;
    const CREATED_AT = 'con_cadastro';
    const UPDATED_AT = null;

    public function urls()
    {
        return $this->hasMany(contratantesUrl::class, 'cou_con_id', 'con_id');
    }

    public function unidades()
    {
        return $this->hasMany(unidades::class, 'uni_con_id', 'con_id');
    }

    /** Normaliza CEP: mantém apenas dígitos (ex.: 01234-567 -> 01234567) */
    private static function normalizeZip(string $zip): string
    {
        return preg_replace('/\D+/', '', $zip) ?? '';
    }


    public static function create($data): array
    {
        Db::beginTransaction();
        try {
            $c = new self();
            $c->con_titulo          = $data['con_titulo'] ?? '';
            $c->con_title           = $data['con_title'] ?? null;
            $c->con_descricao       = $data['con_descricao'] ?? null;
            $c->con_razaosocial     = $data['con_razaosocial'] ?? '';
            $c->con_cnpj            = $data['con_cnpj'] ?? '';
            $c->con_email           = $data['con_email'] ?? '';
            $c->con_token           = $data['con_token'] ?? md5(uniqid((string)mt_rand(), true));
            $c->con_telefone        = $data['con_telefone'] ?? '';
            $c->con_preco           = isset($data['con_preco']) ? (float)$data['con_preco'] : 0.00;
            $c->con_googleanalytics = $data['con_googleanalytics'] ?? null;
            $c->con_theme           = $data['con_theme'] ?? null;
            $c->con_javascript      = $data['con_javascript'] ?? null;
            $c->con_pwa_name        = $data['con_pwa_name'] ?? null;
            $c->con_pwa_color       = $data['con_pwa_color'] ?? null;
            $c->con_promocao_aberta = isset($data['con_promocao_aberta']) ? (int)$data['con_promocao_aberta'] : 1;

            $c->save();
            Db::commit();

            return ['status' => 201, 'message' => 'Cadastrado com sucesso'];
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
            if (! $c || (int)$c->con_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Contratante não encontrado'];
            }

            $c->con_titulo          = $data['con_titulo'] ?? $c->con_titulo;
            $c->con_title           = $data['con_title'] ?? $c->con_title;
            $c->con_descricao       = $data['con_descricao'] ?? $c->con_descricao;
            $c->con_razaosocial     = $data['con_razaosocial'] ?? $c->con_razaosocial;
            $c->con_cnpj            = $data['con_cnpj'] ?? $c->con_cnpj;
            $c->con_email           = $data['con_email'] ?? $c->con_email;
            $c->con_token           = $data['con_token'] ?? $c->con_token;
            $c->con_telefone        = $data['con_telefone'] ?? $c->con_telefone;

            if (isset($data['con_preco'])) {
                $c->con_preco = (float)$data['con_preco'];
            }

            $c->con_googleanalytics = $data['con_googleanalytics'] ?? $c->con_googleanalytics;
            $c->con_theme           = $data['con_theme'] ?? $c->con_theme;
            $c->con_javascript      = $data['con_javascript'] ?? $c->con_javascript;
            $c->con_pwa_name        = $data['con_pwa_name'] ?? $c->con_pwa_name;
            $c->con_pwa_color       = $data['con_pwa_color'] ?? $c->con_pwa_color;
            $c->con_promocao_aberta = isset($data['con_promocao_aberta']) ? (int)$data['con_promocao_aberta'] : $c->con_promocao_aberta;

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
            if (! $c || (int)$c->con_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Contratante não encontrado'];
            }
            $c->con_ativo = (int) (! (int)$c->con_ativo);
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
            if (! $c || (int)$c->con_excluido === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Contratante não encontrado'];
            }
            $c->con_excluido = 1;
            $c->save();
            Db::commit();
            return ['status' => 200, 'message' => 'Deletado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 404, 'message' => 'Erro ao deletar'];
        }
    }

    public static function listAll($pag): array
    {
        $page = (int)($pag ?? 1);

        try {
            $p = self::query()
                ->with(['urls' => function ($query) {
                    $query->where('cou_excluido', 0);
                }])
                ->where('con_excluido', 0)
                ->orderBy('con_id', 'asc')
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
                ->with(['urls' => function ($query) {
                    $query->where('cou_excluido', 0);
                }])
                ->where('con_id', $id)
                ->where('con_excluido', 0)
                ->first();

            if (! $row) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            return ['status' => 200, 'data' => $row->toArray()];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function search_url($url, $cepClient = null, $rawQuery = null, ?float $clientLat = null, ?float $clientLng = null)
    {
        try {
            $zip = self::normalizeZip((string)$cepClient);
            $zipNumeric = $zip !== '' ? (int)$zip : 0;

            $row = self::query()
                ->whereHas('urls', function ($query) use ($url) {
                    $query->where('cou_url', $url)->where('cou_excluido', 0);
                })
                ->with([
                    'urls' => function ($query) use ($url) {
                        $query->where('cou_url', $url)->where('cou_excluido', 0);
                    },
                    'unidades' => function ($query) {
                        $query->where('uni_excluido', 0)
                              ->where('uni_ativo', 1);
                    }
                ])
                ->where('con_excluido', 0)
                ->first();

            if (! $row) {
                return ['status' => 404, 'message' => 'Not found'];
            }


            $data = $row->toArray();

            // Logos
            $imagens = \Hyperf\DbConnection\Db::table('tb_contratantes_imagens')
                ->where('coi_con_id', $data['con_id'])
                ->where('coi_ativo', 1)
                ->where('coi_excluido', 0)
                ->whereIn('coi_tipo', [1, 2, 3])
                ->get();

            $data['logo_desktop'] = null;
            $data['logo_mobile'] = null;
            $data['fiveicon'] = null;
            foreach ($imagens as $img) {
                if ($img->coi_tipo == 3) {
                    $data['logo_desktop'] = '/public/uploads/marcas/' . $img->coi_img;
                } elseif ($img->coi_tipo == 2) {
                    $data['logo_mobile'] = '/public/uploads/marcas/' . $img->coi_img;
                } elseif ($img->coi_tipo == 1) {
                    $data['fiveicon'] = '/public/uploads/marcas/' . $img->coi_img;
                }
            }

            // Calcular disponibilidade por horário para cada unidade
            if (!empty($data['unidades'])) {
                // Verifica se o URL é de cardápio digital (1) ou delivery (0)
                $isCardapio = !empty($data['urls'][0]['cou_cardapio']);
                $todayWeekDay = (int)date('N'); // 1=segunda .. 7=domingo

                foreach ($data['unidades'] as &$unidade) {

                    if ($isCardapio) {
                        $turno = Db::table('tb_horarios')
                            ->where('hor_inicio', '<=', Db::raw('TIME(NOW())'))
                            ->where('hor_fim', '>=', Db::raw('TIME(NOW())'))
                            ->where('hor_dia', $todayWeekDay)
                            ->where('hor_ativo', 1)
                            ->where('hor_excluido', 0)
                            ->where('hor_uni_id', $unidade['uni_id'])
                            ->first();
                    } else {
                        $turno = Db::table('tb_horarios_entregas_rel_unidades')
                            ->where('heu_inicio', '<=', Db::raw('TIME(NOW())'))
                            ->where('heu_fim', '>=', Db::raw('TIME(NOW())'))
                            ->where('heu_dia', $todayWeekDay)
                            ->where('heu_ativo', 1)
                            ->where('heu_excluido', 0)
                            ->where('heu_uni_id', $unidade['uni_id'])
                            ->first();
                    }

                    // Disponivel apenas se estiver dentro do horário de funcionamento
                    $unidade['uni_is_disponivel'] = $turno !== null;
                }
                unset($unidade);
            }

            // Calcular distância se o $cepClient ou $rawQuery foi informado
            if (($cepClient || $rawQuery) && !empty($data['unidades'])) {
                $apiKey = 'AIzaSyDg3lNZl6LwhHL1vWexDXexZUr30TztzL8';

                foreach ($data['unidades'] as &$unidade) {
                    $unidade['distancia'] = null;
                    $unidade['distancia_texto'] = null;
                    $unidade['frete'] = null;

                    $uniLatForLookup = isset($unidade['uni_latitude']) ? (float)$unidade['uni_latitude'] : null;
                    $uniLngForLookup = isset($unidade['uni_longitude']) ? (float)$unidade['uni_longitude'] : null;
                    $matchedFrete = \App\model\entity\fretes::lookupFrete(
                        (int)$unidade['uni_id'],
                        $zipNumeric,
                        $clientLat,
                        $clientLng,
                        $uniLatForLookup ?: null,
                        $uniLngForLookup ?: null
                    );

                    if ($matchedFrete) {
                        $unidade['frete'] = $matchedFrete;
                    } else if ($zip !== '' || $clientLat !== null) {
                        $unidade['frete'] = ['fre_preco' => 'indisponivel'];
                    }

                    $uniCep = $unidade['uni_cep'] ?? null;
                    if ($uniCep && $apiKey) {
                        $uniCepFormatado = preg_replace('/[^0-9]/', '', $uniCep);

                        $destinoForDistance = null;
                        // Prioridade 1: lat/lng do cliente (mais precisas — vêm de GPS ou Nominatim)
                        if ($clientLat !== null && $clientLng !== null) {
                            $destinoForDistance = urlencode("{$clientLat},{$clientLng}");
                        } elseif (!empty($cepClient)) {
                            $cepClientFormatado = preg_replace('/[^0-9]/', '', $cepClient);
                            if (!empty($cepClientFormatado)) {
                                $cepClientFormatado = str_pad($cepClientFormatado, 8, '0', STR_PAD_RIGHT);
                                $destinoForDistance = substr($cepClientFormatado, 0, 5) . '-' . substr($cepClientFormatado, 5);
                            }
                        } elseif (!empty($rawQuery)) {
                            $destinoForDistance = urlencode($rawQuery . ', Brasil');
                        }

                        if (!empty($uniCepFormatado) && !empty($destinoForDistance)) {
                            // Garantir formato minimo de 8 dígitos preenchendo com zeros a direita se faltar
                            $uniCepFormatado = str_pad($uniCepFormatado, 8, '0', STR_PAD_RIGHT);
                            $uniCepFormatado = substr($uniCepFormatado, 0, 5) . '-' . substr($uniCepFormatado, 5);

                            // API Google Distance Matrix
                            $googleUrl = "https://maps.googleapis.com/maps/api/distancematrix/json?origins={$uniCepFormatado}&destinations={$destinoForDistance}&key={$apiKey}";

                            $response = @file_get_contents($googleUrl);
                            if ($response) {
                                $json = json_decode($response, true);
                                if (isset($json['rows'][0]['elements'][0]['status']) && $json['rows'][0]['elements'][0]['status'] === 'OK') {
                                    $unidade['distancia'] = $json['rows'][0]['elements'][0]['distance']['value'];
                                    $unidade['distancia_texto'] = $json['rows'][0]['elements'][0]['distance']['text'];
                                }
                            }
                        }
                    }
                }

            }

            if (!empty($data['unidades'])) {
                // Remove dados sensíveis de cada unidade
                $sensitiveFields = [
                    'uni_pagseguro_email', 'uni_pagseguro_token', 'uni_pagseguro_token_sandbox',
                    'uni_orenda_token', 'uni_orenda_id',
                    'uni_sms', 'uni_ligacao', 'uni_status_notificar_sms',
                ];
                foreach ($data['unidades'] as &$unidade) {
                    foreach ($sensitiveFields as $field) {
                        unset($unidade[$field]);
                    }
                }
                unset($unidade);

                // Prioriza unidades abertas e, em seguida, a menor distância quando disponível.
                usort($data['unidades'], function ($a, $b) {
                    $isOpenA = (int)($a['uni_ativo'] ?? 0);
                    $isOpenB = (int)($b['uni_ativo'] ?? 0);

                    if ($isOpenA !== $isOpenB) {
                        return $isOpenB <=> $isOpenA;
                    }

                    $distA = isset($a['distancia']) && $a['distancia'] !== null ? (int)$a['distancia'] : PHP_INT_MAX;
                    $distB = isset($b['distancia']) && $b['distancia'] !== null ? (int)$b['distancia'] : PHP_INT_MAX;

                    if ($distA !== $distB) {
                        return $distA <=> $distB;
                    }

                    return strcmp((string)($a['uni_titulo'] ?? ''), (string)($b['uni_titulo'] ?? ''));
                });
            }
            return ['status' => 200, 'data' => $data];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    /**
     * Converte um texto de endereço em CEP usando Google Geocoding API.
     */
    private static function addressToCep(string $address): ?string
    {
        $apiKey = 'AIzaSyDg3lNZl6LwhHL1vWexDXexZUr30TztzL8';
        $addressEncoded = urlencode($address);
        $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$addressEncoded}&key={$apiKey}&region=br&language=pt-BR";

        $response = @file_get_contents($url);
        if (!$response) {
            return null;
        }

        $json = json_decode($response, true);
        if (empty($json['results'][0]['address_components'])) {
            return null;
        }

        // Procura o CEP nos componentes do endereço
        foreach ($json['results'][0]['address_components'] as $component) {
            if (in_array('postal_code', $component['types'])) {
                return preg_replace('/\D+/', '', $component['long_name']);
            }
        }

        return null;
    }

    /**
     * Busca unidades por URL da loja, aceitando CEP ou texto de endereço.
     * Se for um endereço (não apenas dígitos), usa Google Geocoding para obter o CEP.
     */
    public static function search_by_query(string $url, string $query, ?float $clientLat = null, ?float $clientLng = null): array
    {
        $cepOnly = preg_replace('/\D+/', '', $query);

        // Se a query tem pelo menos 8 dígitos, trata como CEP
        if (strlen($cepOnly) >= 8) {
            return self::search_url($url, $cepOnly, $query, $clientLat, $clientLng);
        }

        // Caso contrário, trata como endereço textual e tenta converter para CEP
        $cepFromAddress = self::addressToCep($query);
        if ($cepFromAddress) {
            return self::search_url($url, $cepFromAddress, $query, $clientLat, $clientLng);
        }

        // Se não conseguiu obter CEP do endereço, retorna unidades calculando distância pelo endereço
        return self::search_url($url, null, $query, $clientLat, $clientLng);
    }
}
