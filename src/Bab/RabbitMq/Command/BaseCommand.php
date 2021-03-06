<?php

namespace Bab\RabbitMq\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Bab\RabbitMq\VhostManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Bab\RabbitMq\Action\RealAction;
use Bab\RabbitMq\HttpClient\CurlClient;
use Bab\RabbitMq\Logger\CliLogger;
use Symfony\Component\Filesystem\Filesystem;

class BaseCommand extends Command
{
    protected function configure()
    {
        $this
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'Connection name (if you use a ~/.rabbitmq_admin_toolkit file)')
            ->addOption('host', 'H', InputOption::VALUE_REQUIRED, 'Which host?', '127.0.0.1')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Which user?', 'guest')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Which password? If nothing provided, password is asked', null)
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Which port?', 15672)
        ;
    }

    /**
     * getVhostManager
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param string          $vhost
     *
     * @return VhostManager
     */
    protected function getVhostManager(InputInterface $input, OutputInterface $output, $vhost)
    {
        $credentials = $this->getCredentials($input, $output);

        $logger = new CliLogger($output);
        $httpClient = new CurlClient($credentials['host'], $credentials['port'], $credentials['user'], $credentials['password']);
        $action = new RealAction($httpClient);
        $action->setLogger($logger);

        $credentials['vhost'] = $vhost;
        $vhostManager = new VhostManager($credentials, $action, $httpClient);

        $vhostManager->setLogger($logger);

        return $vhostManager;
    }

    /**
     * getCredentials
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return array
     */
    protected function getCredentials(InputInterface $input, OutputInterface $output)
    {
        if (null !== $connection = $input->getOption('connection')) {
            $fs = new Filesystem();

            $file = rtrim(getenv('HOME'), '/') . '/.rabbitmq_admin_toolkit';
            if (!$fs->exists($file)) {
                throw new \InvalidArgumentException('Can\'t use connection option without a ~/.rabbitmq_admin_toolkit file');
            }
            $credentials = json_decode(file_get_contents($file), true);
            if (!isset($credentials[$connection])) {
                throw new \InvalidArgumentException("Connection $connection not found in ~/.rabbitmq_admin_toolkit");
            }

            $defaultCredentials = [
                'host' => '127.0.0.1',
                'port' => 15672,
                'user' => 'root',
                'password' => 'root',
            ];

            return array_merge($defaultCredentials, $credentials[$connection]);
        }

        $credentials = [
            'host' => $input->getOption('host'),
            'port' => $input->getOption('port'),
            'user' => $input->getOption('user')
        ];

        if ($input->hasParameterOption(['--password', '-p'])) {
            $credentials['password'] = $input->getOption('password');
        } elseif (null === $input->getOption('password')) {
            $dialog = $this->getHelperSet()->get('dialog');
            $credentials['password'] = $dialog->askHiddenResponse($output, 'Password?', false);
        }

        return $credentials;
    }
}
