<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use App\controllers\entity\students;

class studentsTest extends TestCase {
    private static $id;

    protected function setUp(): void {
        parent::setUp();
        // Inicialize o estado necessário para os testes
    }

    public function testCreateStudent() {
        $data = [
            "stu_name" => "John",
            "stu_surname" => "Doe",
            "stu_email" => "john.doe@example.com",
            "stu_date_of_birth" => "2000-01-01",
            "stu_avatar" => "data:image/png;base64," . base64_encode(file_get_contents('https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_272x92dp.png'))
        ];

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/v1/api/students')
                                               ->withParsedBody($data);
        $response = (new ResponseFactory())->createResponse();

        $response = students::create($request, $response, []);
        $responseData = json_decode((string) $response->getBody(), true);

        self::$id = $responseData['id'];
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testEditStudent() {
        $data = [
            "stu_name" => "John",
            "stu_surname" => "Doe Edited",
            "stu_email" => "john.doe.edited@example.com",
            "stu_date_of_birth" => "2000-01-01",
            "stu_avatar" => "data:image/png;base64," . base64_encode(file_get_contents('https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_272x92dp.png'))
        ];

        $id = self::$id; // Use o ID armazenado na variável estática
        $request = (new ServerRequestFactory())->createServerRequest('PUT', "/v1/api/students/{$id}")
                                               ->withParsedBody($data);
        $response = (new ResponseFactory())->createResponse();

        $response = students::edit($request, $response, ['id' => $id]);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testToggleActive() {
        $id = self::$id; // Use o ID armazenado na variável estática
        $request = (new ServerRequestFactory())->createServerRequest('GET', "/v1/api/students/toggleActive/{$id}");
        $response = (new ResponseFactory())->createResponse();

        $response = students::actived($request, $response, ['id' => $id]);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testListAll() {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/api/students');
        $response = (new ResponseFactory())->createResponse();

        $response = students::listall($request, $response, []);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testListAllWithPagination() {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/api/students/1');
        $response = (new ResponseFactory())->createResponse();

        $response = students::listall($request, $response, ['pag' => 1]);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteStudent() {
        $id = self::$id; // Use o ID armazenado na variável estática
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', "/v1/api/students/{$id}");
        $response = (new ResponseFactory())->createResponse();

        $response = students::deleted($request, $response, ['id' => $id]);
        $this->assertEquals(200, $response->getStatusCode());
    }
}