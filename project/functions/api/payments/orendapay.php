<?php

namespace Functions\api\payments;


class orendapay
{    // URL do endpoint de criação de cobrança
    static $url = "https://www.orendapay.com.br/api/v1/cobranca";

    static public function create($data, $data2, $config, $id_payment, $email, $split): array
    {
        // var_dump("data", $data);
        // var_dump("data2", $data2);
        // var_dump("config", $config);
        // var_dump("id_payment", $id_payment);
        // var_dump("email", $email);
        // var_dump("split", $split);
        // var_dump("pai_payment_method", $data2['pai_payment_method']);
        // var_dump("email", $email);
        // var_dump("split", $split);
        //"par_payment_type": 4, //1 = mensal / 2 = trimestral / 3 = semestral / 4 = anual

        if ($data2['pai_payment_method'] == 1) {

            $data2['pai_payment_method'] = 'credit';

            $data['par_payment_type_recurrence'] = '0';
            if($data['par_payment_type'] != 4){
                // $data['pai_price'] = 35;
                $data['par_payment_type_recurrence'] = '1';
                $data['par_payment_type_installments'] = '12';
            }

        } else if ($data2['pai_payment_method'] == 2) {

            $data2['pai_payment_method'] = 'debit';

            $data['par_payment_type_recurrence'] = '0';
            if($data['par_payment_type'] != 4){
                // $data['pai_price'] = 35;
                $data['par_payment_type_recurrence'] = '1';
                $data['par_payment_type_installments'] = '12';
            }

        } else if ($data['pai_payment_method'] == 3) {

            $data2['pai_payment_method'] = 'pix';

            $data['par_payment_type_recurrence'] = '0';
            if($data['par_payment_type'] != 4){
                // $data['pai_price'] = 35;
                $data['par_payment_type_recurrence'] = '1';
                $data['par_payment_type_installments'] = '12';
            }

        } else if ($data['pai_payment_method'] == 4) {

            $data2['pai_payment_method'] = 'boleto';

            $data['par_payment_type_recurrence'] = '0';
            if($data['par_payment_type'] != 4){
                // $data['pai_price'] = 35;
                $data['par_payment_type_recurrence'] = '1';
                $data['par_payment_type_installments'] = '12';
            }

        } else {
            return array(
                'status' => 400,
                'message' => 'payment method not found'
            );
        }
        // var_dump("pai_payment_method 2", $data2['pai_payment_method']);

        // var_dump($data);
        // var_dump('id_payment', $id_payment);
        // Montagem do array de dados da cobrança
        $data = array(
            "valor" => $data2['pai_price'],
            "codigo_custom" => $id_payment."-".$data['pai_id'],
            "seu_codigo" =>  (string)$id_payment."-".(string)$data['pai_id'],
            "descricao" => $data['pai_description'],
            "vencimento" => date('d/m/Y', strtotime($data['pai_date'])),  // Data da primeira cobrança            "valor" => $data['pai_price'],
            "TIPO" => $data2['pai_payment_method'],               // Pagamento via Pix (se for 1 é credito, se for 2 é debito 3 é pix e 4 é boleto)
            "RECORRENCIA" => 0, //$data['par_payment_type_recurrence']
            "cliente_nome" => $data['ass_name'],
            "cliente_cpf_cnpj" => $data['ass_cpf'], //'111.222.333-44',
            "cliente_telefone" => $data['ass_phone1'],
            "cliente_email" => $data['ass_email'],
            "cliente_endereco" => $data['ass_address'],
            "cliente_cidade" => $data['ass_city'],
            "cliente_uf" => $data['ass_state'],
            "cliente_cep" => $data['ass_zipcode'],
            "NUMERO_PARCELAS"=> (int)$data['cardInstallments'] ?? 1,
            
            "ENVIAR_EMAIL" => "1",
            "ENVIO_IMEDIATO" => "1",

            // SPLIT: 30% para parceiro, 70% fica para a conta criadora
            "SPLIT" => array(
                array("percentual" => (float)$split, "email" => $email)
            ),

            // "URL_CALLBACK" => "https://3cd2-187-61-237-113.ngrok-free.app/callback",
            "URL_CALLBACK" => "https://api.organizadas.com.br/callback",

            // Campos do cartão (obrigatórios se TIPO=credit):
            "cartao_numero" => $data['cardNumber'],
            "cartao_nome" => $data['cardName'],
            "cartao_validade" => $data['expiryDate'],
            "cartao_codigo" => $data['cvv']
        );
        // Converte o array em JSON
        $json = json_encode($data);
        
        // Inicializa o cURL
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::$url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        // Cabeçalhos necessários
        $headers = array(
            "x-ID:" . $config['pac_api_id'],        // Substitua pelo seu x-ID
            "x-Token:" . $config['pac_api_key'],  // Substitua pelo seu x-Token
            "Content-Type: application/json"
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // Executa a requisição
        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Fecha a conexão
        curl_close($curl);

        // Decodifica o retorno JSON
        $retornoJSON = json_decode($response, true);

        // Exibe o resultado (para fins de teste)
        // echo "HTTP Code: " . $httpcode . "\n";
        // echo "Retorno: \n";
        // print_r($retornoJSON);
        return [
            'status' => $httpcode,
            'message' => $retornoJSON
        ];
    }

    static public function CreatePaymentSingle($data, $email, $split, $config, $typePayment, $dataCard = null): array{
        // var_dump((float)$data['pai_price']);
        // return [];
        $data = array(
            "valor" => (float)$data['pai_price'],//(float)$data['pai_price'],
            "codigo_custom" => $data['par_id']."-".$data['pai_id'],
            "seu_codigo" => (string) $data['par_id']."-". (string)$data['pai_id'],
            "descricao" => $data['pai_description'] ?? 'Pagamento de mensalidade',
            // "vencimento" => date('d/m/Y', strtotime($data['pai_date'])),  // Data da primeira cobrança            "valor" => $data['pai_price'],
            //dia de hoje mais um dia
            "vencimento" => date('d/m/Y', strtotime('+1 day')), 
            "TIPO" => $typePayment,          // Pagamento via Pix (se for 1 é credito, se for 2 é debito 3 é pix e 4 é boleto)
            "RECORRENCIA" => 0, //$data['par_payment_type_recurrence']
            "cliente_nome" => $data['ass_name'],
            "cliente_cpf_cnpj" => $dataCard['cpf'] ?? $data['ass_cpf'], //'111.222.333-44',
            "cliente_telefone" => $data['ass_phone1'],
            "cliente_email" => $data['ass_email'],
            "cliente_endereco" => $dataCard['address'] ?? $data['ass_address'],
            "cliente_cidade" => $dataCard['city'] ?? $data['ass_city'],
            "cliente_uf" => $dataCard['state'] ?? $data ['ass_state'],
            "cliente_cep" => $dataCard['cep'] ?? $data['ass_zipcode'],
            "NUMERO_PARCELAS"=> $data['installments'] ?? "1",
            
            "ENVIAR_EMAIL" => "1",
            "ENVIO_IMEDIATO" => "1",

            // SPLIT: 30% para parceiro, 70% fica para a conta criadora
            "SPLIT" => array(
                array("percentual" => (float)$split, "email" => $email)
            ),

            // "URL_CALLBACK" => "https://539f-179-190-184-204.ngrok-free.app/callback",
            "URL_CALLBACK" => "https://api.organizadas.com.br/callback",

            // Campos do cartão (obrigatórios se TIPO=credit):
            "cartao_numero" => $dataCard['cardNumber'] ?? null,
            "cartao_nome" => $dataCard['cardName'] ?? null,
            "cartao_validade" => $dataCard['expiryDate'] ?? null,
            "cartao_codigo" => $dataCard['cvv'] ?? null
        );
        // Converte o array em JSON
        $json = json_encode($data);

        // Inicializa o cURL
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::$url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        // Cabeçalhos necessários
        $headers = array(
            "x-ID:" . $config['pac_api_id'],        // Substitua pelo seu x-ID
            "x-Token:" . $config['pac_api_key'],  // Substitua pelo seu x-Token
            "Content-Type: application/json"
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // Executa a requisição
        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Fecha a conexão
        curl_close($curl);

        // Decodifica o retorno JSON
        $retornoJSON = json_decode($response, true);

        // Exibe o resultado (para fins de teste)
        // echo "HTTP Code: " . $httpcode . "\n";
        // echo "Retorno: \n";
        // print_r($retornoJSON);
        return [
            'status' => $httpcode,
            'message' => $retornoJSON
        ];
    }

    static public function cancelBilling(string $seu_codigo, string $cpf_cliente, $config): array
    {
        // Verifica se os dados foram fornecidos
        if (empty($seu_codigo) || empty($cpf_cliente)) {
            return [
                'status' => 400,
                'message' => 'Código da cobrança e CPF do cliente são obrigatórios'
            ];
        }

        // 1️⃣ Consultar cobranças para obter o código interno
        $urlConsulta = "https://www.orendapay.com.br/api/v1/cobrancas/status?DOC={$cpf_cliente}";

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $urlConsulta);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "x-ID:" . $config['pac_api_id'],        // Substitua pelo seu x-ID
            "x-Token:" . $config['pac_api_key'],  // Substitua pelo seu x-Token
            "Content-Type: application/json"
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($http_code !== 200) {
            return [
                'status' => $http_code,
                'message' => 'Erro ao consultar cobranças',
                'data' => $response
            ];
        }

        // Decodificar resposta JSON
        $data = json_decode($response, true);
        $cobrancas = $data['cobrancas'] ?? [];

        // Filtrar a cobrança pelo "seu_codigo"
        $cobrancaEncontrada = array_filter($cobrancas, function ($cobranca) use ($seu_codigo) {
            return $cobranca['seu_codigo'] === $seu_codigo;
        });

        if (empty($cobrancaEncontrada)) {
            return [
                'status' => 404,
                'message' => 'Cobrança não encontrada para esse código',
                'seu_codigo' => $seu_codigo
            ];
        }

        // Pegar o primeiro resultado (caso existam várias cobranças com o mesmo seu_codigo)
        $cobranca = array_values($cobrancaEncontrada)[0];
        $codigo_cobranca = $cobranca['codigo'];

        // 2️⃣ Cancelar cobrança com o código interno
        $urlCancelamento = "https://www.orendapay.com.br/api/v1/cobranca/{$codigo_cobranca}/cancelar";

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $urlCancelamento);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "x-ID: 1239",
            "x-Token: 12I175258e546673S723524s6780C77e7942j72j1149e96C8276s59R6428S15s4110e12s7269I",
            "Content-Type: application/json"
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $responseCancel = curl_exec($curl);
        $http_code_cancel = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        var_dump("callback cancelamento",$http_code_cancel);
        return match ($http_code_cancel) {
            201 => [
                'status' => 201,
                'message' => 'Cobrança cancelada com sucesso',
                'seu_codigo' => $seu_codigo,
                'codigo_cobranca' => $codigo_cobranca
            ],
            204 => [
                'status' => 204,
                'message' => 'Saldo insuficiente para estorno. O cancelamento não pode ser concluído.',
                'seu_codigo' => $seu_codigo,
                'codigo_cobranca' => $codigo_cobranca
            ],
            403 => [
                'status' => 403,
                'message' => 'Cobrança já foi paga ou não pode ser cancelada.',
                'seu_codigo' => $seu_codigo,
                'codigo_cobranca' => $codigo_cobranca
            ],
            default => [
                'status' => $http_code_cancel,
                'message' => 'Erro ao tentar cancelar a cobrança',
                'seu_codigo' => $seu_codigo,
                'codigo_cobranca' => $codigo_cobranca,
                'data' => $responseCancel
            ],
        };
    }
}
