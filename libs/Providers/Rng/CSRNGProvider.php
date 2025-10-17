<?php
namespace RobThree\Auth\Providers\Rng;

class CSRNGProvider implements IRNGProvider
{
    public function getRandomBytes(int $bytecount): string
    {
        return random_bytes($bytecount);
    }
}