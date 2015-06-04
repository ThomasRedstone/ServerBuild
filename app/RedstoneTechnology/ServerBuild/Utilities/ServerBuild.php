<?php

namespace RedstoneTechnology\ServerBuild\Utilities;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\QuestionHelper;

class ServerBuild
{
    protected $script = '';
    protected $config = [];

    public function __construct()
    {
    }

    public function build($name, $config, $architecture, $gitUsername, $input, $output)

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
        $appConfigPath = $this->getConfigPath('app');
        if (!is_file($appConfigPath)) {
            throw new \Exception("The application config file at \"{$appConfigPath}\" does not exist");
        }
        $yaml = new Parser();
        $appConfig = $yaml->parse(file_get_contents($appConfigPath));
        if (!empty($appConfig['repository']))
            if (is_dir($name)) {
                throw new \Exception("A directory with \"{$name}\" already exists");
            }
        $box = $this->getBox($this->config['box'], $architecture);
        #die(getcwd()."\n");
        mkdir($name);
        chdir($name);
        echo "#Setting up Repository\n";
	    if(!empty($this->config['prebuildCommands'])) {
            $this->script .= "#Run Prebuild Commands\n" . $this->setupCommands($this->config['prebuildCommands']);
        }
        $this->setupRepository($appConfig['repository'], $gitUsername, $input, $output);
        $this->script .= "#Setup Repositories:\n" . $this->setupPackages($this->config['repos']);
        $this->script .= "#Setup Packages:\n" . $this->setupPackages($this->config['packages'], true);
        $this->script .= "#Enable Services\n" . $this->setupServices($this->config['services']);
        $this->script .= "#Setup Directories:\n" . $this->setupDirectories($this->config['directories']);
        $this->script .= "#Setup Config:\n" . $this->setupConfig($this->config['httpd-config'], $this->config['paths']['httpd']);
        $this->script .= "#Setup Config:\n" . $this->setupConfig($this->config['php-config'], $this->config['paths']['php']);
        $this->script .= "#Setup App\n" . $this->processApplicationConfiguration($appConfig);
        $this->script .= "#Restart Services\n" . $this->setupServices($this->config['services'], 'restart');
        #die($this->script);
        $this->script .= "#Run Commands\n" . $this->setupCommands($this->config['commands']);
        $vagrantfile = $this->setupServer($this->script, $this->config['vagrantfile'], $box, $name);
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
        if (is_file($config)) {
            return $config;
        }
        if (is_file(APP_PATH . "/{$config}")) {
            return APP_PATH . "/{$config}";
        }
        if (is_file("app/config/{$config}")) {
            return "app/config/{$config}";
        }
        if (is_file(APP_PATH . "/app/config/{$config}")) {
            return APP_PATH . "/app/config/{$config}";
        }
        echo "Can't seem to find path for {$config}\n";
        return realpath("./{$config}") . "\n";
    }

    protected function setupPackages($packages, $compact = false)
    {
        if (empty($packages)) {
            return false;
        }
        if (is_array($packages)) {
            $script = '';
            foreach ($packages as $package) {
                if($compact === true && $script != '') {
                    $script .= " {$package}";
                    continue;
                }
                $script .= $this->setupPackages($package, $compact);
            }
            if ($compact === true) {
                $script .= "\n";
            }
        } else {
            $script = $this->installPackage($packages, $compact);
        }
        return $script;
    }

    protected function installPackage($package, $compact = false)
    {
        $os = $this->config['os'];
        if (!filter_var($package, FILTER_VALIDATE_URL) === false) {
            return ($os === "centos" ?
                "rpm -ivh {$package}" :
                "wget --quiet --output-document=- {$package} | dpkg --install -") . "\n";
        }
        $script = ($os === "centos" ?
            "yum install -y " :
            "apt-get install -q -y ") .
        "{$package}";
        if ($compact === false) {
            $script .= "\n";
        }
        return $script;
    }

    protected function setupServices($services, $action = 'enable')
    {
        /**
         * @todo: use switch if we add another OS (CentOS and Ubuntu at the moment).
         */
        $enableServices = '';
        $os = $this->config['os'];
        foreach ($services as $service) {
            switch ($action) {
                case 'enable':
                    $enableServices .= (
                        $os === 'centos' ?
                            "chkconfig {$service} on" :
                            "update-rc.d {$service} defaults"
                        ) . "\n";
                case 'restart':
                    $enableServices .= (
                        $os === 'centos' ?
                            "service {$service} restart" :
                            "service {$service} restart"
                        ) . "\n";
                    break;
            }
        }
        return $enableServices;
    }

    protected function processApplicationConfiguration($appConfig)
    {
        $script = '';
        $script .= "#Setup App specific Directories\n" . $this->setupDirectories($appConfig['directories'], true);
        $script .= "#Run App specific Commands\n" . $this->setupCommands($appConfig['commands']);
        $script .= "#Run Composer Install\n" . $this->setupComposer();
        if(!empty($appConfig['database'])) {
            $script .= "#Run Database Setup\n" . $this->setupDatabase($appConfig['database']);
        }
        return $script;
    }

    protected function setupDirectories($directories, $global = false)
    {
        if (is_array($directories)) {
            $makeDirectories = '';
            foreach ($directories as $directory) {
                $makeDirectories .= $this->setupDirectories($directory, $global);
            }
            return $makeDirectories;
        }
        if ($global === false) {
            $directories = "/vagrant/{$directories}";
        }
        if (empty($this->config['webUser'])) {
            die("No web user set\n" . var_export($this->config, 1));
        }
        return "if [ ! -d {$directories} ]; then\nmkdir -p {$directories}\nfi;\nchown {$this->config['webUser']}:vagrant {$directories};\nchmod 775 {$directories}\n";
    }

    protected function setupConfig($config, $path)
    {
        if (empty($path) || empty($config)) {
            return false;
        }
        return "echo \"{$config}\" > {$path}\n";
    }

    protected function setupRepository($repository, $gitUsername, $input, $output)
    {
        if ($gitUsername !== false) {
            $helper = new QuestionHelper();
            $question = new Question("What is your Git password?");
            $question->setHidden(true);
            $gitPassword = $helper->ask($input, $output, $question);
            echo "$repository\n";
            $repository = str_replace('https://', "http://{$gitUsername}:{$gitPassword}@", $repository);
            $process = new Process("git clone {$repository} www");

            try {
                $process->mustRun();

                echo $process->getOutput();
            } catch (ProcessFailedException $e) {
                echo $e->getMessage();
            }
        }
    }

    protected function setupComposer()
    {
        return "if [ -f /vagrant/www/composer.json ]; then \n".
        "curl -sS https://getcomposer.org/installer | php\n".
        "mv composer.phar /usr/local/bin/composer\n".
        "cd /vagrant/www;\n".
        "/usr/local/bin/composer install;\n".
        "fi\n";
    }

    protected function setupCommands($commands)
    {
        $script = '';
        if(is_array($commands)) {
            foreach ($commands as $command) {
                $script .= "{$command}\n";
            }
        }
        return $script;
    }

    protected function setupServer($script, $vagrantfile, $box, $name)
    {
        $vagrantfile = str_replace('###script###', $script, $vagrantfile);
        $vagrantfile = str_replace('###name###', $name, $vagrantfile);
        $vagrantfile = str_replace('###box###', $box, $vagrantfile);
        $vagrantfile = str_replace('###IP###', rand(1, 254), $vagrantfile);
        return $vagrantfile;
    }

    protected function setupDatabase($config)
    {
        $sqlCommand = $this->config['db'];
        $command = "{$sqlCommand} -u root -e 'create database {$config['user_database']};';\n" .
            "{$sqlCommand} -u root -e 'grant ALL on {$config['user_database']}.* to " .
            "`{$config['user_user']}`@`localhost` identified by \"{$config['user_password']}\"'\n";
        foreach($config['scripts'] as $script) {
            $command .= "{$sqlCommand} -u root {$config['user_database']} < /vagrant/{$script}\n";
        }
        return $command;
    }
}

