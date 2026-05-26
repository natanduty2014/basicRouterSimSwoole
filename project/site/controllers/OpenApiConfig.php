<?php

namespace App\controllers;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    openapi: '3.0.0'
)]
#[OA\Info(
    version: '1.0.0',
    title: 'API REFACIL',
    description: 'Documentação da API REFACIL'
)]
#[OA\Server(
    url: 'http://localhost:10384/',
    description: 'Servidor Local'
)]
class OpenApiConfig
{
}
