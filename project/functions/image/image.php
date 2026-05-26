<?php

namespace Functions\image;

use Nyholm\Psr7\UploadedFile;

class image
{
    /**
     * @param string $imagem
     * @param string $src
     * @return string
     */
    static public function upload($imagem, string $src)
    {
        if ($imagem) {

                // Explorar apenas se a string não estiver vazia
                $explodedData = explode(';', $imagem);
                $type = $explodedData[0] ?? null;
                //verificar o diretorio se existe
                if (!is_dir($src)) {

                   throw new \Exception('Erro: Diretório não encontrado.');
                }
                if ($type) {
                    $imagem = $explodedData[1] ?? null;
                    $imagem = ($imagem) ? explode(',', $imagem)[1] : null;

                    if ($imagem) {
                        $imagem = base64_decode($imagem);
                        if ($imagem !== false) {  // Verificar se a decodificação foi bem-sucedida
                            $imagem_nome = md5(time() . rand(0, 9999)) . '.webp';
                            file_put_contents($src . $imagem_nome, $imagem);
                            /*$imgp = self::compress($src . $imagem_nome, $src, 90);
                            // // Remover o primeiro ponto da string
                             $imgp = substr($imgp, 1); */
                            return (string) $imagem_nome;
                        } else {
                            //return exception on  'Erro: Falha na decodificação da imagem.'
                            throw new \Exception('Erro: Falha na decodificação da imagem.');
                        }
                    } else {
                        throw new \Exception('Erro: image_empty');
                    }
                } else {
                    throw new \Exception('Erro: Tipo de imagem não fornecido.');
                }
        } else {
            \var_dump($imagem);
            throw new \Exception('Nenhuma imagem fornecida.');
        }
    }

     /**
     * @param string $source
     * @param string $destination
     * @param int $quality
     * @return string
     */
    static private function compress($source, $destination, $quality)
    {

        $info = getimagesize($source);

        if ($info['mime'] == 'image/jpeg')
            $image = imagecreatefromjpeg($source);

        elseif ($info['mime'] == 'image/gif')
            $image = imagecreatefromgif($source);

        elseif ($info['mime'] == 'image/png')
            $image = imagecreatefrompng($source);


        $image = imagewebp($image, $destination, $quality);
        if(!$image){
            throw new \Exception('Erro: Falha ao comprimir a imagem.');
        }
        return $destination;
    }

    static public function moveUploadedFile($directory, UploadedFile $uploadedFile){
        $extension = pathinfo($uploadedFile->getClientFilename(),
        PATHINFO_EXTENSION);
        $basename = md5(time() . rand(0, 9999));
        $filename = sprintf('%s.%0.8s', $basename, $extension);
        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
       return $filename;
   }

}
