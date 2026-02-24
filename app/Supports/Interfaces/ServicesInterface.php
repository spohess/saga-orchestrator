<?php

declare(strict_types=1);

namespace App\Supports\Interfaces;

use App\Supports\Abstracts\Input;

interface ServicesInterface
{
    public function execute(Input $input): DTOInterface;
}
