<?php

namespace App\Twig;

class AppExtension extends \Twig_Extension
{
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('env', [$this, 'env'])
        ];
    }

    /**
     * @param string $varName
     *
     * @return array|false|string
     */
    public function env(string $varName)
    {
        return getenv($varName);
    }
}
