<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;

class pedidos extends Model
{
    protected ?string $table = 'tb_pedidos';
    protected string $primaryKey = 'ped_id';
    public bool $timestamps = true;
    const CREATED_AT = 'ped_cadastro';
    const UPDATED_AT = 'ped_atualizacao';

    /**
     * @param int|null $conId
     * @param int|null $pseId
     * @param int|null $page
     * @param array|null $allowedUniIds null = sem restrição (admin); array = limita pedidos a essas unidades
     */
    public static function listByContratante($conId, $pseId, $page, ?array $allowedUniIds = null): array
    {
        $page    = (int) ($page ?? 1);
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        try {
            $today = date('Y-m-d');

            $query = Db::table('tb_pedidos as p')
                ->join('tb_unidades as u', 'u.uni_id', '=', 'p.ped_uni_id')
                ->leftJoin('tb_clientes as c', 'c.cli_id', '=', 'p.ped_cli_id')
                ->leftJoin('tb_pedidos_situacao_entrega as pse', 'pse.pse_id', '=', 'p.ped_pse_id')
                ->leftJoin('tb_pagamentos_metodos as pag', 'pag.pag_id', '=', 'p.ped_pag_id')
                ->selectRaw('
                    p.ped_id, p.ped_pse_id, p.ped_pag_id, p.ped_subtotal, p.ped_total,
                    p.ped_desconto, p.ped_fretepreco, p.ped_troco, p.ped_agendamento,
                    p.ped_bairro, p.ped_logradouro, p.ped_numero, p.ped_cidade, p.ped_estado,
                    p.ped_cadastro, p.ped_atualizacao,
                    c.cli_nome, c.cli_telefone1, c.cli_telefone2,
                    pse.pse_titulo,
                    pag.pag_titulo,
                    (SELECT GROUP_CONCAT(pro.pro_titulo ORDER BY pp.pep_id SEPARATOR \', \')
                     FROM tb_pedidos_produtos pp
                     LEFT JOIN tb_produtos pro ON pro.pro_id = pp.pep_pro_id
                     WHERE pp.pep_ped_id = p.ped_id AND pp.pep_excluido = 0
                     LIMIT 3) as itens_resumo,
                    (SELECT COUNT(*) FROM tb_pedidos_produtos
                     WHERE pep_ped_id = p.ped_id AND pep_excluido = 0) as total_itens
                ')
                ->where('p.ped_excluido', 0)
                ->where('u.uni_con_id', (int) $conId)
                ->where(function($q) use ($today) {
                    $q->whereRaw("DATE(p.ped_cadastro) = ?", [$today])
                      ->orWhereRaw("DATE(p.ped_agendamento) = ?", [$today]);
                });

            if ($pseId !== null && $pseId !== '') {
                $query->where('p.ped_pse_id', (int) $pseId);
            }

            // Escopo de unidade: null = admin (sem restrição); array vazio ou
            // com ids = limita aos uni_id permitidos. Array vazio para
            // não-admin sem unidades configuradas → não retorna nada.
            if (is_array($allowedUniIds)) {
                if (count($allowedUniIds) === 0) {
                    return [
                        'status' => 200,
                        'data' => [],
                        'pagination' => [
                            'current_page' => $page, 'last_page' => 1, 'per_page' => $perPage,
                            'total' => 0, 'from' => 0, 'to' => 0,
                            'next_page_url' => null, 'prev_page_url' => null,
                        ],
                    ];
                }
                $query->whereIn('p.ped_uni_id', $allowedUniIds);
            }

            $total = $query->count();
            $rows  = $query->orderByDesc('p.ped_cadastro')->offset($offset)->limit($perPage)->get()->toArray();

            $lastPage = max(1, (int) ceil($total / $perPage));

            return [
                'status'     => 200,
                'data'       => $rows,
                'pagination' => [
                    'current_page'  => $page,
                    'last_page'     => $lastPage,
                    'per_page'      => $perPage,
                    'total'         => $total,
                    'from'          => $total > 0 ? $offset + 1 : 0,
                    'to'            => min($offset + $perPage, $total),
                    'next_page_url' => $page < $lastPage ? $page + 1 : null,
                    'prev_page_url' => $page > 1 ? $page - 1 : null,
                ],

            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    // ─── create ──────────────────────────────────────────────────────────────

    private static function generateOrendaPix($pedId, $total, $data)
    {
        $unidade = Db::table('tb_unidades')->where('uni_id', $data['uni_id'])->first();
        if (!$unidade || empty($unidade->uni_orenda_id)) {
            return false;
        }

        $cliente = null;
        if (!empty($data['cli_id'])) {
            $cliente = Db::table('tb_clientes')->where('cli_id', $data['cli_id'])->first();
        }

        $cliNome  = $cliente->cli_nome ?? 'Consumidor Final';
        $cliCpf   = $cliente->cli_cpf ?? '000.000.000-00';
        $cliPhone = $cliente->cli_telefone1 ?? '84999999999';
        $cliEmail = $cliente->cli_email ?? 'contato@refacil.com.br';

        $endereco = $data['endereco_logradouro'] ? implode(', ', array_filter([$data['endereco_logradouro'], $data['endereco_numero']])) : 'Avenida Praia de Ponta Negra, 9060';
        $cidade   = $data['endereco_cidade'] ? $data['endereco_cidade'] : 'Natal';
        $uf       = $data['endereco_estado'] ? $data['endereco_estado'] : 'RN';
        $cep      = $data['endereco_cep'] ? $data['endereco_cep'] : '59094100';

        $url = "https://www.orendapay.com.br/api/v1/cobranca";

        $json = [
            "seu_codigo" => (string)$pedId,
            "descricao" => 'Pedido N ' . $pedId,
            "vencimento" => date('d/m/Y'),
            "valor" => $total,
            "juros" => "0",
            "multa" => "0",
            "cliente_nome" => $cliNome,
            "cliente_cpf_cnpj" => $cliCpf,
            "cliente_telefone" => $cliPhone,
            "cliente_email" => $cliEmail,
            "cliente_endereco" => $endereco,
            "cliente_cidade" => $cidade,
            "cliente_uf" => $uf,
            "cliente_cep" => $cep,
            "cliente_grupo" => "cliente",
            "TIPO" => "pix",
            "NUMERO_PARCELAS" => "1",
            "RECORRENCIA" => "0",
            "ENVIAR_EMAIL" => "0",
            "ENVIAR_SMS" => "0",
            "ENVIO_IMEDIATO" => "1",
            "SPLIT" => "0",
            "URL_CALLBACK" => "http://localhost:10384/callback",
            "UTILIZACAO" => "ECOMMERCE"
        ];

        $orendaId = $unidade->uni_orenda_id;
        $orendaToken = $unidade->uni_orenda_token;

        if (empty($orendaId) || empty($orendaToken)) {
            return false;
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($json));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "x-ID:" . $orendaId,
            "x-Token:" . $orendaToken,
            "Content-Type: application/json"
        ]);

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpcode == 200 || $httpcode == 201) {
            $respData = json_decode($response, true);
            if (!empty($respData['cobrancas'][0])) {
                $cobranca = $respData['cobrancas'][0];
                Db::table('tb_pedidos')->where('ped_id', $pedId)->update([
                    'ped_codigo_orenda' => $cobranca['codigo'] ?? null,
                    'ped_pix_qrcode' => $cobranca['pix_qrcode'] ?? null,
                    'ped_pix_chave' => $cobranca['pix_chave'] ?? null
                ]);
            }
        }
        return true;
    }

    /**
     * Valida nÃºmero de cartÃ£o pelo algoritmo de Luhn.
     */
    private static function validateCardLuhn(string $number): bool
    {
        $digits = preg_replace('/\D/', '', $number);
        if (!ctype_digit($digits) || strlen($digits) < 13) {
            return false;
        }
        $sum = 0;
        $alternate = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int)$digits[$i];
            if ($alternate) {
                $n *= 2;
                if ($n > 9) $n -= 9;
            }
            $sum += $n;
            $alternate = !$alternate;
        }
        return ($sum % 10) === 0;
    }

