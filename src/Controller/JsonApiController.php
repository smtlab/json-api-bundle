<?php

namespace SmtLab\JsonApiBundle\Controller;

use SmtLab\JsonApiBundle\JsonApiServer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class JsonApiController extends AbstractController
{
    public function __invoke(Request $request, JsonApiServer $jsonApiServer)
    {
        return $jsonApiServer->serve($request);
    }
}
