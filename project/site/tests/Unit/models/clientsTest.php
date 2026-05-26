<?php

namespace Tests\App\model\entity;

use PHPUnit\Framework\TestCase;
use App\model\entity\clients;
use Functions\db\config;

class ClientsTest extends TestCase
{
    private static $id;

    protected function setUp(): void
    {
        parent::setUp();
        // Configuração inicial para os testes
    }

    public function testCreateClientSuccess()
    {
        $data = [
            'avatar' => 'avatar.png',
            'username' => 'johndoe',
            'full_name' => 'John Doe',
            'fristr_name' => 'John',
            'last_name' => 'Doe',
            'cpf_cnpj' => '12345678900',
            'date_of_birth' => '1990-01-01'
        ];

        $result = clients::create($data);
        $this->assertEquals(201, $result['status']);
        $this->assertEquals('created', $result['message']);
    }

    public function testCreateClientCpfCnpjExists()
    {
        $data = [
            'avatar' => 'avatar.png',
            'username' => 'johndoe',
            'full_name' => 'John Doe',
            'fristr_name' => 'John',
            'last_name' => 'Doe',
            'cpf_cnpj' => '12345678900', // Assume que já existe
            'date_of_birth' => '1990-01-01'
        ];

        $result = clients::create($data);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('cpf_cnpj already exists', $result['message']);
    }

    public function testEditClientSuccess()
    {
        $id = 1; // ID existente
        $data = [
            'avatar' => 'new_avatar.png',
            'username' => 'janedoe',
            'full_name' => 'Jane Doe',
            'fristr_name' => 'Jane',
            'last_name' => 'Doe',
            'cpf_cnpj' => '09876543210',
            'date_of_birth' => '1992-02-02'
        ];

        $result = clients::edit($data, $id);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals('edited', $result['message']);
    }

    public function testEditClientNotFound()
    {
        $id = 9999; // ID inexistente
        $data = [
            'avatar' => 'avatar.png',
            'username' => 'nonexistent',
            'full_name' => 'Non Existent',
            'fristr_name' => 'Non',
            'last_name' => 'Existent',
            'cpf_cnpj' => '00000000000',
            'date_of_birth' => '2000-01-01'
        ];

        $result = clients::edit($data, $id);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('client not found', $result['message']);
    }

    public function testSearchClientSuccess()
    {
        $id = 1; // ID existente
        $result = clients::search($id);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals('success', $result['message']);
        $this->assertIsObject($result['data']);
    }

    public function testSearchClientNotFound()
    {
        $id = 9999; // ID inexistente
        $result = clients::search($id);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('client not found', $result['message']);
    }

    public function testListAllClients()
    {
        $pag = 10;
        $result = clients::listAll($pag);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals('success', $result['message']);
        $this->assertIsArray($result['data']);
    }

    public function testToggleClientStatus()
    {
        $id = 1; // ID existente
        $result = clients::activedOrDesactived($id);
        $this->assertEquals(200, $result['status']);
        $this->assertContains($result['message'], ['actived', 'desactived']);
    }

    public function testDeleteClientSuccess()
    {
        $id = 1; // ID existente
        $result = clients::deleted($id);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals('deleted', $result['message']);
    }

    public function testDeleteClientNotFound()
    {
        $id = 9999; // ID inexistente
        $result = clients::deleted($id);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('client not found', $result['message']);
    }
}