<?php
namespace RedstoneTechnology\ServerBuild\Utilities;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\QuestionHelper;

/**
 * Class ServerBuild
 * @package RedstoneTechnology\ServerBuild\Utilities
 */
class ServerBuild
{
    protected $script = '';
    protected $config = [];
    protected $name;
    protected $packages;

    /**
     * ServerBuild constructor.
     * @param Packages $packages
     */
    public function __construct(\RedstoneTechnology\ServerBuild\Utilities\Packages $packages)
    {
        $this->packages = $packages;
    }

    /**
     * @param $name
     * @param $config
     * @param $provider
     * @param $gitUsername
     * @param $input
     * @param $output
     * @throws \Exception
     */
    public function build($name, $config, $provider, $gitUsername, $input, $output)
    {
        $yaml = new Parser();
        $this->name = $name;
        $configDefaultsPath = $this->getConfigPath('defaults');
        $configPath = $this->getConfigPath($config);
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
        if (empty($appConfig['repository'])) {
            throw new \Exception("A repository must be provided in the app config.");
        }
        if (!is_dir($name)) {
            throw new \Exception("A directory with \"{$name}\" must exists, with a config.yml file.");
        }
        $box = $this->getBox($this->config['box'], $provider);
        chdir($name);
        echo "#Setting up Repository\n";
	    if (!empty($this->config['prebuildCommands'])) {
            $this->script .= "#Run Prebuild Commands\n" . $this->setupCommands($this->config['prebuildCommands']);
        }
        $this->setupRepository($appConfig['repository'], $gitUsername, $input, $output);
        $this->script .= "#Setup Repositories:\n" . $this->packages->setupPackages($this->config['repos']);
        $this->script .= "#Setup Packages:\n" . $this->packages->setupPackages($this->config['packages'], true);
        $this->script .= "#Enable Services\n" . $this->packages->setupServices($this->config['services'], 'enable');
        $this->script .= "#Setup Directories:\n" . $this->setupDirectories($this->config['directories']);
        $this->script .= "#Setup Config:\n" .
            $this->setupConfig($this->config['httpd-config'], $this->config['paths']['httpd']);
        $this->script .= "#Setup Config:\n" .
            $this->setupConfig($this->config['php-config'], $this->config['paths']['php']);
        $this->script .= "#Setup App\n" . $this->processApplicationConfiguration($appConfig);
        $this->script .= "#Restart Services\n" . $this->setupServices($this->config['services'], 'restart');
        $this->script .= "#Run Commands\n" . $this->setupCommands($this->config['script']);
        $vagrantfile = $this->setupServer($this->script, $this->config['vagrantfile'], $box, $provider, $name);
        file_put_contents("Vagrantfile", $vagrantfile);
        echo "Your Vagrantfile is now finished, to start it run:\n".
            "    cd {$name};vagrant up --provider {$provider}\n";
    }

    /**
     * @param $box
     * @param $provider
     * @return mixed
     * @throws \Exception
     */
    protected function getBox($box, $provider)
    {
        if (!empty($box[$provider])) {
            return $box[$provider];
        }
        throw new \Exception("Cannot get a box for the provider \"{$provider}\"".var_export([$box, $provider], 1));
    }

    /**
     * @param $config
     * @return string
     */
    protected function getConfigPath($config)
    {
        $fileInfo = pathinfo($config);
        if (empty($fileInfo['extension'])) {
            $config .= '.yml';
        }
        if ($config === 'app.yml' && is_file(APP_PATH."/../{$this->name}/{$config}")) {
            return APP_PATH."/../{$this->name}/{$config}";
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
        if (is_file(APP_PATH . "/distros/{$config}")) {
            return APP_PATH . "/distros/{$config}";
        }
        if (is_file(APP_PATH . "/app/config/{$config}")) {
            return APP_PATH . "/app/config/{$config}";
        }
        echo "Can't seem to find path for {$config}\n";
        return realpath("./{$config}") . "\n";
    }

    /**
     * @param $services
     * @param $action
     * @return string
     */
    protected function setupServices($services, $action)
    {
        $enableServices = '';
        foreach ($services as $service) {
            $enableServices .= str_replace('{service}', $service, $this->config['commands']['service'][$action])."\n";
        }
        return $enableServices;
    }

    /**
     * @param $appConfig
     * @return string
     */
    protected function processApplicationConfiguration($appConfig)
    {
        $script = '';
        if (!empty($this->setupDirectories($appConfig['directories']))) {
            $script .= "#Setup App specific Directories\n" . $this->setupDirectories($appConfig['directories'], true);
        }
        if (!empty($this->setupDirectories($appConfig['commands']))) {
            $script .= "#Run App specific Commands\n" . $this->setupCommands($appConfig['commands']);
        }
        $script .= "#Run Composer Install\n" . $this->setupComposer();
        if (!empty($appConfig['database'])) {
            $script .= "#Run Database Setup\n" . $this->setupDatabase($appConfig['database']);
        }
        return $script;
    }

    /**
     * @param $directories
     * @param bool $global
     * @return string
     */
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
        return "if [ ! -d {$directories} ]; then\nmkdir -p {$directories}\nfi;".
            "\nchown {$this->config['webUser']}:vagrant {$directories};\nchmod 775 {$directories}\n";
    }

    /**
     * @param $config
     * @param $path
     * @return bool|string
     */
    protected function setupConfig($config, $path)
    {
        if (empty($path) || empty($config)) {
            return false;
        }
        return "echo \"{$config}\" > {$path}\n";
    }

    /**
     * @param $repository
     * @param $gitUsername
     * @param $input
     * @param $output
     */
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

    /**
     * @return string
     */
    protected function setupComposer()
    {
        return "if [ -f /vagrant/www/composer.json ]; then \n".
        "curl -sS https://getcomposer.org/installer | php\n".
        "mv composer.phar /usr/local/bin/composer\n".
        "cd /vagrant/www;\n".
        "/usr/local/bin/composer install;\n".
        "fi\n";
    }

    /**
     * @param $commands
     * @return string
     */
    protected function setupCommands($commands)
    {
        $script = '';
        if (is_array($commands)) {
            foreach ($commands as $command) {
                $script .= "{$command}\n";
            }
        }
        return $script;
    }

    /**
     * @param $script
     * @param $vagrantfile
     * @param $box
     * @param $provider
     * @param $name
     * @return mixed
     */
    protected function setupServer($script, $vagrantfile, $box, $provider, $name)
    {
        $vagrantfile = str_replace('###script###', $script, $vagrantfile);
        $vagrantfile = str_replace('###name###', $name, $vagrantfile);
        $vagrantfile = str_replace('###box###', $box, $vagrantfile);
        $vagrantfile = str_replace('###IP###', rand(1, 254), $vagrantfile);
        $vagrantfile = str_replace('###NETWORK###', ($provider === 'parallels' ? '30' : '31'), $vagrantfile);
        return $vagrantfile;
    }

    /**
     * @param $config
     * @return string
     */
    protected function setupDatabase($config)
    {
        $sqlCommand = $this->config['db'];
        $command = "{$sqlCommand} -u root -e 'create database {$config['user_database']};';\n" .
            "{$sqlCommand} -u root -e 'grant ALL on {$config['user_database']}.* to " .
            "`{$config['user_user']}`@`localhost` identified by \"{$config['user_password']}\"'\n";
        if (!empty($config['scripts']) && is_array($config['scripts'])) {
            foreach ($config['scripts'] as $script) {
                $command .= "{$sqlCommand} -u root {$config['user_database']} < /vagrant/{$script}\n";
            }
        }
        return $command;
    }
}
