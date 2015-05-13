<?php

namespace RedstoneTechnology\ServerBuild\Utilities;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Process\Process;

class ServerBuild {
    protected $script = '';
    protected $config = [];

    public function __construct()
    {
    }

    public function build($name, $config, $architecture)
    {
        $yaml = new Parser();
        $configDefaultsPath = $this->getConfigPath('defaults');
        $configPath = $this->getConfigPath($config);
        echo "ConfigPath: {$configPath}\n";
        $this->config =
            array_merge(
                $yaml->parse(file_get_contents($configDefaultsPath)),
                $yaml->parse(file_get_contents($configPath))
            );
        if(is_dir($name)) {
            throw new \Exception("A directory with \"{$name}\" already exists");
        }
        $box = $this->getBox($this->config['box'], $architecture);
        #die(getcwd()."\n");
        mkdir($name);
        chdir($name);
        $this->script .= "#Setup Repositories:\n". $this->setupPackages($this->config['repos']);
        $this->script .= "#Setup Packages:\n". $this->setupPackages($this->config['packages']);
        $this->script .= "#Enable Services\n". $this->setupServices($this->config['services']);
        $this->script .= "#Setup Directories:\n". $this->setupDirectories($this->config['directories']);
        $this->script .= "#Setup Config:\n". $this->setupConfig($this->config['httpd-config']);
        $vagrantfile = $this->setupServer($this->script, $this->config['vagrantfile'], $box);
        file_put_contents("Vagrantfile", $vagrantfile);
        #$process = new Process('vagrant up');
        #$process->run();
        #if (!$process->isSuccessful()) {
        #    throw new \RuntimeException($process->getErrorOutput());
        #}

        #echo $process->getOutput();
        #echo "Vagrantfile:\n{$vagrantfile}\n";

    }

    protected function getBox($box, $architecture)
    {
        if (!empty($box[$architecture])) {
            return $box[$architecture];
        }
        throw new \Exception("Cannot get a box for the architecture \"{$architecture}\"");
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
        if(is_file(APP_PATH."/{$config}")) {
            return APP_PATH."/{$config}";
        }
        if(is_file("app/config/{$config}")) {
            return "app/config/{$config}";
        }
        if(is_file(APP_PATH."/app/config/{$config}")) {
            return APP_PATH."/app/config/{$config}";
        }
        echo "Can't seem to find path for {$config}\n";
        return realpath("./{$config}")."\n";
    }

    protected function setupPackages($repos)
    {
        if(empty($repos)) {
            return false;
        }
        if(is_array($repos)) {
            $script = '';
            foreach ($repos as $repo) {
                $script .= $this->setupPackages($repo);
            }
        } else {
            $script = $this->installPackage($repos);
        }
        return $script;
    }

    protected function installPackage($package)
    {
        $os = $this->config['os'];
        if (!filter_var($package, FILTER_VALIDATE_URL) === false) {
            return ($os === "centos" ?
                "rpm -ivh {$package}" :
                "wget --quiet --output-document=- {$package} | dpkg --install -")."\n";
        }
        return ($os === "centos" ?
            "yum install -y " :
            "apt-get install -y ").
            "{$package}\n";
    }

    protected function setupServices($services)
    {
        $enableServices = '';
        $os = $this->config['os'];
        foreach ($services as $service) {
            $enableServices .= (
                $os === 'centos' ?
                "chkconfig {$service} on" :
                "chkconfig {$service} on"
            )."\n";
            $enableServices .= (
                $os === 'centos' ?
                    "service {$service} restart" :
                    "service {$service} restart"
            )."\n";

        }
        return $enableServices;
    }

    protected function processApplicationConfiguration()
    {
        $appConfig = $this->getConfigPath('app');
        if(!is_file($appConfig)) {
            throw new \Exception("The application config file at \"{$appConfig}\" does not exist");
        }
        $yaml = new Parser();
        $yaml->parse(file_get_contents($appConfig));

    }

    protected function setupDirectories($directories)
    {
        if (is_array($directories)) {
            $makeDirectories = '';
            foreach ($directories as $directory) {
                $makeDirectories .= $this->setupDirectories($directory);
            }
        }
        else {
            $makeDirectories = "if [ ! -d /vagrant/{$directories} ]; then\nmkdir -p /vagrant/{$directories}\nfi\n";
        }
        return $makeDirectories;
    }

    protected function setupConfig($config)
    {
        if (!is_array($config) || empty($config['path']) || empty($config['data'])) {
            return false;
        }
        return "echo \"{$config['data']}\" > {$config['path']}";
    }

    protected function setupServer($script, $vagrantfile, $box)
    {
        $vagrantfile = str_replace('###script###', $script, $vagrantfile);
        $vagrantfile = str_replace('###box###', $box, $vagrantfile);
        $vagrantfile = str_replace('###IP###', rand(1, 254), $vagrantfile);
        return $vagrantfile;
    }
}