    /**
     * Valida data de validade (MM/AA) - nÃ£o pode estar vencida.
     */
    private static function validateCardExpiry(string $expiry): bool
    {
        $cleaned = preg_replace('/[^\d]/', '', $expiry);
        if (strlen($cleaned) !== 4) return false;
        $month = (int)substr($cleaned, 0, 2);
        $year = (int)substr($cleaned, 2, 2);
        if ($month < 1 || $month > 12) return false;
        $currentYear = (int)date('y');
        $currentMonth = (int)date('n');
        return ($year > $currentYear) || ($year === $currentYear && $month >= $currentMonth);
    }

    /**
     * Valida CVV - 3 ou 4 dÃ­gitos dependendo da bandeira.
     */
    private static function validateCardCvv(string $cvv, string $cardNumber): bool
    {
        if (!ctype_digit($cvv)) return false;
        // Amex tem 4 dÃ­gitos, demais 3
        $isAmex = preg_match('/^3[47]/', preg_replace('/\D/', '', $cardNumber));
        $expectedLength = $isAmex ? 4 : 3;
        return strlen($cvv) === $expectedLength;
    }

    private static function generateOrendaCard($pedId, $total, $data)
    {
        $unidade = Db::table('tb_unidades')->where('uni_id', $data['uni_id'])->first();
        if (!$unidade || empty($unidade->uni_orenda_id)) {
            throw new \Exception('Unidade sem credenciais de pagamento configuradas.');
        }

        $cardNumber = $data['cartao_numero'] ?? null;
        $cardName = $data['cartao_nome'] ?? null;
        $cardExpiry = $data['cartao_validade'] ?? null;
        $cardCvv = $data['cartao_cvv'] ?? null;
        $cardInstallments = (int)($data['cartao_parcelas'] ?? 1);

        if (!$cardNumber || !$cardName || !$cardExpiry || !$cardCvv) {
            throw new \Exception('Dados do cartÃ£o incompletos.');
        }

        // Validacao no backend (camada extra de seguranca)
        if (!self::validateCardExpiry($cardExpiry)) {
            throw new \Exception('Data de validade inválida ou vencida.');
        }
        if (!self::validateCardCvv($cardCvv, $cardNumber)) {
            throw new \Exception('CVV inválido.');
        }

        $cliente = null;
        if (!empty($data['cli_id'])) {
            $cliente = Db::table('tb_clientes')->where('cli_id', $data['cli_id'])->first();
        }

        $cliNome  = $cliente->cli_nome ?? 'Consumidor Final';
        $cliCpf   = $cliente->cli_cpf ?? '000.000.000-00';
        $cliPhone = $cliente->cli_telefone1 ?? '84999999999';
        $cliEmail = $cliente->cli_email ?? 'contato@refacil.com.br';

        $endereco = $data['endereco_logradouro'] ? implode(', ', array_filter([$data['endereco_logradouro'], $data['endereco_numero']])) : 'Avenida Praia de Ponta Negra, 9060';
        $cidade   = $data['endereco_cidade'] ? $data['endereco_cidade'] : 'Natal';
        $uf       = $data['endereco_estado'] ? $data['endereco_estado'] : 'RN';
        $cep      = $data['endereco_cep'] ? $data['endereco_cep'] : '59094100';

        $url = "https://www.orendapay.com.br/api/v1/cobranca";

        $json = [
            "seu_codigo" => (string)$pedId,
            "descricao" => 'Pedido N ' . $pedId,
            "vencimento" => date('d/m/Y'),
            "valor" => $total,
            "juros" => "0",
            "multa" => "0",
            "cliente_nome" => $cliNome,
            "cliente_cpf_cnpj" => $cliCpf,
            "cliente_telefone" => $cliPhone,
            "cliente_email" => $cliEmail,
            "cliente_endereco" => $endereco,
            "cliente_cidade" => $cidade,
            "cliente_uf" => $uf,
            "cliente_cep" => $cep,
            "cliente_grupo" => "cliente",
            "TIPO" => "credit",
            "NUMERO_PARCELAS" => (string)$cardInstallments,
            "RECORRENCIA" => "0",
            "ENVIAR_EMAIL" => "0",
            "ENVIAR_SMS" => "0",
            "ENVIO_IMEDIATO" => "1",
            "SPLIT" => "0",
            "URL_CALLBACK" => "http://localhost:10384/callback",
            "UTILIZACAO" => "ECOMMERCE",
            "cartao_numero" => $cardNumber,
            "cartao_nome" => $cardName,
            "cartao_validade" => $cardExpiry,
            "cartao_codigo" => $cardCvv
        ];

        // Usa credenciais da unidade (do banco) ao invÃ©s de hardcoded
        $orendaId = $unidade->uni_orenda_id;
        $orendaToken = $unidade->uni_orenda_token;

        if (empty($orendaId) || empty($orendaToken)) {
            throw new \Exception('Credenciais OrendaPay nÃ£o configuradas para esta unidade.');
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($json));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "x-ID:" . $orendaId,
            "x-Token:" . $orendaToken,
            "Content-Type: application/json"
        ]);

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            throw new \Exception('Erro de comunicaÃ§Ã£o com a operadora de pagamento: ' . $curlError);
        }

        if ($httpcode !== 200 && $httpcode !== 201) {
            $respData = json_decode($response, true);
            $erroMsg = 'Pagamento recusado pela operadora.';
            if (!empty($respData['message'])) {
                $erroMsg = is_string($respData['message']) ? $respData['message'] : json_encode($respData['message']);
            } elseif (!empty($respData['erro'])) {
                $erroMsg = $respData['erro'];
            }
            throw new \Exception($erroMsg);
        }

        $respData = json_decode($response, true);
        $cardStatus = $respData['situacao_cartao'] ?? null;
        if ($cardStatus && strtoupper((string)$cardStatus) !== 'OK') {
            $detail = $respData['situacao_cartao_detalhes'] ?? 'Cartao recusado.';
            throw new \Exception(is_string($detail) ? $detail : json_encode($detail));
        }

        $cobranca = $respData['cobrancas'][0] ?? null;
        if (!$cobranca) {
            throw new \Exception('Cobranca nao retornou dados validos.');
        }

        $cobrancaStatus = $cobranca['situacao_cartao'] ?? null;
        if ($cobrancaStatus && strtoupper((string)$cobrancaStatus) !== 'OK') {
            $detail = $cobranca['situacao_cartao_detalhes'] ?? 'Cartao recusado.';
            throw new \Exception(is_string($detail) ? $detail : json_encode($detail));
        }

        Db::table('tb_pedidos')->where('ped_id', $pedId)->update([
            'ped_codigo_orenda' => $cobranca['codigo'] ?? null,
        ]);

        return true;
    }

    /**
     * Cria um pedido com seus itens e opções de grade.
     *
     * @param array $data {
     *   cli_id, uni_id, con_id,
     *   frete_tipo   (3=delivery, 4=retirar, 6=cardapio digital),
     *   frete_preco,
     *   pagamento_metodo,
     *   pagamento_troco,
     *   obs,
     *   endereco_cep, endereco_logradouro, endereco_numero,
     *   endereco_complemento, endereco_bairro, endereco_cidade, endereco_estado,
     *   cupom_id,
     *   desconto,
     *   itens: [
     *     {
     *       pro_id, titulo, preco_unitario, qtd, embalagem, obs,
     *       grades: [
     *         { prg_id, titulo, descricao, tipo, min, gratis, max, obrigatoria,
     *           opcoes: [{ pgo_id, titulo, qtd, preco, embalagem }]
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public static function create(array $data): array
    {
        Db::beginTransaction();
        try {
            // ── 1. Validações básicas ─────────────────────────────────────────
            if (empty($data['itens'])) {
                Db::rollBack();
                return ['status' => 400, 'message' => 'O carrinho está vazio.'];
            }

            // ── 2. Valida se a unidade pode receber pedidos ───────────────────
            $uniId = (int)($data['uni_id'] ?? 0);
            if ($uniId > 0) {
                $unidade = Db::table('tb_unidades')
                    ->where('uni_id', $uniId)
                    ->where('uni_excluido', 0)
                    ->first();

                if (!$unidade) {
                    Db::rollBack();
                    return ['status' => 400, 'message' => 'Unidade não encontrada.'];
                }

                // Verifica horário de funcionamento (cardápio digital ou delivery)
                $todayWeekDay = (int)date('N');
                $isCardapio = (int)($data['frete_tipo'] ?? 0) === 6;

                if ($isCardapio) {
                    $turno = Db::table('tb_horarios')
                        ->where('hor_inicio', '<=', Db::raw('TIME(NOW())'))
                        ->where('hor_fim', '>=', Db::raw('TIME(NOW())'))
                        ->where('hor_dia', $todayWeekDay)
                        ->where('hor_ativo', 1)
                        ->where('hor_excluido', 0)
                        ->where('hor_uni_id', $uniId)
                        ->first();
                } else {
                    $turno = Db::table('tb_horarios_entregas_rel_unidades')
                        ->where('heu_inicio', '<=', Db::raw('TIME(NOW())'))
                        ->where('heu_fim', '>=', Db::raw('TIME(NOW())'))
                        ->where('heu_dia', $todayWeekDay)
                        ->where('heu_ativo', 1)
                        ->where('heu_excluido', 0)
                        ->where('heu_uni_id', $uniId)
                        ->first();
                }

                $dentroHorario = $turno !== null;
                $isOnline = (int)($unidade->uni_online ?? 0);
                $pedidoOffline = (int)($unidade->uni_pedidooffline ?? 0);

                // Aceita pedidos se: dentro do horário E (online OU permite offline)
                $podeAceitar = $dentroHorario && ($isOnline === 1 || $pedidoOffline === 1);

                if (!$podeAceitar) {
                    Db::rollBack();
                    $motivo = !$dentroHorario
                        ? 'A unidade está fora do horário de funcionamento.'
                        : 'A unidade não pode receber pedidos no momento.';
                    return ['status' => 400, 'message' => $motivo];
                }
            }

            // ── 3. Calcula subtotal a partir dos itens recebidos ──────────────
            $subtotal = 0;
            foreach ($data['itens'] as $item) {
                $subtotal += ($item['preco_unitario'] ?? 0) * ($item['qtd'] ?? 1);
            }

            // ── 4. Valor mínimo (opcional, se enviado) ────────────────────────
            $valorMinimo = (float)($data['valor_minimo'] ?? 0);
            if ($valorMinimo > 0 && $subtotal < $valorMinimo) {
                Db::rollBack();
                return [
                    'status'  => 400,
                    'message' => sprintf(
                        'O valor mínimo do pedido é R$ %.2f. Seu subtotal é R$ %.2f.',
                        $valorMinimo,
                        $subtotal
                    ),
                ];
            }

            $cupomId = $data['cupom_id'] ?? null;
            $desconto   = 0;

            if ($cupomId) {
                $cupomRecord = Db::table('tb_cupons')->where('cup_id', $cupomId)->first();
                if (!$cupomRecord) {
                    Db::rollBack();
                    return ['status' => 400, 'message' => 'Cupom não encontrado.', 'invalid_coupon' => true];
                }

                $validaCupom = self::validateCoupon($cupomRecord->cup_cupom, (int)($data['cli_id'] ?? 0), $subtotal);
                
                if ($validaCupom['status'] !== 200) {
                    Db::rollBack();
                    return ['status' => 400, 'message' => $validaCupom['message'], 'invalid_coupon' => true];
                }
                
                $desconto = (float)$validaCupom['desconto'];
            }

            // ── 5. Grava o cabeçalho do pedido ────────────────────────────────
            $freteTipo  = (int)($data['frete_tipo']  ?? 3);  // 3=delivery, 4=retirar, 6=cardápio
            $fretePreco = (float)($data['frete_preco'] ?? 0);
            $total = $subtotal - $desconto + $fretePreco;


            $pedido = new self();
            $pedido->ped_cli_id            = $data['cli_id'] ?? null;
            $pedido->ped_uni_id            = $data['uni_id'] ?? null;
            $pedido->ped_cup_id            = $data['cupom_id'] ?? null;
            $pedido->ped_frt_id            = $freteTipo;
            $pedido->ped_pse_id            = 6;  // 6 = pedido recebido
            $pedido->ped_pag_id            = $data['pagamento_metodo'] ?? null;
            $pedido->ped_troco             = $data['pagamento_troco']  ?? null;
            $pedido->ped_subtotal          = $subtotal;
            $pedido->ped_desconto          = $desconto;
            $pedido->ped_fretepreco        = $fretePreco;
            $pedido->ped_total             = $total;
            // Quantidade total de itens (soma das qtds dos produtos)
            $pedido->ped_qtd               = array_sum(array_map(
                fn($i) => (int)($i['qtd'] ?? 0),
                $data['itens'] ?? []
            ));
            $pedido->ped_obs               = $data['obs'] ?? '';
            // Endereço de entrega
            $pedido->ped_cep               = $data['endereco_cep']         ?? null;
            $pedido->ped_logradouro        = $data['endereco_logradouro']  ?? null;
            $pedido->ped_numero            = $data['endereco_numero']      ?? null;
            $pedido->ped_complemento       = $data['endereco_complemento'] ?? null;
            $pedido->ped_bairro            = $data['endereco_bairro']      ?? null;
            $pedido->ped_cidade            = $data['endereco_cidade']      ?? null;
            $pedido->ped_estado            = $data['endereco_estado']      ?? null;
            $pedido->ped_cadastro          = date('Y-m-d H:i:s');
            $pedido->ped_ativo             = 1;
            $pedido->ped_psp_id            = 1;  // Status de pagamento (1 = Aguardando pagamento)
            $pedido->ped_excluido          = 0;
            $pedido->save();

            $pedId = $pedido->ped_id;

            // ── 6. Grava log inicial de situação ──────────────────────────────
            Db::table('tb_pedidos_log_situacao_entrega')->insert([
                'ple_ped_id'  => $pedId,
                'ple_pse_id'  => 6,  // Pedido recebido
                'ple_cadastro' => date('Y-m-d H:i:s'),
            ]);

            // Grava log inicial de situação de pagamento
            Db::table('tb_pedidos_log_situacao_pagamento')->insert([
                'plp_ped_id'  => $pedId,
                'plp_psp_id'  => 1,  // Aguardando
            ]);

            // ── 7. Grava cada item + grades do pedido ─────────────────────────
            foreach ($data['itens'] as $item) {
                $pep = Db::table('tb_pedidos_produtos')->insertGetId([
                    'pep_ped_id'       => $pedId,
                    'pep_pro_id'       => $item['pro_id'],
                    'pep_preco'        => $item['preco_unitario'] ?? 0,
                    'pep_qtd'          => $item['qtd']            ?? 1,
                    'pep_pem_custo'    => $item['embalagem']      ?? 0,
                    'pep_obs'          => $item['obs']            ?? null,
                    'pep_cadastro'     => date('Y-m-d H:i:s'),
                    'pep_ativo'        => 1,
                    'pep_excluido'     => 0,
                ]);

                // Grades / opções escolhidas
                if (!empty($item['grades'])) {
                    foreach ($item['grades'] as $grade) {
                        if (!empty($grade['opcoes'])) {
                            foreach ($grade['opcoes'] as $opcao) {
                                $qtdOpcao = (int)($opcao['qtd'] ?? 0);
                                if ($qtdOpcao <= 0) continue;

                                Db::table('tb_pedidos_produtos_rel_grades_opcoes')->insert([
                                    'ppo_pep_id'              => $pep,
                                    'ppo_prg_id'              => $grade['prg_id']      ?? null,
                                    'ppo_prg_titulo'          => $grade['titulo']      ?? null,
                                    'ppo_prr_prg_descricao'   => $grade['descricao']   ?? null,
                                    'ppo_prr_prg_pgt_id'      => $grade['tipo']        ?? null,
                                    'ppo_prr_prg_qtd_min'     => $grade['min']         ?? null,
                                    'ppo_prr_prg_qtd_gratis'  => $grade['gratis']      ?? null,
                                    'ppo_prr_prg_qtd_max'     => $grade['max']         ?? null,
                                    'ppo_prr_prg_obrigatoria' => $grade['obrigatoria'] ?? null,
                                    'ppo_pgo_id'              => $opcao['pgo_id']      ?? null,
                                    'ppo_pgo_titulo'          => $opcao['titulo']      ?? null,
                                    'ppo_prp_pgo_min'         => $opcao['min']         ?? null,
                                    'ppo_prp_pgo_max'         => $opcao['max']         ?? null,
                                    'ppo_qtd'                 => $qtdOpcao,
                                    'ppo_prp_preco'           => $opcao['preco']       ?? 0,
                                    'ppo_prp_embalagem'       => $opcao['embalagem']   ?? 0,
                                    'ppo_excluido'            => 0,
                                ]);
                            }
                        }
                    }
                }
            }

            $paymentMethodId = (int)($data['pagamento_metodo'] ?? 0);
            $paymentMethod = Db::table('tb_pagamentos_metodos')
                ->where('pag_id', $paymentMethodId)
                ->first();
            $paymentTitle = $paymentMethod->pag_titulo ?? '';

            $isCard = $paymentMethodId === 18 || preg_match('/cart[aã]o|credito/i', (string)$paymentTitle);
            $isPix = in_array($paymentMethodId, [3, 17], true) || preg_match('/pix/i', (string)$paymentTitle);

            if ($isCard) {
                self::generateOrendaCard($pedId, $total, $data);
            } elseif ($isPix) {
                self::generateOrendaPix($pedId, $total, $data);
            }

            Db::commit();

            return [
                'status'  => 201,
                'message' => 'Pedido realizado com sucesso',
                'data'    => ['ped_id' => $pedId, 'total' => $total],
            ];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    // ─── checkPagamentoStatus ─────────────────────────────────────────────

    public static function checkPagamentoStatus(int $pedId): array
    {
        try {
            $pedido = Db::table('tb_pedidos')
                ->where('ped_id', $pedId)
                ->where('ped_excluido', 0)
                ->first();

            if (!$pedido) {
                return ['status' => 404, 'message' => 'Pedido não encontrado.'];
            }

            $codigo = $pedido->ped_codigo_orenda ?? null;
            if (!$codigo) {
                return ['status' => 400, 'message' => 'Pedido sem codigo Orenda.'];
            }

            // Usa credenciais da unidade
            $unidade = Db::table('tb_unidades')->where('uni_id', $pedido->ped_uni_id)->first();
            $orendaId = $unidade->uni_orenda_id ?? 1239;
            $orendaToken = $unidade->uni_orenda_token ?? "12I175258e546673S723524s6780C77e7942j72j1149e96C8276s59R6428S15s4110e12s7269I";

            $url = "https://www.orendapay.com.br/api/v1/cobranca/{$codigo}";

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                "x-ID:" . $orendaId,
                "x-Token:" . $orendaToken,
                "Content-Type: application/json"
            ]);

            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpcode !== 200) {
                return ['status' => 400, 'message' => 'Erro ao consultar pagamento.', 'data' => $response];
            }

            $respData = json_decode($response, true);
            $cobranca = $respData['cobrancas'][0] ?? null;
            if (!$cobranca) {
                return ['status' => 404, 'message' => 'Cobranca nao encontrada.'];
            }

            $situacao = $cobranca['situacao'] ?? null;
            $cardStatus = $cobranca['situacao_cartao'] ?? null;
            if ($cardStatus && strtoupper((string)$cardStatus) !== 'OK') {
                $situacao = 'cancelado';
            }
            $pspId = 1;
            if ($situacao === 'pago') {
                $pspId = 3;
            } elseif ($situacao === 'cancelado' || $situacao === 'estorno') {
                $pspId = 7;
            } elseif ($situacao === 'vencido') {
                $pspId = 10;
            }

            $ultimoLog = Db::table('tb_pedidos_log_situacao_pagamento')
                ->where('plp_ped_id', $pedId)
                ->orderBy('plp_id', 'desc')
                ->first();
            $ultimoPsp = $ultimoLog->plp_psp_id ?? null;

            if ((int)$ultimoPsp !== $pspId) {
                Db::table('tb_pedidos_log_situacao_pagamento')->insert([
                    'plp_pagseguro_notificacao' => '',
                    'plp_pagseguro_tipo' => '',
                    'plp_ped_id' => $pedId,
                    'plp_psp_id' => $pspId,
                ]);
            }

            $updatePedido = ['ped_psp_id' => $pspId];
            if ($situacao === 'vencido') {
                $updatePedido['ped_pse_id'] = 5;

                $ultimoEntrega = Db::table('tb_pedidos_log_situacao_entrega')
                    ->where('ple_ped_id', $pedId)
                    ->orderBy('ple_id', 'desc')
                    ->first();
                $ultimoPse = $ultimoEntrega->ple_pse_id ?? null;
                if ((int)$ultimoPse !== 5) {
                    Db::table('tb_pedidos_log_situacao_entrega')->insert([
                        'ple_ped_id' => $pedId,
                        'ple_pse_id' => 5,
                    ]);
                }
            }

            Db::table('tb_pedidos')->where('ped_id', $pedId)->update($updatePedido);

            return [
                'status' => 200,
                'message' => 'payment_status',
                'data' => [
                    'psp_id' => $pspId,
                    'situacao' => $situacao,
                    'codigo' => $codigo,
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function search($id): array
    {
        try {
            $row = Db::table('tb_pedidos as p')
                ->leftJoin('tb_clientes as c', 'c.cli_id', '=', 'p.ped_cli_id')
                ->leftJoin('tb_pedidos_situacao_entrega as pse', 'pse.pse_id', '=', 'p.ped_pse_id')
                ->leftJoin('tb_pagamentos_metodos as pag', 'pag.pag_id', '=', 'p.ped_pag_id')
                ->select(
                    'p.*',
                    'c.cli_nome',
                    'c.cli_telefone1',
                    'c.cli_telefone2',
                    'c.cli_email',
                    'pse.pse_titulo',
                    'pag.pag_titulo'
                )
                ->where('p.ped_id', $id)
                ->where('p.ped_excluido', 0)
                ->first();

            if (!$row) {
                return ['status' => 404, 'message' => 'Pedido não encontrado'];
            }

            $data = (array) $row;

            $itens = Db::table('tb_pedidos_produtos as pp')
                ->leftJoin('tb_produtos as pro', 'pro.pro_id', '=', 'pp.pep_pro_id')
                ->select('pp.*', 'pro.pro_titulo')
                ->where('pp.pep_ped_id', $id)
                ->where('pp.pep_excluido', 0)
                ->get()
                ->toArray();

            $data['itens'] = array_map(fn($i) => (array) $i, $itens);

            return ['status' => 200, 'data' => $data];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
    public static function updatePagamentoCallback(array $data): array
    {
        try {
            $pedId = 0;
            if (!empty($data['seu_codigo']) && is_numeric($data['seu_codigo'])) {
                $pedId = (int)$data['seu_codigo'];
            } elseif (!empty($data['codigo_custom'])) {
                $parts = explode('-', (string)$data['codigo_custom']);
                if (!empty($parts[0]) && is_numeric($parts[0])) {
                    $pedId = (int)$parts[0];
                } elseif (is_numeric($data['codigo_custom'])) {
                    $pedId = (int)$data['codigo_custom'];
                }
            }

            if ($pedId <= 0) {
                return ['status' => 400, 'message' => 'Pedido nao identificado.'];
            }

            $pedido = Db::table('tb_pedidos')
                ->where('ped_id', $pedId)
                ->where('ped_excluido', 0)
                ->first();

            if (!$pedido) {
                return ['status' => 404, 'message' => 'Pedido nao encontrado.'];
            }

            $situacao = $data['situacao'] ?? null;
            $pspId = 1;
            if ($situacao === 'pago') {
                $pspId = 3;
            } elseif ($situacao === 'cancelado' || $situacao === 'estorno') {
                $pspId = 7;
            } elseif ($situacao === 'vencido') {
                $pspId = 10;
            }

            $ultimoLog = Db::table('tb_pedidos_log_situacao_pagamento')
                ->where('plp_ped_id', $pedId)
                ->orderBy('plp_id', 'desc')
                ->first();
            $ultimoPsp = $ultimoLog->plp_psp_id ?? null;

            if ((int)$ultimoPsp !== $pspId) {
                Db::table('tb_pedidos_log_situacao_pagamento')->insert([
                    'plp_pagseguro_notificacao' => '',
                    'plp_pagseguro_tipo' => '',
                    'plp_ped_id' => $pedId,
                    'plp_psp_id' => $pspId,
                ]);
            }

            $update = ['ped_psp_id' => $pspId];

            if ($situacao === 'vencido') {
                $update['ped_pse_id'] = 5;

                $ultimoEntrega = Db::table('tb_pedidos_log_situacao_entrega')
                    ->where('ple_ped_id', $pedId)
                    ->orderBy('ple_id', 'desc')
                    ->first();
                $ultimoPse = $ultimoEntrega->ple_pse_id ?? null;
                if ((int)$ultimoPse !== 5) {
                    Db::table('tb_pedidos_log_situacao_entrega')->insert([
                        'ple_ped_id' => $pedId,
                        'ple_pse_id' => 5,
                    ]);
                }
            }

            if (!empty($data['numero'])) {
                $update['ped_codigo_orenda'] = $data['numero'];
            }

            Db::table('tb_pedidos')->where('ped_id', $pedId)->update($update);

            return [
                'status' => 200,
                'message' => 'payment_updated',
                'data' => [
                    'ped_id' => $pedId,
                    'psp_id' => $pspId,
                    'situacao' => $situacao,
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    // ─── listByClient ─────────────────────────────────────────────────────────

    /**
     * Lista pedidos de um cliente com paginação.
     */
    public static function listByClient(int $cliId, int $page = 1): array
    {
        try {
            self::cancelExpiredPending(); // Atualiza pagamentos expirados antes de listar

            $p = self::query()
                ->where('ped_cli_id', $cliId)
                ->where('ped_excluido', 0)
                ->orderByDesc('ped_id')
                ->paginate(10, ['*'], 'page', $page);

            $rows = $p->items();

            return [
                'status' => 200,
                'pagination' => [
                    'current_page'  => $p->currentPage(),
                    'last_page'     => $p->lastPage(),
                    'total'         => $p->total(),
                    'per_page'      => $p->perPage(),
                    'next_page_url' => $p->nextPageUrl() ? $p->currentPage() + 1 : null,
                    'prev_page_url' => $p->previousPageUrl() ? $p->currentPage() - 1 : null,
                ],
                'data' => array_map(
                    fn($r) => is_array($r) ? $r : (method_exists($r, 'toArray') ? $r->toArray() : (array)$r),
                    $rows
                ),
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    // ─── getById ──────────────────────────────────────────────────────────────

    /**
     * Busca um pedido completo com seus itens e grades.
     */
    public static function getById(int $pedId, ?int $cliId = null): array
    {
        try {
            self::cancelExpiredPending(); // Atualiza pagamentos expirados

            $q = self::query()
                ->where('ped_id', $pedId)
                ->where('ped_excluido', 0);

            if ($cliId !== null) {
                $q->where('ped_cli_id', $cliId);
            }

            $pedido = $q->first();

            if (!$pedido) {
                return ['status' => 404, 'message' => 'Pedido não encontrado.'];
            }

            $pedArray = is_array($pedido) ? $pedido : $pedido->toArray();

            // Situação de entrega atual
            $situacaoEntrega = Db::table('tb_pedidos_situacao_entrega')
                ->where('pse_id', $pedArray['ped_pse_id'] ?? 0)
                ->first();
            $pedArray['situacao_entrega'] = $situacaoEntrega ? (array)$situacaoEntrega : null;

            // Situação de pagamento (buscar último log)
            $ultimoLogPgto = Db::table('tb_pedidos_log_situacao_pagamento')
                ->where('plp_ped_id', $pedId)
                ->orderBy('plp_id', 'desc')
                ->first();
            if ($ultimoLogPgto) {
                $situacaoPgto = Db::table('tb_pedidos_situacoes_pagamento')
                    ->where('psp_id', $ultimoLogPgto->plp_psp_id ?? 0)
                    ->first();
                $pedArray['situacao_pagamento'] = $situacaoPgto ? (array)$situacaoPgto : null;
                $pedArray['psp_id'] = $ultimoLogPgto->plp_psp_id ?? null;
            } else {
                // Se não existe log, considerar "Aguardando pagamento"
                $pedArray['situacao_pagamento'] = ['psp_id' => 1, 'psp_titulo' => 'Aguardando pagamento'];
                $pedArray['psp_id'] = 1;
            }

            // Histórico de entrega
            $pedArray['historico_entrega'] = Db::table('tb_pedidos_log_situacao_entrega as ple')
                ->leftJoin('tb_pedidos_situacao_entrega as pse', 'ple.ple_pse_id', '=', 'pse.pse_id')
                ->where('ple.ple_ped_id', $pedId)
                ->orderBy('ple.ple_cadastro', 'asc')
                ->select('ple.*', 'pse.pse_titulo', 'pse.pse_descricao')
                ->get()
                ->map(fn($r) => (array)$r)
                ->toArray();

            // Busca produtos do pedido
            $produtos = Db::table('tb_pedidos_produtos')
                ->where('pep_ped_id', $pedId)
                ->where('pep_excluido', 0)
                ->get()
                ->toArray();

            foreach ($produtos as &$produto) {
                $produto = (array)$produto;

                // Buscar o nome do produto
                $pro = Db::table('tb_produtos')->where('pro_id', $produto['pep_pro_id'])->first();
                $produto['produto'] = $pro ? (array)$pro : null;

                // Busca grades de cada produto
                $produto['grades'] = Db::table('tb_pedidos_produtos_rel_grades_opcoes')
                    ->where('ppo_pep_id', $produto['pep_id'])
                    ->where('ppo_excluido', 0)
                    ->get()
                    ->map(fn($g) => (array)$g)
                    ->toArray();
            }

            $pedArray['itens'] = $produtos;

            return ['status' => 200, 'data' => $pedArray];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function updateStatusEntrega($id, $pseId): array
    {
        Db::beginTransaction();
        try {
            $pedido = Db::table('tb_pedidos')
                ->where('ped_id', $id)
                ->where('ped_excluido', 0)
                ->first();

            if (!$pedido) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Pedido não encontrado'];
            }

            $update = ['ped_pse_id' => (int)$pseId];

            if ((int)$pseId === 5) {
                $update['ped_psp_id'] = 10;

                if (!empty($pedido->ped_codigo_orenda)) {
                    $unidade = Db::table('tb_unidades')
                        ->where('uni_id', $pedido->ped_uni_id)
                        ->first();

                    if ($unidade) {
                        $cancelResult = self::cancelOrendaCharge(
                            (string)$pedido->ped_codigo_orenda,
                            (array)$unidade
                        );

                        if (!in_array((int)($cancelResult['status'] ?? 0), [200, 201, 204], true)) {
                            Db::rollBack();
                            return [
                                'status' => 400,
                                'message' => $cancelResult['message'] ?? 'Nao foi possivel cancelar a cobranca.'
                            ];
                        }
                    }
                }
            }

            Db::table('tb_pedidos')
                ->where('ped_id', $id)
                ->update($update);

            Db::table('tb_pedidos_log_situacao_entrega')->insert([
                'ple_ped_id' => $id,
                'ple_pse_id' => (int)$pseId,
                'ple_cadastro' => date('Y-m-d H:i:s')
            ]);

            if ((int)$pseId === 5) {
                Db::table('tb_pedidos_log_situacao_pagamento')->insert([
                    'plp_ped_id' => $id,
                    'plp_psp_id' => 10,
                    'plp_pagseguro_notificacao' => '',
                    'plp_pagseguro_tipo' => '',
                ]);
            }

            Db::commit();
            return ['status' => 200, 'message' => 'Status atualizado'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao atualizar status: ' . $e->getMessage()];
        }
    }

    /**
     * @param int $conId
     * @param array|null $allowedUniIds null = sem restrição (admin); array limita aos uni_ids
     */
    public static function countsByStatus($conId, ?array $allowedUniIds = null): array
    {
        try {
            $today = date('Y-m-d');

            $query = Db::table('tb_pedidos as p')
                ->join('tb_unidades as u', 'u.uni_id', '=', 'p.ped_uni_id')
                ->selectRaw('p.ped_pse_id, COUNT(*) as total')
                ->where('p.ped_excluido', 0)
                ->where('u.uni_con_id', (int) $conId)
                ->where(function($q) use ($today) {
                    $q->whereRaw("DATE(p.ped_cadastro) = ?", [$today])
                      ->orWhereRaw("DATE(p.ped_agendamento) = ?", [$today]);
                });

            if (is_array($allowedUniIds)) {
                if (count($allowedUniIds) === 0) {
                    return ['status' => 200, 'data' => []];
                }
                $query->whereIn('p.ped_uni_id', $allowedUniIds);
            }

            $counts = $query
                ->groupBy('p.ped_pse_id')
                ->pluck('total', 'ped_pse_id')
                ->all();

            return ['status' => 200, 'data' => $counts];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
    // ─── updateStatus ─────────────────────────────────────────────────────────

    /**
     * Cancela pedidos que estao pendentes (sem pagamento) ha mais de 10 minutos.
     * Ignora pedidos cujo metodo de pagamento seja dinheiro em especie.
     */
    public static function cancelExpiredPending(): array
    {
        try {
            $limitDate = date('Y-m-d H:i:s', strtotime('-10 minutes'));
            $limitCashDate = date('Y-m-d H:i:s', strtotime('-1 day'));

            // Buscar IDs dos pagamentos que são dinheiro (para não cancelar)
            $dinheiroIds = Db::table('tb_pagamentos_metodos')
                ->where('pag_titulo', 'like', '%dinheiro%')
                ->pluck('pag_id')
                ->toArray();

            $query = Db::table('tb_pedidos')
                ->where('ped_cadastro', '<=', $limitDate)
                ->where('ped_pse_id', '!=', 5) // Nao cancelado
                ->where('ped_excluido', 0)
                ->where(function ($q) {
                    $q->where('ped_psp_id', 1) // 1 = Aguardando pagamento
                        ->orWhereNull('ped_psp_id');
                });

            if (!empty($dinheiroIds)) {
                $query->whereNotIn('ped_pag_id', $dinheiroIds);
            }

            $pedidos = $query->get();

            $cancelPedidos = function ($rows) {
                $canceledCount = 0;
                foreach ($rows as $p) {
                    // Efetiva o cancelamento
                    Db::table('tb_pedidos')->where('ped_id', $p->ped_id)->update([
                        'ped_pse_id' => 5, // Status de entrega: Cancelado
                        'ped_psp_id' => 10 // Status de pgto: Vencido/Cancelado
                    ]);

                    // Log entrega
                    Db::table('tb_pedidos_log_situacao_entrega')->insert([
                        'ple_ped_id' => $p->ped_id,
                        'ple_pse_id' => 5,
                        'ple_cadastro' => date('Y-m-d H:i:s')
                    ]);

                    // Log pagamento
                    Db::table('tb_pedidos_log_situacao_pagamento')->insert([
                        'plp_ped_id' => $p->ped_id,
                        'plp_psp_id' => 10,
                        'plp_pagseguro_notificacao' => '',
                        'plp_pagseguro_tipo' => '',
                    ]);

                    $canceledCount++;
                }

                return $canceledCount;
            };
            $canceledCount = $cancelPedidos($pedidos);

            if (!empty($dinheiroIds)) {
                $cashQuery = Db::table('tb_pedidos')
                    ->where('ped_cadastro', '<=', $limitCashDate)
                    ->where('ped_pse_id', '!=', 5)
                    ->where('ped_excluido', 0)
                    ->whereIn('ped_pag_id', $dinheiroIds)
                    ->where(function ($q) {
                        $q->where('ped_psp_id', 1)
                            ->orWhereNull('ped_psp_id');
                    });

                $cashPedidos = $cashQuery->get();
                $canceledCount += $cancelPedidos($cashPedidos);
            }

            return ['status' => 200, 'message' => "{$canceledCount} pedidos cancelados por tempo limite."];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
    public static function updateStatus(int $pedId, int $pspId): array
    {
        Db::beginTransaction();
        try {
            $pedido = self::query()
                ->where('ped_id', $pedId)
                ->where('ped_excluido', 0)
                ->first();

            if (!$pedido) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Pedido não encontrado.'];
            }

            $pedido->ped_psp_id = $pspId;
            $pedido->save();

            // Registra no log de situação
            Db::table('tb_pedidos_log_situacao_entrega')->insert([
                'ple_ped_id'   => $pedId,
                'ple_pse_id'   => $pspId,
                'ple_cadastro' => date('Y-m-d H:i:s'),
            ]);

            Db::commit();

            return ['status' => 200, 'message' => 'Situação atualizada com sucesso.'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    private static function cancelOrendaCharge(string $codigoCobranca, array $unidade): array
    {
        $orendaId = $unidade['uni_orenda_id'] ?? null;
        $orendaToken = $unidade['uni_orenda_token'] ?? null;

        if (empty($orendaId) || empty($orendaToken)) {
            return ['status' => 400, 'message' => 'Credenciais OrendaPay nao configuradas.'];
        }

        $url = "https://www.orendapay.com.br/api/v1/cobranca/{$codigoCobranca}/cancelar";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "x-ID:" . $orendaId,
            "x-Token:" . $orendaToken,
            "Content-Type: application/json"
        ]);

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            return ['status' => 500, 'message' => 'Erro ao cancelar cobranca: ' . $curlError];
        }

        if (!in_array($httpcode, [200, 201, 204], true)) {
            return ['status' => $httpcode, 'message' => 'Erro ao cancelar cobranca.', 'data' => $response];
        }

        return ['status' => $httpcode, 'message' => 'Cobranca cancelada.'];
    }

    public static function updatePayment(int $pedId, array $data): array
    {
        Db::beginTransaction();
        try {
            $pedido = Db::table('tb_pedidos')
                ->where('ped_id', $pedId)
                ->where('ped_excluido', 0)
                ->first();

            if (!$pedido) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Pedido nao encontrado.'];
            }

            if ((int)$pedido->ped_pse_id === 5) {
                Db::rollBack();
                return ['status' => 400, 'message' => 'Pedido cancelado.'];
            }

            if (in_array((int)$pedido->ped_psp_id, [3, 4], true)) {
                Db::rollBack();
                return ['status' => 400, 'message' => 'Pedido ja pago.'];
            }

            $paymentMethodId = (int)($data['pagamento_metodo'] ?? 0);
            if (!$paymentMethodId) {
                Db::rollBack();
                return ['status' => 400, 'message' => 'Informe o metodo de pagamento.'];
            }

            $paymentMethod = Db::table('tb_pagamentos_metodos')
                ->where('pag_id', $paymentMethodId)
                ->where('pag_ativo', 1)
                ->where('pag_excluido', 0)
                ->first();

            if (!$paymentMethod) {
                Db::rollBack();
                return ['status' => 400, 'message' => 'Metodo de pagamento invalido.'];
            }

            $paymentTitle = $paymentMethod->pag_titulo ?? '';
            $isCard = $paymentMethodId === 18 || preg_match('/cart[aã]o|credito/i', (string)$paymentTitle);
            $isPix = in_array($paymentMethodId, [3, 17], true) || preg_match('/pix/i', (string)$paymentTitle);

            if (!empty($pedido->ped_codigo_orenda)) {
                $unidade = Db::table('tb_unidades')
                    ->where('uni_id', $pedido->ped_uni_id)
                    ->first();

                if (!$unidade) {
                    Db::rollBack();
                    return ['status' => 400, 'message' => 'Unidade nao encontrada.'];
                }

                $cancelResult = self::cancelOrendaCharge(
                    (string)$pedido->ped_codigo_orenda,
                    (array)$unidade
                );

                if (!in_array((int)($cancelResult['status'] ?? 0), [200, 201, 204], true)) {
                    Db::rollBack();
                    return [
                        'status' => 400,
                        'message' => $cancelResult['message'] ?? 'Nao foi possivel cancelar a cobranca anterior.'
                    ];
                }
            }

            $update = [
                'ped_pag_id' => $paymentMethodId,
                'ped_troco' => $data['pagamento_troco'] ?? null,
                'ped_psp_id' => 1,
                'ped_codigo_orenda' => null,
                'ped_pix_qrcode' => null,
                'ped_pix_chave' => null,
            ];

            Db::table('tb_pedidos')
                ->where('ped_id', $pedId)
                ->update($update);

            $ultimoLog = Db::table('tb_pedidos_log_situacao_pagamento')
                ->where('plp_ped_id', $pedId)
                ->orderBy('plp_id', 'desc')
                ->first();
            $ultimoPsp = $ultimoLog->plp_psp_id ?? null;

            if ((int)$ultimoPsp !== 1) {
                Db::table('tb_pedidos_log_situacao_pagamento')->insert([
                    'plp_pagseguro_notificacao' => '',
                    'plp_pagseguro_tipo' => '',
                    'plp_ped_id' => $pedId,
                    'plp_psp_id' => 1,
                ]);
            }

            $payload = [
                'uni_id' => $pedido->ped_uni_id,
                'cli_id' => $pedido->ped_cli_id,
                'endereco_logradouro' => $pedido->ped_logradouro,
                'endereco_numero' => $pedido->ped_numero,
                'endereco_cidade' => $pedido->ped_cidade,
                'endereco_estado' => $pedido->ped_estado,
                'endereco_cep' => $pedido->ped_cep,
                'cartao_numero' => $data['cartao_numero'] ?? null,
                'cartao_nome' => $data['cartao_nome'] ?? null,
                'cartao_validade' => $data['cartao_validade'] ?? null,
                'cartao_cvv' => $data['cartao_cvv'] ?? null,
                'cartao_parcelas' => $data['cartao_parcelas'] ?? 1,
            ];

            if ($isCard) {
                self::generateOrendaCard($pedId, (float)$pedido->ped_total, $payload);
            } elseif ($isPix) {
                self::generateOrendaPix($pedId, (float)$pedido->ped_total, $payload);
            }

            Db::commit();
            return ['status' => 200, 'message' => 'Metodo de pagamento atualizado.'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    // ─── validateCoupon ───────────────────────────────────────────────────────

    /**
     * Valida e calcula o desconto de um cupom para o carrinho atual.
     * Retorna o valor do desconto ou 0 se cupom inválido.
     */
    public static function validateCoupon(string $codigoCupom, int $cliId, float $subtotal): array
    {
        try {
            $cupom = Db::table('tb_cupons')
                ->where('cup_cupom', strtoupper($codigoCupom))
                ->where('cup_excluido', 0)
                ->where('cup_ativo', 1)
                ->first();

            if (!$cupom) {
                return ['status' => 404, 'message' => 'Cupom não encontrado ou inativo.', 'desconto' => 0];
            }

            $cupom = (array)$cupom;

            // Verifica valor mínimo
            if (!empty($cupom['cup_valor_minimo']) && $subtotal < (float)$cupom['cup_valor_minimo']) {
                return [
                    'status'   => 400,
                    'message'  => sprintf(
                        'Cupom válido para compras acima de R$ %.2f.',
                        $cupom['cup_valor_minimo']
                    ),
                    'desconto' => 0,
                ];
            }

            // Verifica limite de uso total
            $qtdUsado = Db::table('tb_pedidos')
                ->where('ped_cup_id', $cupom['cup_id'])
                ->whereIn('ped_psp_id', [1, 2, 3, 4])
                ->where('ped_excluido', 0)
                ->count();

            if (!empty($cupom['cup_qtd']) && $qtdUsado >= (int)$cupom['cup_qtd']) {
                return ['status' => 400, 'message' => 'Cupom atingiu o limite de uso.', 'desconto' => 0];
            }

            // Se uso único por cliente
            if ((int)($cupom['cup_unico'] ?? 0) === 1) {
                $jaUsou = Db::table('tb_pedidos')
                    ->where('ped_cup_id', $cupom['cup_id'])
                    ->where('ped_cli_id', $cliId)
                    // Verifica pedidos pendentes ou pagos (1,2,3,4).
                    // Se o pedido for Cancelado/Retornado (7, 10, etc), ele não entra na conta (estorno automático).
                    ->whereIn('ped_psp_id', [1, 2, 3, 4])
                    ->where('ped_excluido', 0)
                    ->exists();

                if ($jaUsou) {
                    return ['status' => 400, 'message' => 'Cupom já utilizado por este cliente.', 'desconto' => 0];
                }
            }

            // Calcula desconto
            $desconto = 0;
            if ((int)$cupom['cup_tipo'] === 1) {
                // Porcentagem
                $desconto = $subtotal * ((float)($cupom['cup_porcentagem'] ?? 0) / 100);
            } else {
                // Valor fixo
                $desconto = (float)($cupom['cup_valor'] ?? 0);
            }

            return [
                'status'   => 200,
                'message'  => 'Cupom válido!',
                'desconto' => round($desconto, 2),
                'cup_id'   => $cupom['cup_id'],
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage(), 'desconto' => 0];
        }
    }
}
