<?php

namespace Functions\slim;


use Functions\{
    image\image
};

/**
 * Classe para processar e validar dados de requisições,
 * especialmente útil com $request->getParsedBody() do Slim Framework.
 * Fornece uma interface fluente para validações e transformações.
 *
 * @property mixed $data Os dados sendo processados.
 * @method mixed getData() Método final que deve ser chamado para obter os dados processados.
 * @template T
 */
class getParsedBody
{
    /** @var mixed */
    public $data;


    /**
     * Processa e valida os dados do objeto fornecido.
     *
     * @param string $data A chave dos dados a serem validados e processados.
     * 
     * @throws \Exception Se a chave de dados especificada não existir ou se os dados não forem um array.
     * 
     * @return self Retorna a instância atual com os dados processados.
     *
     * @example
     * $instance = new getParsedBody();
     * $instance->objectData('data');
     */
    //exemple objectData input: $data = ["data"=>["id"=>1,"name"=>"John"]]
    //select data from object
    public function objectData($data): self
    {
        if (!isset($this->data[$data])) {
            throw new \Exception(\json_encode(
                [
                    'error' => 'Invalid object data. Key not found',
                    'message' => $data,
                    'status' => 422
                ]
            ));
        }
        if (!\is_array($this->data[$data])) {
            throw new \Exception(\json_encode(
                [
                    'error' => 'Invalid object data. Not an array',
                    'message' => $this->data[$data],
                    'status' => 422
                ]
            ));
        }
        $this->data = $this->data[$data];
        return $this;
    }


    /**
     * Filtra os dados fornecidos.
     *
     * @param mixed $data Os dados a serem filtrados.
     * 
     * @return self Retorna a instância da classe para encadeamento de métodos.
     * 
     * @throws \Exception Se os dados fornecidos forem nulos, uma exceção é lançada com uma mensagem de erro codificada em JSON e código de status 422.
     *
     * @example
     * $instance = new Classe();
     * $instance->filterData($data);
     */
    /**
     * Filters the provided data.
     *
     * @param mixed $data The data to be filtered.
     * 
     * @return self Returns the instance of the class for method chaining.
     * 
     * @throws \Exception If the provided data is null, an exception is thrown with a JSON encoded error message and status code 422.
     */
    //exemple filter input: $data = "John"
    public function filter($data): self
    {
        if (is_null($data)) {
            throw new \Exception(\json_encode(['error' => 'Invalid data is null', 'status' => 422]));
        }
        $this->data = filter_var($data);
        return $this;
    }

    /**
     * Verifica se o dado é um array.
     *
     * Este método verifica se a propriedade `$data` é um array. 
     * Se não for, lança uma exceção com uma mensagem de erro e status 422.
     *
     * @throws \Exception Se `$data` não for um array.
     * @return self Retorna a instância atual para permitir encadeamento de métodos.
     *
     * @example
     * $obj = new Classe();
     * $obj->isArray(); // Verifica se $data é um array
     */
    //exemple isArray input: $data = ["id" => 1, "name" => "John"]
    public function isArray(): self
    {
        if (!\is_array($this->data)) {
            throw new \Exception(\json_encode(['error' => 'Invalid array', 'status' => 422]));
        }
        return $this;
    }

    /**
     * Verifica se o dado é uma string.
     *
     * @throws \Exception Se o dado não for uma string, lança uma exceção com uma mensagem de erro e status 400.
     * @return self Retorna a própria instância para permitir encadeamento de métodos.
     *
     * Exemplo de uso:
     * ```php
     * $obj = new Classe();
     * $obj->isString();
     * ```
     */
    //exemple isString input: $data = "John"
    public function isString(): self
    {
        if (!\is_string($this->data)) {
            throw new \Exception(json_encode(['error' => 'The provided data is not a valid string - ' . $this->data, 'status' => 400]));
        }
        return $this;
    }

