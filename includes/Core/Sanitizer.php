<?php
namespace ProConsultancy\Core;

use HTMLPurifier;
use HTMLPurifier_Config;

class Sanitizer {
    private static ?HTMLPurifier $purifier = null;
    
    public static function richText(string $html): string {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.SerializerPath', ROOT_PATH . '/cache');
            $config->set('HTML.Allowed', 'p,br,strong,em,u,ol,ul,li,a[href],h1,h2,h3');
            self::$purifier = new HTMLPurifier($config);
        }
        
        return self::$purifier->purify($html);
    }
}