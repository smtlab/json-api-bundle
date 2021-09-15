<?php

namespace SmtLab\JsonApiBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class SmtLabJsonApiBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
