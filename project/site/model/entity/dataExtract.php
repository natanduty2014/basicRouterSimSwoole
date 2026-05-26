<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;

/* doc para o dev Natanyel
tirar o facebook, senha senha temp
*/

class dataExtract
{
    /**
     * Verifica se uma tabela existe no schema atual
     * 
     * @param string $table Nome da tabela
     * @return bool
     */
    public static function tableExists(string $table): bool
    {
        try {
            // Obter o nome do banco de dados da configuração
            $database = defined('MYSQL_DB') ? MYSQL_DB : (getenv('DB_NAME') ?: null);
            
            // Log de debug
            error_log("DEBUG tableExists: database={$database}, table={$table}");
            
            if (empty($database)) {
                // Fallback para DATABASE() se não houver configuração
                $result = Db::table('information_schema.tables')
                    ->where('table_schema', Db::raw('DATABASE()'))
                    ->where('table_name', $table)
                    ->limit(1)
                    ->first();
            } else {
                // Usar o nome do banco da configuração
                $result = Db::table('information_schema.tables')
                    ->where('table_schema', $database)
                    ->where('table_name', $table)
                    ->limit(1)
                    ->first();
            }
            
            error_log("DEBUG tableExists result: " . ($result ? 'FOUND' : 'NOT FOUND'));
            
            return !empty($result);
        } catch (\Throwable $e) {
            error_log("DEBUG tableExists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Detecta automaticamente a coluna de modificação/atualização da tabela
     * Busca por colunas comuns como updated_at, modified_at, etc.
     * 
     * @param string $table Nome da tabela
     * @return string|null Nome da coluna encontrada ou null
     */
    public static function detectModifiedColumn(string $table): ?string
    {
        try {
            // Obter o nome do banco de dados da configuração
            $database = defined('MYSQL_DB') ? MYSQL_DB : (getenv('DB_NAME') ?: null);
            
            // Lista de candidatos em ordem de prioridade
            $candidates = [
                'updated_at',
                'modified_at',
                'modified',
                'last_modified',
                'updatedAt',
                'dt_update',
                'dt_updated',
                'dt_alteracao',
                'data_atualizacao',
                'data_alteracao',
                'timestamp',
                'ts_update',
                'date_modified',
                'last_update',
                'cli_atualizacao',
                'prod_atualizacao',
                'user_atualizacao',
                'req_atualizacao',
                'ped_atualizacao'
            ];

            // Construir query base
            $query = Db::table('information_schema.COLUMNS')
                ->select('COLUMN_NAME');
            
            // Adicionar filtro de schema
            if (!empty($database)) {
                $query->where('TABLE_SCHEMA', $database);
            } else {
                $query->where('TABLE_SCHEMA', Db::raw('DATABASE()'));
            }
            
            // Busca por candidatos prioritários
            $result = $query
                ->where('TABLE_NAME', $table)
                ->whereIn('COLUMN_NAME', $candidates)
                ->orderByRaw("FIELD(COLUMN_NAME, '" . implode("','", $candidates) . "')")
                ->limit(1)
                ->first();
            
            if ($result) {
                $columnName = is_array($result) ? ($result['COLUMN_NAME'] ?? null) : ($result->COLUMN_NAME ?? null);
                if ($columnName) {
                    return $columnName;
                }
            }

            // Fallback: procura qualquer coluna TIMESTAMP/DATETIME com 'update', 'modif' ou 'atualizacao' no nome
            $query2 = Db::table('information_schema.COLUMNS')
                ->select('COLUMN_NAME');
            
            // Adicionar filtro de schema
            if (!empty($database)) {
                $query2->where('TABLE_SCHEMA', $database);
            } else {
                $query2->where('TABLE_SCHEMA', Db::raw('DATABASE()'));
            }
            
            $result2 = $query2
                ->where('TABLE_NAME', $table)
                ->whereIn('DATA_TYPE', ['timestamp', 'datetime', 'date'])
                ->where(function($query) {
                    $query->where('COLUMN_NAME', 'like', '%update%')
                          ->orWhere('COLUMN_NAME', 'like', '%modif%')
                          ->orWhere('COLUMN_NAME', 'like', '%atualizacao%');
                })
                ->limit(1)
                ->first();
            
            if ($result2) {
                return is_array($result2) ? ($result2['COLUMN_NAME'] ?? null) : ($result2->COLUMN_NAME ?? null);
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Extrai dados de uma tabela com suporte a carga incremental e paginação
     * 
     * @param string $table Nome da tabela
     * @param string|null $modifiedSince Timestamp ISO 8601 para carga incremental (opcional)
     * @param int $page Número da página (padrão: 1)
     * @param int $perPage Registros por página (padrão: 50)
     * @return array ['status' => int, 'message' => string, 'data' => array|null]
     */
    public static function extractData(string $table, ?string $modifiedSince = null, int $page = 1, int $perPage = 50): array
    {
        try {
            // Validar nome da tabela (apenas caracteres alfanuméricos e underscore)
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                return [
                    'status' => 400,
                    'message' => 'Nome de tabela inválido',
                    'error' => 'INVALID_TABLE_NAME',
                    'data' => null
                ];
            }

            // Verificar se a tabela existe
            if (!self::tableExists($table)) {
                return [
                    'status' => 404,
                    'message' => 'Tabela não encontrada',
                    'error' => 'TABLE_NOT_FOUND',
                    'data' => null
                ];
            }

            // Se timestamp fornecido, validar e converter para formato MySQL
            $modifiedTimestamp = null;
            if ($modifiedSince !== null && $modifiedSince !== '') {
                $modifiedTimestamp = self::parseIso8601ToMysql($modifiedSince);
                
                if ($modifiedTimestamp === null) {
                    return [
                        'status' => 400,
                        'message' => 'Parâmetro modified_since inválido. Use formato ISO 8601 (ex: 2024-01-01T00:00:00Z)',
                        'error' => 'INVALID_MODIFIED_SINCE',
                        'data' => null
                    ];
                }
            }

            // Construir a query
            $query = Db::table($table);

            // Se carga incremental, detectar coluna e aplicar filtro
            $modifiedColumn = null;
            if ($modifiedTimestamp !== null) {
                $modifiedColumn = self::detectModifiedColumn($table);
                
                if ($modifiedColumn === null) {
                    return [
                        'status' => 400,
                        'message' => 'Nenhuma coluna de modificação compatível encontrada para carga incremental',
                        'error' => 'NO_MODIFIED_COLUMN',
                        'data' => null
                    ];
                }

                $query->where($modifiedColumn, '>=', $modifiedTimestamp);
            }

            // Validar página e perPage
            $page = max(1, $page);
            $perPage = max(1, min(1000, $perPage)); // Limite máximo de 1000 registros por página

            // Executar query com paginação
            $paginatorResult = $query->paginate($perPage, ['*'], 'page', $page);
            
            // Converter para array (compatível com Hyperf)
            $paginated = [
                'data' => $paginatorResult->items(),
                'current_page' => $paginatorResult->currentPage(),
                'from' => $paginatorResult->firstItem(),
                'last_page' => $paginatorResult->lastPage(),
                'per_page' => $paginatorResult->perPage(),
                'to' => $paginatorResult->lastItem(),
                'total' => $paginatorResult->total()
            ];

            if (empty($paginated['data'])) {
                return [
                    'status' => 404,
                    'message' => 'Nenhum registro encontrado',
                    'data' => []
                ];
            }

            return [
                'status' => 200,
                'message' => 'Dados extraídos com sucesso',
                'data' => $paginated['data'],
                'pagination' => [
                    'current_page' => $paginated['current_page'],
                    'first_page_url' => 1,
                    'from' => $paginated['from'],
                    'last_page' => $paginated['last_page'],
                    'last_page_url' => $paginated['last_page'],
                    'next_page_url' => $paginated['current_page'] < $paginated['last_page'] ? ($paginated['current_page'] + 1) : null,
                    'per_page' => $paginated['per_page'],
                    'prev_page_url' => $paginated['current_page'] > 1 ? ($paginated['current_page'] - 1) : null,
                    'to' => $paginated['to'],
                    'total' => $paginated['total']
                ],
                'total_records' => $paginated['total'],
                'is_incremental' => $modifiedTimestamp !== null,
                'modified_column' => $modifiedColumn
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'message' => 'Erro ao extrair dados',
                'error' => 'INTERNAL_ERROR',
                'details' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Converte timestamp ISO 8601 ou formato MySQL para formato MySQL (Y-m-d H:i:s)
     * Aceita formatos: ISO 8601 (2023-12-01T16:56:37Z) ou MySQL (2023-12-01 16:56:37)
     * 
     * @param string $iso8601 Timestamp em formato ISO 8601 ou MySQL
     * @return string|null Timestamp em formato MySQL ou null se inválido
     */
    private static function parseIso8601ToMysql(string $iso8601): ?string
    {
        try {
            // Se já está no formato MySQL (YYYY-MM-DD HH:MM:SS), valida e retorna
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $iso8601)) {
                $datetime = new \DateTimeImmutable($iso8601);
                return $datetime->format('Y-m-d H:i:s');
            }
            
            // Tenta interpretar como ISO 8601 ou outros formatos
            $datetime = new \DateTimeImmutable($iso8601);
            // Converter para UTC para garantir consistência
            $datetimeUtc = $datetime->setTimezone(new \DateTimeZone('UTC'));
            return $datetimeUtc->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Obtém informações sobre a estrutura da tabela
     * Útil para debug e validação
     * 
     * @param string $table Nome da tabela
     * @return array
     */
    public static function getTableInfo(string $table): array
    {
        try {
            if (!self::tableExists($table)) {
                return [
                    'status' => 404,
                    'message' => 'Tabela não encontrada',
                    'data' => null
                ];
            }

            // Obter o nome do banco de dados da configuração
            $database = defined('MYSQL_DB') ? MYSQL_DB : (getenv('DB_NAME') ?: null);
            
            // Construir query
            $query = Db::table('information_schema.COLUMNS')
                ->select('COLUMN_NAME', 'DATA_TYPE', 'IS_NULLABLE', 'COLUMN_KEY', 'EXTRA');
            
            // Adicionar filtro de schema
            if (!empty($database)) {
                $query->where('TABLE_SCHEMA', $database);
            } else {
                $query->where('TABLE_SCHEMA', Db::raw('DATABASE()'));
            }
            
            $columns = $query
                ->where('TABLE_NAME', $table)
                ->orderBy('ORDINAL_POSITION')
                ->get();
            
            $modifiedColumn = self::detectModifiedColumn($table);

            return [
                'status' => 200,
                'data' => [
                    'table_name' => $table,
                    'database' => $database ?: 'DATABASE()',
                    'columns' => $columns,
                    'detected_modified_column' => $modifiedColumn,
                    'supports_incremental' => $modifiedColumn !== null
                ]
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'message' => 'Erro ao obter informações da tabela',
                'details' => $e->getMessage(),
                'data' => null
            ];
        }
    }
}
