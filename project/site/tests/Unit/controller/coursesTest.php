<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use App\controllers\entity\courses;

class coursesTest extends TestCase {
    private static $id;

    protected function setUp(): void {
        parent::setUp();
        // Inicialize o estado necessário para os testes
    }

    public function testCreateCourse() {
        $data = [
            "cou_slug" => "course-slug",
            "cou_cover" => "data:image/png;base64," . base64_encode(file_get_contents('https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_272x92dp.png')),
            "cou_title" => "Course Title",
            "cou_description" => "Course Description",
            "cou_teacher" => 1,
            "cou_lang" => "EN"
        ];

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/v1/api/courses')
                                               ->withParsedBody($data);
        $response = (new ResponseFactory())->createResponse();

        $response = courses::create($request, $response, []);
 
        $responseData = json_decode((string) $response->getBody(), true);
        self::$id = $responseData['id'];
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testEditCourse() {
        $data = [
            "cou_slug" => "course-slug-edited",
            "cou_cover" => "data:image/png;base64," . base64_encode(file_get_contents('https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_272x92dp.png')),
            "cou_title" => "Course Title Edited",
            "cou_description" => "Course Description Edited",
            "cou_teacher" => 2,
            "cou_lang" => "FR"
        ];

        $id = self::$id; // Use o ID armazenado na variável estática
        $request = (new ServerRequestFactory())->createServerRequest('PUT', "/v1/api/courses/{$id}")
                                               ->withParsedBody($data);
        $response = (new ResponseFactory())->createResponse();

        $response = courses::edit($request, $response, ['id' => $id]);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testToggleActive() {
        $id = self::$id; // Use o ID armazenado na variável estática
        $request = (new ServerRequestFactory())->createServerRequest('GET', "/v1/api/courses/toggleActive/{$id}");
        $response = (new ResponseFactory())->createResponse();

        $response = courses::actived($request, $response, ['id' => $id]);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testListAll() {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/api/courses');
        $response = (new ResponseFactory())->createResponse();

        $response = courses::listall($request, $response, []);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testListAllWithPagination() {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/api/courses/1');
        $response = (new ResponseFactory())->createResponse();

        $response = courses::listall($request, $response, ['pag' => 1]);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteCourse() {
        $id = self::$id; // Use o ID armazenado na variável estática
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', "/v1/api/courses/{$id}");
        $response = (new ResponseFactory())->createResponse();

        $response = courses::deleted($request, $response, ['id' => $id]);
        $this->assertEquals(200, $response->getStatusCode());
    }
}