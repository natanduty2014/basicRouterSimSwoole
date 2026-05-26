<?php

namespace Tests\App\model\entity;

use PHPUnit\Framework\TestCase;
use App\model\entity\contratantes;
use App\model\entity\contratantesUrl;

class ContratantesTest extends TestCase
{
    private static $testConId;
    private static $testUrlId;

    protected function setUp(): void
    {
        parent::setUp();

        // Inicializa a conexão com o banco do Hyperf para testes fora do framework
        $dbInitPath = __DIR__ . '/../../../../functions/db/hyperfDB/initHyperfDb.php';
        if (file_exists($dbInitPath)) {
            require_once $dbInitPath;
            if (function_exists('initHyperfDb')) {
                initHyperfDb();
            }
        }
    }

    public function testSearchUrlSuccess()
    {
        // Executar o método de busca passando uma url real do banco
        $result = contratantes::search_url('www.carolidouces.com.br');

        // Asserções
        $this->assertEquals(200, $result['status'], 'URL não foi encontrada. Verifique se www.carolidouces.com.br existe no banco.');
        $this->assertIsArray($result['data']);
        $this->assertNotEmpty($result['data']['urls']);
        $this->assertEquals('www.carolidouces.com.br', $result['data']['urls'][0]['cou_url']);
        var_dump($result);
    }

    public function testSearchUrlNotFound()
    {
        // Tentar buscar por uma URL que não existe
        $result = contratantes::search_url('url_inexistente_99999.com.br');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('Not found', $result['message']);
    }
}
