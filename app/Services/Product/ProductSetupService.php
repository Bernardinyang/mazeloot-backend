<?php

namespace App\Services\Product;

interface ProductSetupService
{
    /**
     * Initialize product setup for a user
     *
     * @param string $userUuid
     * @param array $data Product-specific setup data
     * @return mixed
     */
    public function setup(string $userUuid, array $data = []): mixed;
}
