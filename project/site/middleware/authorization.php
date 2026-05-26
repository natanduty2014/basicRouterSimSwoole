<?php

namespace App\middleware;

use Functions\slim\getParsedBody as getParsedBody;
use Slim\Psr7\Response as slimReponse;
use Psr\Http\Message\ResponseInterface as ResponseSlim;
use Psr\Http\Message\ServerRequestInterface as RequestSlim;
use Psr\Http\Server\RequestHandlerInterface as RequestHandlerSlim;
use Functions\jwt\jwtCms as jwt;
use Functions\cryptography\decode;

    /**
     * @OA\Info(
     *   version="0.4.0",
     *   title="My API",
     *   description="This is a sample server Petstore server.  You can find out more about Swagger at [http://swagger.io](http://swagger.io) or on [irc.freenode.net, #swagger](http://swagger.io/irc/). For this sample, you can use the api key `special-key` to test the authorization filters.",
    *    @OA\contact(
    *      name="API Support",
    *      url="http://www.example.com/support",
    *      email="support@example.com"
    * ),
    *   @OA\Attachable()
    * )
     * @OA\SecurityScheme(
     *     securityScheme="bearerAuth",
     *     type="http",
     *     scheme="bearer",
     *     bearerFormat="JWT"
     * ),
    * @OA\Response(
        *    response=200,
        *   description="Success"
        * ),
        * @OA\Response(
        *   response=400,
        *  description="Bad request"
        * ),
        * @OA\Response(
        *   response=401,
        *  description="Unauthorized"
        * ),
        * @OA\Response(
        *   response=500,
        *  description="Internal Server Error"
        * ),
        */
class authorization
{

    static public function authorization(RequestSlim $request, RequestHandlerSlim $handler): ResponseSlim
    {
        try{
            $token = jwt::verifyToken($request->getHeader('authorization')[0] ?? null);
            if(!$token){
                throw new \Exception ('Token inválido');
            }
            $jwt = jwt::decodeToken($request->getHeader('authorization')[0]);
            $getIPExternal = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
            if(empty($getIPExternal)){ //essa função so deve ser usada em ambiente de desenvolvimento
                $getIPExternal = 'http://localhost';
            }
            $HTTP_ORIGIN = $_SERVER['HTTP_ORIGIN'];
            if(empty($HTTP_ORIGIN)){ //essa função so deve ser usada em ambiente de desenvolvimento
                $HTTP_ORIGIN = 'http://localhost';
            }
            $HTTP_SEC_CH_UA_PLATFORM = $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? null;
            if(empty($HTTP_SEC_CH_UA_PLATFORM)){ //essa função so deve ser usada em ambiente de desenvolvimento
                $HTTP_SEC_CH_UA_PLATFORM = '';
            }
            $HTTP_USER_AGENT = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if(!$jwt->browser){
                $jwt->browser = 'Desconhecido';
            }
        //    if(self::getBrowser($HTTP_USER_AGENT) != $jwt->browser){
        //         throw new \Exception ('Token_not_allowed');
        //     }
        //     if($getIPExternal != decode::decode($jwt->aud[1])){
        //         throw new \Exception ('Token_not_allowed');
        //     }
        //     if($HTTP_ORIGIN != $jwt->aud[0]){
        //         throw new \Exception ('Token_not_allowed');
        //     }
        //     if($HTTP_SEC_CH_UA_PLATFORM != $jwt->platform){
        //         throw new \Exception ('Token_not_allowed');
        //     }
            //
            $response = $handler->handle($request);
            $status = $response->getStatusCode();
            $existingContent = (string) $response->getBody();
            $response = new slimReponse();
            $response->getBody()->write($existingContent);
            $response = $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
            ->withHeader('Authorization', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            // Return the updated response
            return $response->withStatus($status);
        }catch (\Throwable $e) {
            $response = new slimReponse();
            $response->getBody()->write(
                json_encode(
                    array(
                        'status' => 401,
                        'message' => 'Unauthorized '. $e->getMessage()
                    )
                )
            );
            return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
            ->withHeader('Authorization', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    }

    static public function getBrowser($userAgent) {
        // Padrões comuns de user agents
        $patterns = array(
            'Firefox' => '/Firefox/i',
            'Chrome' => '/Chrome|CriOS/i',
            'Safari' => '/Safari/i',
            'Edge' => '/Edg/i',
            'Opera' => '/Opera|OPR/i',
            'IE' => '/MSIE/i',
            'Brave' => '/Brave/i',
            'Vivaldi' => '/Vivaldi/i',
            'Yandex' => '/YaBrowser/i',
            'UC Browser' => '/UCBrowser/i',
            'Samsung Internet' => '/SamsungBrowser/i',
            'Nokia Browser' => '/NokiaBrowser/i',
            'Maxthon' => '/Maxthon/i',
            'Konqueror' => '/Konqueror/i',
            'Pale Moon' => '/PaleMoon/i',
            'SeaMonkey' => '/SeaMonkey/i',
            'Avant Browser' => '/Avant Browser/i',
            'Epic Privacy Browser' => '/Epic/i',
            'Waterfox' => '/Waterfox/i',
            'DuckDuckGo Browser' => '/DuckDuckGo/i',
            'Midori' => '/Midori/i',
            'qutebrowser' => '/qutebrowser/i',
            'Sleipnir' => '/Sleipnir/i',
            'GNU IceCat' => '/IceCat/i',
            'GNU IceWeasel' => '/Iceweasel/i',
            'QupZilla' => '/QupZilla/i',
            'Falkon' => '/Falkon/i',
            'Min Browser' => '/Min/i',
            'Dooble' => '/Dooble/i',
            'Elinks' => '/ELinks/i',
            'Links' => '/Links/i',
            'Lynx' => '/Lynx/i',
            'w3m' => '/w3m/i',
            'NetSurf' => '/NetSurf/i',
            'Surf' => '/Surf/i',
            'Dillo' => '/Dillo/i',
            'Amaya' => '/Amaya/i',
            'EWW' => '/w3m/i', // Emacs Web Wowser
            'Emacs w3' => '/w3m/i',
            'MicroEmacs' => '/w3m/i',
            'w3' => '/w3m/i',
            'ELinks' => '/ELinks/i'
        );

        // Verifica cada padrão para determinar o navegador
        foreach ($patterns as $browser => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $browser;
            }
        }
        // Se nenhum navegador for encontrado, retorna desconhecido
        return 'Desconhecido';
    }
}