    /**
     * Verifica se o valor associado à chave fornecida é um inteiro.
     *
     * @param string $key A chave do valor a ser verificado.
     * @return self Retorna a instância atual para permitir encadeamento de métodos.
     * @throws \Exception Se o valor não for um inteiro, lança uma exceção com uma mensagem de erro e status 400.
     *
     * Exemplo de uso:
     * ```php
     * $obj->isInt('idade');
     * ```
     */
    //exemple isInt input: $data = 1
    public function isInt($key): self
    {
        if (!\is_int($this->data[$key])) {
            throw new \Exception(json_encode(['error' => $key . ' Invalid int', 'status' => 400]));
        }
        return $this;
    }

    /**
     * Verifica se a entrada é uma string.
     *
     * @param string $key A chave do dado a ser verificado.
     * @return self Retorna a instância atual para encadeamento de métodos.
     * @throws \Exception Se a chave não existir, se o valor for um inteiro ou se não for uma string.
     *
     * Exemplo de uso:
     * ```php
     * try {
     *     $obj->isStringInput('nome');
     * } catch (\Exception $e) {
     *     echo $e->getMessage();
     * }
     * ```
     */
    //exemple isStringInput input: $data = "John"
    public function isStringInput($key): self
    {
        if (!isset($this->data[$key])) {
            throw new \Exception(json_encode(['error' => $key . ' Invalid key is missing', 'status' => 422]));
        }
        if (\is_int($this->data[$key])) {
            throw new \Exception(json_encode(['error' => $key . ' Invalid is int', 'status' => 400]));
        }
        if (!\is_string($this->data[$key])) {
            throw new \Exception(json_encode(['error' => $key . ' Invalid string', 'status' => 400]));
        }
        return $this;
    }


    /**
     * Converte um array para JSON.
     *
     * @throws \Exception Se o dado não for um array.
     *
     * @example
     * $data = ["id" => 1, "name" => "John"];
     * $obj->arrayToJson();
     * // Retorna o objeto com $data convertido para JSON.
     */
    //exemple arrayToJson input: $data = ["id" => 1, "name" => "John"]
    public function arrayToJson(): self
    {
        if (!\is_array($this->data)) {
            throw new \Exception(\json_encode(['error' => 'Invalid, not an array', 'status' => 422]));
        }
        $this->data = json_encode($this->data);
        return $this;
    }

    /**
     * Converte uma string JSON em um array associativo.
     *
     * @param string $data A string JSON a ser convertida.
     * @return self Retorna a instância atual com o array decodificado.
     * @throws \Exception Se o dado fornecido não for uma string.
     *
     * Exemplo de uso:
     * ```php
     * $jsonString = '{"data":{"id":1,"name":"John"}}';
     * $obj = new Classe(); // Substitua 'Classe' pelo nome da sua classe
     * $obj->jsonToArray($jsonString);
     * ```
     */
    //exemple jsonToArray input: $data = "{"data":{"id":1,"name":"John"}}"
    public function jsonToArray($data): self
    {
        if (!\is_string($data)) {
            throw new \Exception(\json_encode(
                [
                    'error' => 'Invalid, not a string',
                    'message' => $data,
                    'status' => 422
                ]
            ));
        }
        $this->data = \json_decode($data, true);
        return $this;
    }

    /**
     * Verifica se a data fornecida é válida no formato 'Y-m-d'.
     *
     * @param string $key A chave do array que contém a data a ser validada.
     * @return self Retorna a própria instância se a data for válida.
     * @throws \Exception Lança uma exceção se a chave da data estiver ausente no array ou se a data estiver em um formato inválido.
     *
     * Exemplo de uso:
     * ```php
     * $data = ['data' => '2022-01-01'];
     * $obj = new ClasseQueContemOMetodo($data);
     * $obj->isValidDate('data');
     * ```
     */
    //exemple isValidDate input: $data = "2022-01-01"
    public function isValidDate($key): self
    {
        if (!isset($this->data[$key])) {
            throw new \Exception(json_encode(['error' => 'Date key is missing in the array. isValidDate not key on array => ' . $key, 'status' => 422]));
        }

        $date = $this->data[$key];
        $format = 'Y-m-d';
        $d = \DateTime::createFromFormat($format, $date);
        if ($d && $d->format($format) === $date) {
            return $this;
        } else {
            throw new \Exception(json_encode(['error' => 'Invalid date format. ' . $key, 'status' => 422]));
        }
    }


