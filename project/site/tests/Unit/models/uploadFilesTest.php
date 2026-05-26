<?php 

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\model\entity\uploadFiles;
use Slim\Psr7\UploadedFile;

class uploadFilesTest extends TestCase{
    public function testUploadFile(){
        $file = new UploadedFile(
            "/public/project/site/tests/Unit/LTS_202411.txt",
            'test.txt',
            'text/plain',
            1024,
            0,
            0
        );
        $uploadFiles = new uploadFiles();
        $uploadFiles = $uploadFiles->uploadFile(['file' => $file]);
        //status 200 and data is not empty
        $this->assertEquals(201, $uploadFiles['status']);
    }

    public function testUploadFileDelete(){
        $id = 2;
        $uploadFiles = new uploadFiles();
        $uploadFiles = $uploadFiles->deleteFile($id);
        //status 200 and data is not empty
        $this->assertEquals(200, $uploadFiles['status']);
    }

    public function testUploadFileListAll(){
        $pag = 1;
        $uploadFiles = new uploadFiles();
        $uploadFiles = $uploadFiles->listAll($pag);
        //status 200 and data is not empty
        $this->assertEquals(200, $uploadFiles['status']);
    }
}