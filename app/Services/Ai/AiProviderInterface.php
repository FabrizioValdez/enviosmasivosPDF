<?php

namespace App\Services\Ai;

interface AiProviderInterface
{
    public function extractProducts($rawText);
    public function getName();
    public function getCost();
}
