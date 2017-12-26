<?php

namespace App\Console\Command;

use App\Server\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoaderSingleServer extends Command
{

    protected function configure()
    {
        $this->addOption(
            'option',
            '',
            InputArgument::OPTIONAL,
            'control server'
        );

        $this->addOption(
            'serve',
            '',
            InputArgument::OPTIONAL,
            'server name'
        );

        $this->addOption(
            'daemon',
            '',
            InputArgument::OPTIONAL,
            'is start sever in daemon'
        );

        $this->setName('tcp:single');
        $this->setHelp("tcp:single --option=start|stop|restart|status --serve=servename --daemon=true");
        $this->setDescription("operation single Tcp Serve.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $option = $input->getOption('option');
        $serve = $input->getOption('serve');
        if (is_null($option) || is_null($serve)) {
            $output->writeln("<error>--option && --serve must be set!</error>");
            return;
        }
        //加载swoole ini 配置
        Server::loadIni();
        //判断serve是否存在配置中
        if (!array_key_exists($serve, Server::$ini)) {
            $output->writeln("<error>没有发现服务:$serve 在swoole.ini中</error>");
            return;
        } else {
            $is_daemon = false;
            $daemon = $input->getOption('daemon');
            if (!empty($daemon)) {
                if ($daemon !== "true" && $daemon !== "false") {
                    $output->writeln("<error>undefined option for $daemon</error>");
                    return;
                }
                $is_daemon = true;
            }
            switch ($option) {
                case "start":
                    //启动server进程
                    Server::start($is_daemon, $serve);
                    break;
                case "stop":
                    //杀死server进程
                    Server::stop($serve);
                    break;
                case "restart":
                case "reload":
                    Server::restart($serve);
                    break;
                case "status":
                    Server::status($serve);
                    break;
                default:
                    $output->writeln("<error>undefined option for $option</error>");
                    break;
            }
        }
    }

}