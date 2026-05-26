<?php

namespace App\model\entity;

use Functions\db\redis;

class dashboard
{
    /**
     * Retorna o dashboard completo consultando API externa
     * 
     * @param string|null $customStart
     * @param string|null $customEnd
     * @param int|null $clientId
     * @return array
     */
    public static function getDashboard(?string $customStart = null, ?string $customEnd = null, ?int $clientId = null): array
    {
        $url = "https://m95ji3ctbi.execute-api.us-east-1.amazonaws.com/dashboard";

        // Recuperar Token do Redis se houver clientId
        $token = null;
        if (class_exists('Functions\db\redis')) {
            // Tenta pegar o token associado ao cliente
            $token = redis::get('cognito_token_' . 7);
        }

        // Monta parametros da query string
        $queryParams = [];
        if ($clientId) {
            $queryParams['client_id'] = $clientId;
        }
        if ($customStart) {
            $queryParams['data_inicio'] = $customStart;
        }
        if ($customEnd) {
            $queryParams['data_fim'] = $customEnd;
        }

        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $headers = [
                'Content-Type: application/json'
            ];

            if ($token) {
                // Adiciona o IdToken no header Authorization
                // A maioria dos Authorizers Cognito aceita o token direto ou com Bearer
                $headers[] = 'Authorization: ' . $token;
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            // Se precisar enviar filtros no body ou query string, adicionar aqui
            // Por enquanto, apenas GET

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                throw new \Exception('Curl error: ' . curl_error($ch));
            }

            curl_close($ch);

            $data = json_decode($response, true);
            if ($httpCode !== 200) {
                // Tenta extrair mensagem de erro da resposta
                $msg = isset($data['message']) ? $data['message'] : 'Erro ao consultar dashboard externo.';

                // Se for 401, tenta renovar o token e tentar novamente
                if ($httpCode === 401) {
                    // Tentar renovar fazendo login novamente
                    // Supondo que o cognitoLogin usa as credenciais do .env ou constantes
                    $loginResult = \App\model\entity\cognitoLogin::login();

                    if (isset($loginResult['status']) && $loginResult['status'] === 200) {
                        // Login renovado com sucesso. Recuperar novo token.
                        $newToken = $loginResult['data']['token'] ?? null;

                        if ($newToken) {
                            // Atualiza header com novo token
                            $headers = [
                                'Content-Type: application/json',
                                'Authorization: ' . $newToken
                            ];

                            // Re-executa a requisicao
                            $chRetry = curl_init($url);
                            curl_setopt($chRetry, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($chRetry, CURLOPT_HTTPHEADER, $headers);

                            $responseRetry = curl_exec($chRetry);
                            $httpCodeRetry = curl_getinfo($chRetry, CURLINFO_HTTP_CODE);

                            if (curl_errno($chRetry)) {
                                error_log('Curl retry error: ' . curl_error($chRetry));
                            } else {
                                curl_close($chRetry);
                                $dataRetry = json_decode($responseRetry, true);

                                if ($httpCodeRetry === 200) {
                                    return [
                                        'status' => 200,
                                        'data' => $dataRetry
                                    ];
                                }
                            }
                            if (isset($chRetry) && is_resource($chRetry)) curl_close($chRetry);
                        }
                    }
                }

                return [
                    'status' => $httpCode,
                    'message' => $msg,
                    'details' => $data,
                    'debug_token_found' => !empty($token) // Para debug apenas
                ];
            }

            if (!$data) {
                return [
                    'status' => 500,
                    'message' => 'Resposta vazia da API externa.',
                    'data' => []
                ];
            }


            // Mapeia chaves para o formato esperado pelo frontend (Recharts)
            // Backend API retorna: vendas, pedidos
            // Frontend espera: valor, quantidade
            $periods = ['hoje', 'ultimos7Dias', 'esteMes', 'customizado'];
            foreach ($periods as $period) {
                if (isset($data[$period]) && isset($data[$period]['vendasEvolucao'])) {
                    foreach ($data[$period]['vendasEvolucao'] as &$item) {
                        if (!isset($item['valor']) && isset($item['vendas'])) {
                            $item['valor'] = $item['vendas'];
                        }
                        if (!isset($item['quantidade']) && isset($item['pedidos'])) {
                            $item['quantidade'] = $item['pedidos'];
                        }
                    }
                }
            }

            // O retorno da API externa já está no formato esperado
            return [
                'status' => 200,
                'data' => $data
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Gera insights automáticos com base nos dados do dashboard de hoje.
     */
    public static function getInsights(): array
    {
        $apiBase     = 'https://m95ji3ctbi.execute-api.us-east-1.amazonaws.com';
        $today       = date('Y-m-d');
        $firstOfMonth = date('Y-m-01');

        // Recupera token do Redis
        $token = null;
        if (class_exists('Functions\db\redis')) {
            $token = \Functions\db\redis::get('cognito_token_7');
        }

        $doGet = function (string $path, array $params = []) use ($apiBase, &$token): array {
            $url = $apiBase . $path;
            if (!empty($params)) $url .= '?' . http_build_query($params);
            $headers = ['Content-Type: application/json'];
            if ($token) $headers[] = 'Authorization: ' . $token;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            // Renova token em caso de 401
            if ($code === 401) {
                $login = \App\model\entity\cognitoLogin::login();
                $token = $login['data']['token'] ?? null;
                if ($token) {
                    $headers = ['Content-Type: application/json', 'Authorization: ' . $token];
                    $ch2 = curl_init($url);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
                    $body = curl_exec($ch2);
                    $code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                    curl_close($ch2);
                }
            }
            return ['code' => $code, 'data' => json_decode($body, true) ?? []];
        };

        $dashRes   = $doGet('/dashboard');
        $pendRes   = $doGet('/dashboard/pedidos-nao-finalizados', ['data_inicio' => $today, 'data_fim' => $today]);
        $resumoRes = $doGet('/dashboard/resumo-pedidos', ['data_inicio' => $firstOfMonth, 'data_fim' => $today]);

        if ($dashRes['code'] !== 200) {
            return ['status' => $dashRes['code'], 'message' => 'Erro ao buscar dados do dashboard.'];
        }

        $dashboard  = $dashRes['data'];
        $hoje       = $dashboard['hoje']         ?? null;
        $ultimos7   = $dashboard['ultimos7Dias'] ?? null;
        $esteMes    = $dashboard['esteMes']      ?? null;
        $pendingCount = ($pendRes['code'] === 200) ? (int)($pendRes['data']['total'] ?? 0) : 0;
        $topItem    = ($resumoRes['code'] === 200 && !empty($resumoRes['data']['topItens']))
            ? ($resumoRes['data']['topItens'][0] ?? null) : null;

        $insights = [];
        $seq = 1;

        // Pedidos Hoje
        if ($hoje && isset($hoje['kpis']['numeroPedidos'])) {
            $pedidosHoje = (int)($hoje['kpis']['numeroPedidos'] ?? 0);
            $receitaHoje = (float)($hoje['kpis']['receitaTotal'] ?? 0);
            if ($pedidosHoje > 0) {
                $insights[] = ['id' => (string)$seq++, 'title' => 'Pedidos Hoje',
                    'description' => "Você recebeu {$pedidosHoje} pedido(s) hoje, totalizando R$ " . number_format($receitaHoje, 2, ',', '.') . ".",
                    'type' => 'success'];
            } else {
                $insights[] = ['id' => (string)$seq++, 'title' => 'Sem Pedidos Hoje',
                    'description' => 'Ainda não foram registrados pedidos hoje. Considere ações de divulgação.',
                    'type' => 'warning'];
            }
        }

        // Pedidos Sem Pagamento
        if ($pendingCount > 0) {
            $insights[] = ['id' => (string)$seq++, 'title' => 'Pedidos Aguardando Pagamento',
                'description' => "{$pendingCount} pedido(s) ainda não foram pagos hoje. Considere contatar os clientes.",
                'type' => 'warning'];
        }

        // Produto Destaque do Mês
        if ($topItem) {
            $nome = $topItem['nome_produto'] ?? '';
            $qtd  = $topItem['total_quantidade'] ?? 0;
            $insights[] = ['id' => (string)$seq++, 'title' => 'Produto Destaque do Mês',
                'description' => "\"{$nome}\" é o item mais pedido este mês com {$qtd} unidade(s) vendidas.",
                'type' => 'opportunity'];
        }

        // Ticket Médio Semana vs Mês
        if ($ultimos7 && $esteMes) {
            $ticketSemana = (float)($ultimos7['kpis']['ticketMedio'] ?? 0);
            $ticketMes    = (float)($esteMes['kpis']['ticketMedio']  ?? 0);
            if ($ticketMes > 0 && $ticketSemana > $ticketMes * 1.1) {
                $diff = round((($ticketSemana - $ticketMes) / $ticketMes) * 100);
                $insights[] = ['id' => (string)$seq++, 'title' => 'Ticket Médio em Alta',
                    'description' => "O ticket médio dos últimos 7 dias (R$ " . number_format($ticketSemana, 2, ',', '.') . ") está {$diff}% acima da média mensal.",
                    'type' => 'success'];
            } elseif ($ticketMes > 0 && $ticketSemana < $ticketMes * 0.9) {
                $diff = round((($ticketMes - $ticketSemana) / $ticketMes) * 100);
                $insights[] = ['id' => (string)$seq++, 'title' => 'Ticket Médio Abaixo da Média',
                    'description' => "O ticket médio dos últimos 7 dias está {$diff}% abaixo da média mensal. Avalie promoções ou combos.",
                    'type' => 'warning'];
            }
        }

        // Novos Clientes Este Mês
        if ($esteMes && isset($esteMes['kpis']['novosClientes'])) {
            $novosClientes = (int)($esteMes['kpis']['novosClientes'] ?? 0);
            if ($novosClientes > 0) {
                $insights[] = ['id' => (string)$seq++, 'title' => 'Novos Clientes Este Mês',
                    'description' => "{$novosClientes} novo(s) cliente(s) fizeram seu primeiro pedido este mês.",
                    'type' => 'info'];
            }
        }

        return ['status' => 200, 'data' => ['insights' => $insights]];
    }
}
