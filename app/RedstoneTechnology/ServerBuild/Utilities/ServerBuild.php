<?php

namespace RedstoneTechnology\ServerBuild\Utilities;

use Symfony\Component\Yaml\Parser;

class ServerBuild {
    public function __construct()
    {
    }

    public function build($name, $config)
    {

        $yaml = new Parser();

        $configPath = $this->getConfigPath($config);
        $configValues = $yaml->parse(file_get_contents($configPath));

        echo print_r($configValues, 1);
        
    }

    protected function getConfigPath($config)
    {
        $fileInfo = pathinfo($config);
        if (empty($fileInfo['extension'])) {
            $config .= '.yml';
        }
        if(is_file($config)) {
            return $config;
        }
        return realpath("./{$config}")."\n";

        #if()
    }
}