    //validate confirmation password (password, password2, quant caracters)
    /*
    * Ver se a senha é valida e se a senha e a confirmação são iguais e se tem o minimo de caracteres
    * @param string $key
    * @param string $key2
    * @param int $quant
    * @param string $key3 - key3 é opcional (key3 vai ser a senha padrão caso não exista o password, mas ele precisa ser dirente de null e maior ou igual a 8)
    * @param string $password_white_list - key3 é opcional (key3 vai ser a senha padrão caso não exista o password, mas ele precisa ser dirente de null e maior ou igual a 8)
    * @return self
    * @throws \Exception
    */
    public function isValidPassword($key, $key2, $quant, $key3 = null): self
    {
        // caso n exista a $key e a $key2 vai passar da validação
        if (isset($this->data[$key]) && isset($this->data[$key2])) {
            return $this;
        }
        // se key3 for direrente ele vai passar da validação (e vai rettornar o key3 e pegando os 8 primeiros)
        if ($key3 !== null) {

            if (strlen($this->data[$key3]) < $quant) {
                throw new \Exception(json_encode(['error' => $key3 . ' Invalid password, password must be at least ' . $quant . ' characters', 'status' => 422]));
            }
            //retornar apenas os 8 primeiros caracteres mesmo se for maior que 8
            $this->data[$key] = substr($this->data[$key3], 0, 8);
            return $this;
        }
        if (!isset($this->data[$key])) {
            throw new \Exception(json_encode(['error' => $key . ' Invalid key is missing', 'status' => 422]));
        }
        if (!isset($this->data[$key2])) {
            throw new \Exception(json_encode(['error' => $key2 . ' Invalid key is missing', 'status' => 422]));
        }
        if (strlen($this->data[$key]) < $quant) {
            throw new \Exception(json_encode(['error' => $key . '
            Invalid password, password must be at least ' . $quant . ' characters', 'status' => 422]));
            if ($this->data[$key] !== $this->data[$key2]) {
                throw new \Exception(json_encode(['error' => 'Passwords do not match', 'status' => 422]));
            }
        }
        return $this;
    }


    //remove caracteres tipo (. , - /) definir qual na variavel
    /*
    * Remove caracteres especificos de uma string
    * @param string $key
    * @param string $caracteres
    * @return self
    * @throws \Exception
    */
    public function removeCaracteres($key, $caracteres): self
    {
        if (!isset($this->data[$key])) {
            throw new \Exception(json_encode(['error' => $key . ' Invalid key is missing', 'status' => 422]));
        }
        if (!\is_string($this->data[$key])) {
            throw new \Exception(json_encode(['error' => $key . ' Invalid string', 'status' => 400]));
        }
        $this->data[$key] = str_replace($caracteres, '', $this->data[$key]);
        return $this;
    }


    public function convertDate($key): self
    {
        // Verifica se a chave existe no array
        if (!isset($this->data[$key])) {
            throw new \Exception(json_encode([
                'error'  => "Date key is missing in the array. convertDate => {$key}",
                'status' => 422
            ]));
        }

        // Valor da data que o usuário forneceu
        $dateStr = $this->data[$key];

        // Lista de formatos de entrada que você quer aceitar
        $possibleFormats = [
            'd/m/Y',
            'd-m-Y',
            'd/m/y',
            'd-m-y'
        ];

        // Tenta "interpretar" a data em cada um desses formatos
        foreach ($possibleFormats as $format) {
            $dateTime = \DateTime::createFromFormat($format, $dateStr);

            // Verifica se deu certo a leitura e se não houve "sobras" de caracteres
            if ($dateTime && $dateTime->format($format) === $dateStr) {
                // Converte para o formato Y-m-d
                $this->data[$key] = $dateTime->format('Y-m-d');
                return $this; // Retorna o próprio objeto para encadear métodos se quiser
            }
        }

        // Se chegar aqui, nenhum formato foi compatível
        throw new \Exception(json_encode([
            'error'  => 'Invalid date format.',
            'status' => 422
        ]));
    }


    /**
     * Verifica se o valor de uma chave específica no array de dados é um horário válido no formato 'H:i:s'.
     *
     * @param string $key A chave no array de dados que contém o horário a ser validado.
     * @return $this Retorna a própria instância do objeto se o horário for válido.
     * @throws \Exception Lança uma exceção se a chave não existir no array de dados ou se o horário estiver em um formato inválido.
     *
     * Exemplo de uso:
     * ```php
     * $obj = new ClasseQueContemOMetodo();
     * $obj->data = ['hora' => '14:30:00'];
     * $obj->isValidTime('hora');
     * ```
     */
    //exemple isValidDate input: $data = "14:30:00"
    public function isValidTime($key): self
    {
        if (!isset($this->data[$key])) {
            throw new \Exception(json_encode(['error' => 'Date key is missing in the array. isValidDate not key on array => ' . $key, 'status' => 422]));
        }

        $time = $this->data[$key]; //format 00:00:00 (string)
        $format = 'H:i:s';
        $d = \DateTime::createFromFormat($format, $time);
        if ($d && $d->format($format) === $time) {
            return $this;
        } else {
            throw new \Exception(json_encode(['error' => 'Invalid time format.', 'status' => 422]));
        }
    }

    /**
     * Verifica se os parâmetros são nulos ou vazios.
     *
     * @param mixed $data Os dados a serem verificados. Pode ser nulo ou uma string vazia.
     * @return self Retorna a instância atual para encadeamento de métodos.
     * @throws \Exception Lança uma exceção se os dados forem nulos ou vazios.
     *
     * Exemplo de uso:
     * ```php
     * try {
     *     $obj->nullParams($data);
     * } catch (\Exception $e) {
     *     echo $e->getMessage(); // {"error":"Invalid data is null or empty","status":422}
     * }
     * ```
     */
    //exemple nullParams input: $data = null or $data = ""
    public function nullParams($data): self
    {
        if (is_null($data) || empty($data)) {
            throw new \Exception(json_encode(['error' => 'Invalid data is null or empty', 'status' => 422]));
        }
        $this->data = $data;
        return $this;
    }

    /**
     * Verifica se o comprimento da string associada a uma chave específica no array de dados é menor ou igual a um valor especificado.
     *
     * @param string $key A chave do array de dados que contém a string a ser verificada.
     * @param int $quant O comprimento máximo permitido para a string.
     * @param bool $invert Se verdadeiro, inverte a lógica da verificação (verifica se é menor que o comprimento máximo).
     * @return self Retorna a própria instância para permitir encadeamento de métodos.
     * @throws \Exception Se a chave não estiver presente no array de dados ou se a string for maior que o comprimento permitido.
     *
     * Exemplo de uso:
     * ```php
     * $data = ['username' => '1234567890'];
     * $quant = 10;
     * $this->strlen('username', $quant);
     * ```
     */
    //exemple strlen input: $data = "1234567890" and $quant = 10
    public function strlen($key, int $quant, $invert = false): self
    {
        if (!isset($this->data[$key]) | !isset($quant)) {
            throw new \Exception(json_encode(['error' => $key . ' is missing in the array. strlen not key on array', 'status' => 422]));
        }

        // verificar se a string é menor que o comprimento máximo
        if ($invert) {
            if (strlen($this->data[$key]) < (int)$quant) {
                throw new \Exception(\json_encode(['error' => $key . ' is too short', 'status' => 422]));
            }
            return $this;
        }

        if (strlen($this->data[$key]) > (int)$quant) {
            throw new \Exception(\json_encode(['error' => $key . ' is too long', 'status' => 422]));
        }
        return $this;
    }

    /*
    * Verifica se o comprimento da string associada a uma chave específica no array de dados é maior ou igual a um valor especificado.
    *
    * @param string $key A chave do array de dados que contém a string a ser verificada.
    * @param int $quant O comprimento mínimo permitido para a string.
    * @return self Retorna a pr
    * @throws \Exception Se a chave não estiver presente no array de dados ou se a string for menor que o comprimento permitido.
    *
    * Exemplo de uso:
    * ```php
    * $data = ['username' => '1234567890'];
    * $quant = 10;
    * $this->strlenMin('username', $quant);
    * ```
    */
    public function strlenMin($key, int $quant): self
    {
        if (!isset($this->data[$key]) | !isset($quant)) {
            throw new \Exception(json_encode(['error' => $key . ' is missing in the array. strlen not key on array', 'status' => 422]));
        }

        // verificar se a string é maior que o comprimento mínimo
        if (strlen($this->data[$key]) < (int)$quant) {
            throw new \Exception(\json_encode(['error' => $key . ' is too short', 'status' => 422]));
        }

        return $this;
    }

    /**
     * Remove espaços vazios de uma string no array de dados.
     *
     * @param string $key A chave do array de dados cuja string terá os espaços removidos.
     * @return self Retorna a instância atual para permitir encadeamento de métodos.
     * @throws \Exception Se a chave não estiver presente no array de dados.
     *
     * Exemplo de uso:
     * ```php
     * $obj = new Classe();
     * $obj->removeEmptySpaces('telefone');
     * ```
     */
    //exemple removeEmptySpaces input: $data = "1234567890"
    public function removeEmptySpaces($key): self
    {
        if (!isset($this->data[$key])) {
            throw new \Exception(\json_encode(['error' => $key . ' is missing in the array. removeEmptySpaces not key on array', 'status' => 422]));
        }
        $this->data[$key] = str_replace(' ', '', $this->data[$key]);
        return $this;
    }

    /**
     * Verifica se a entrada no array de dados está vazia ou ausente.
     *
     * @param string $key A chave do array de dados a ser verificada.
     * @return self Retorna a própria instância para encadeamento de métodos.
     * @throws \Exception Se a chave não estiver presente no array ou se o valor estiver vazio.
     *
     * Exemplo de uso:
     * ```php
     * try {
     *     $obj->validInputEmpty('nome');
     * } catch (\Exception $e) {
     *     echo $e->getMessage();
     * }
     * ```
     */
    //exemple validInputEmpty input: $data = null or $data = ""
    public function validInputEmpty($key): self
    {
        if (!isset($this->data[$key])) {
            throw new \Exception(json_encode(['error' => $key . ' is missing in the array. validInputEmpty not key on array ' . $key, 'status' => 422]));
        }

        if (empty($this->data[$key])) {
            throw new \Exception(json_encode(['error' => $key . ' is empty in the array. validInputEmpty not key on array ' . $key, 'status' => 400]));
        }
        return $this;
    }

    /**
     * Valida se o valor associado a uma chave específica no array de dados é um email válido.
     *
     * @param string $key A chave no array de dados que contém o email a ser validado.
     * @return self Retorna a própria instância para permitir encadeamento de métodos.
     * @throws \Exception Se a chave não existir no array de dados, se o valor estiver vazio ou se não for um email válido.
     *
     * Exemplo de uso:
     * ```php
     * try {
     *     $obj->validEmail('email');
     * } catch (\Exception $e) {
     *     echo $e->getMessage();
     * }
     * ```
     */
    //exemple validEmail input: $data = "email@email.com"
    public function validEmail($key): self
    {
        if (!isset($this->data[$key])) {
            throw new \Exception(json_encode(['error' => $key . ' is missing in the array. validEmail not key on array', 'status' => 422]));
        }
        if (empty($this->data[$key])) {
            throw new \Exception(json_encode(['error' => $key . ' is empty in the array. validEmail not key on array', 'status' => 400]));
        }
        if (!filter_var($this->data[$key], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception(json_encode(['error' => $key . ' is not a valid email. validEmail not key on array', 'status' => 400]));
        }
        return $this;
    }

    /**
     * Analisa um array com base no tipo especificado.
     *
     * @param string $key A chave do array a ser analisada.
     * @param int $type O tipo de validação a ser aplicada aos itens do array. 
     *                  Pode ser:
     *                  1 - Requer que os itens sejam strings.
     *                  2 - Requer que os itens sejam inteiros.
     *                  3 - Não requer um tipo específico (qualquer tipo é aceito).
     * @return $this Retorna a instância atual para encadeamento de métodos.
     * @throws \Exception Se a chave não existir no array, se o valor não for um array, 
     *                    ou se algum item do array não corresponder ao tipo especificado.
     *
     * Exemplo de uso:
     * $data = ["1234567890", "1234567890"];
     * $this->data = ['key' => $data];
     * $this->analyseArray('key', 1); // Valida que todos os itens são strings.
     */
    //exemple analyseArray input: $data = ["1234567890", "1234567890"] and $type = 1 or $type = 2 or $type = 3
    //type 1 = is require type string
    //type 2 = is require type int
    //type 3 = is not require type. any type
    //type 4 = is require type string and int for convert to int
    //type 5 = is require type string and int for convert to string
    public function analyseArray($key, int $type = 3): self
    {
        //array = ""
        // if ($this->data[$key] == "") {
        //     return $this;
        // }
        if (isset($this->data[$key])) {
            if (!is_array($this->data[$key])) {
                throw new \Exception(json_encode(['error' => $key . ' is invalid array. analyseAray not key on array or invalid format', 'status' => 400]));
            }
            for ($i = 0, $len = count($this->data[$key]); $i < $len; $i++) {
                if ($type == 1 && !is_string($this->data[$key][$i])) {
                    throw new \Exception(json_encode(['error' => $key . ' Invalid stack item format. is require type string and no int in input', 'status' => 400]));
                }
                if ($type == 2 && !is_int($this->data[$key][$i])) {
                    throw new \Exception(json_encode(['error' => $key . ' Invalid stack item format. is require type int and no string in input', 'status' => 400]));
                }
                if ($type == 4) {
                    $this->data[$key][$i] = (int)$this->data[$key][$i];
                }
                if ($type == 5) {
                    $this->data[$key][$i] = (string)$this->data[$key][$i];
                }
                if ($type == 3) {
                    return $this;
                }
            }
        } else {
            throw new \Exception(json_encode(['error' => $key . ' is missing in the array.', 'status' => 422]));
        }
        return $this;
    }

    /**
     * Verifica se o valor associado a uma chave específica no array `$data` é uma string JSON válida.
     *
     * @param string $key A chave do array `$data` que será verificada.
     * @return $this Retorna a própria instância do objeto para permitir encadeamento de métodos.
     * @throws \Exception Se a chave não existir no array `$data`, se o valor não for uma string, ou se a string não for um JSON válido.
     *
     * Exemplo de uso:
     * ```php
     * $obj = new Classe();
     * $obj->isJson('data');
     * ```
     */
    //exemple isJson input: $data = "{"data":{"id":1,"name":"John"}}"
    public function isJson($key): self
    {
        if (!isset($this->data[$key])) {
            throw new \Exception(json_encode(['error' => $key . ' is missing in the array. isJson not key on array', 'status' => 422]));
        }
        if (!is_string($this->data[$key])) {
            throw new \Exception(json_encode(['error' => $key . ' is not a string. isJson not key on array', 'status' => 400]));
        }
        json_decode($this->data[$key]);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(json_encode(['error' => $key . ' is not a valid json. isJson not key on array', 'status' => 400]));
        }
        return $this;
    }

    /**
     * Verifica se um CPF é válido (apenas os dígitos) e retorna true/false.
     *
     * @param string $cpf
     * @return bool
     */
    private function isValidCPF($cpf)
    {
        // Remove tudo que não for dígito
        $cpf = preg_replace('/\D/', '', $cpf);

        // Se não tiver 11 dígitos, já retorna falso
        if (strlen($cpf) !== 11) {
            return false;
        }

        // Evita CPFs com todos os dígitos iguais (ex: 11111111111)
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Calcula os dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $resto = ((10 * $d) % 11) % 10;
            if ($cpf[$t] != $resto) {
                return false;
            }
        }

        return true;
    }

    public function validCPF($key, $notDigit = false): self
    {
        // 1. Verifica se a chave existe no array
        if (!isset($this->data[$key])) {
            throw new \Exception(json_encode([
                'error'  => "{$key} is missing in the array. validCPF => {$key}",
                'status' => 422
            ]));
        }

        // 2. Verifica se o valor está vazio
        if (empty($this->data[$key])) {
            throw new \Exception(json_encode([
                'error'  => "{$key} is empty in the array. validCPF => {$key}",
                'status' => 400
            ]));
        }

        // 3. Verifica se o CPF é válido
        if (!$this->isValidCPF($this->data[$key])) {
            throw new \Exception(json_encode([
                'error'  => "{$key} is not a valid CPF. validCPF => {$key}",
                'status' => 400
            ]));
        }
        if ($notDigit) {
            $this->data[$key] = preg_replace('/\D/', '', $this->data[$key]);
        }
        // Se tudo estiver certo, retorna $this para permitir encadeamento de métodos
        return $this;
    }

    //validar cpf fora do array com dados externos
    public function validCpfExternal($cpf, $notDigit = false): self
    {
        // 1. Verifica se o CPF é válido
        if (!$this->isValidCPF($cpf)) {
            throw new \Exception(json_encode([
                'error'  => "{$cpf} is not a valid CPF. validCPF => {$cpf}",
                'status' => 400
            ]));
        }

        if ($notDigit) {
            $cpf = preg_replace('/\D/', '', $cpf);
        }
        // Se tudo estiver certo, retorna $this para permitir encadeamento de métodos

        return $this;
    }

    /**
     * Gera um UUID (Identificador Único Universal) e o adiciona ao array de dados.
     *
     * @param string $key A chave do array onde o UUID será atribuído.
     * @param int $quant O comprimento do UUID a ser gerado (padrão é 8).
     * @return self Retorna a instância atual para encadeamento de métodos.
     *
     * Exemplo de uso:
     * ```php
     * $obj = new getParsedBody();
     * $obj->uuid('id', 8); // Adiciona uma chave 'id' com um UUID de 8 caracteres
     * ```
     */
    public function uuid($key, $quant = 8): self
    {
        // Gera um UUID com o comprimento especificado
        $uuid = substr(md5(uniqid(rand(), true)), 0, $quant);

        // Adiciona o UUID ao array de dados com a chave especificada
        $this->data[$key] = $uuid;

        return $this;
    }

    /*
    * Faz o upload de uma imagem e a adiciona ao array de dados.
    * @param string $key A chave do array onde a imagem será armazenada.
    * @param string $src O caminho onde a imagem será salva.
    * @return self Retorna a instância atual para encadeamento de métodos.
    * @throws \Exception Lança uma exceção se a chave não existir no array, se o caminho da imagem não for válido ou se ocorrer um erro durante o upload.
    */

    public function uploadImage($key, $src): self
    {

        if (!isset($this->data[$key])) {
            return $this;
        }
        
        // Verificar se é uma URL completa (http/https) ou um caminho local (começando com /)
        $value = $this->data[$key];
        $isUrl = filter_var($value, FILTER_VALIDATE_URL);
        $isLocalPath = (is_string($value) && strpos($value, '/') === 0);
        
        if ($isUrl || $isLocalPath) {
            $this->data[$key] = null;
            return $this;
        }
        
        if (empty($this->data[$key])) {
            throw new \Exception(json_encode(['error' => $key . ' is empty in the array. uploadImage not key on array', 'status' => 422]));
        }


        try {

            // Verifica se o caminho da imagem já contém o $src
            // if (strpos($this->data[$key], str_replace('.', '', $src)) !== false) {
            //     // Se já contém o caminho, mantém o valor original
            //     return $this;
            // }

            //remover ponto caso tiver no $src
            $image = image::upload($this->data[$key], $src);
            $src = str_replace('.', '', $src);
            $image = $src . $image;
            $this->data[$key] = $image;
            return $this;
        } catch (\Throwable $e) {
            throw new \Exception(json_encode(['error' => $e->getMessage(), 'status' => 422]));
        }
    }


    /**
     * Retorna os dados processados.
     * Este método DEVE ser chamado no final de qualquer cadeia de métodos
     * para obter o resultado final das validações e transformações.
     *
     * @return mixed Os dados processados.
     *
     * @example
     * $obj = new getParsedBody();
     * $obj->filter($data)->objectData('data')->getData(); // Forma correta
     * // $obj->filter($data)->objectData('data'); // Forma INCORRETA - não retorna os dados processados
     */
    public function getData()
    {
        return $this->data;
    }
}
