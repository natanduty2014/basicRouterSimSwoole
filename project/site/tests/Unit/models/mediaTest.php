<?php

use App\model\entity\media;
use Illuminate\Database\Capsule\Manager as DB;

class MediaTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Configuração inicial, se necessário
    }

    public function testCreate()
    {
        $imageGoole = "data:image/png;base64,".base64_encode(file_get_contents('https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_272x92dp.png'));
        $imageData = [
            'media' => $imageGoole
        ];

        $response = media::create($imageData);

        $this->assertEquals(201, $response['status']);
        $this->assertEquals('Image uploaded successfully.', $response['message']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('src', $response['data']);
        $this->assertArrayHasKey('id', $response['data']);
    }

    public function testSearchIdFound()
    {
        $id = 1; // Assumindo que este ID existe
        $response = media::searchId($id);

        $this->assertEquals(200, $response['status']);
        $this->assertEquals('Media found.', $response['message']);
        $this->assertNotEmpty($response['data']);
    }

    public function testSearchIdNotFound()
    {
        $id = 9999; // Assumindo que este ID não existe
        $response = media::searchId($id);

        $this->assertEquals(404, $response['status']);
        $this->assertEquals('Media not found.', $response['message']);
    }

    public function testListAll()
    {
        $pag = 1;
        $response = media::listAll($pag);

        $this->assertEquals(200, $response['status']);
        $this->assertIsArray($response['data']);
        $this->assertArrayHasKey('pagination', $response);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Limpeza após cada teste, se necessário
    }
}

