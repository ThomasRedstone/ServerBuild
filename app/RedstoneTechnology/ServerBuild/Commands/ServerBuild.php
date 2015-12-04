<?php
/**
 * Created by PhpStorm.
 * User: thomas
 * Date: 21/03/15
 * Time: 23:22
 */

namespace RedstoneTechnology\ServerBuild\Commands;

use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Input\InputArgument;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;


class ServerBuild extends Command
{
    protected $serverBuild;

    public function __construct(\RedstoneTechnology\ServerBuild\Utilities\ServerBuild $serverBuild)
    {
        $this->serverBuild = $serverBuild;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('ServerBuilder')
            ->setDescription('Builds a vagrant server')
            ->addOption(
                'name',
                null,
                InputOption::VALUE_REQUIRED,
                'Name your new server'
            )
            ->addOption(
                'config',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify path for the config file, either as a name, or a path '.
                '(if specifying a name, you need not include the file extension)/'
            )
            ->addOption(
                'gitUsername',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify a Git Username, which will be used with the repository provided to clone it'
            )
            ->addOption(
                'provider',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify the provider you\'re using, Virtualbox, VMWare or Paralelles./'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getOption('name');
        if (!$name) {
            throw new \Exception('Name is a required input.');
        }
        $config = $input->getOption('config');
        if (!$config) {
            $config = 'legacy';
        }
        $provider = $input->getOption('provider');
        if(!$provider) {
            $provider = 'virtualbox';
        }
        $gitUsername = $input->getOption('gitUsername');
        if(!$gitUsername) {
            $gitUsername = false;
        }
        $this->serverBuild->build($name, $config, $provider, $gitUsername, $input, $output);
    }
}
