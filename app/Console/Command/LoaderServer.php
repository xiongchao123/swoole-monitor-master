<?php

namespace App\Console\Command;

use App\Server\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoaderServer extends Command
{

    protected function configure()
    {
        $this->addArgument(
            'name',
            InputArgument::REQUIRED,
            'Who do you want to greet?'
        );
        $this->addOption(
            'daemon',
            '',
            InputArgument::OPTIONAL,
            'is start sever in daemon'
        );

        $this->setName('tcp:serve');
        $this->setHelp("tcp:serve start|stop|restart|reload");
        $this->setDescription("operation Tcp Serve.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $args = $input->getArguments();
        $name = strtolower($args['name']);
        $is_daemon=false;
        $daemon=$input->getOption('daemon');
        if(!empty($daemon)){
            if($daemon !== "true" && $daemon !== "false"){
                $output->writeln("<error>undefined option for $daemon</error>");
                return;
            }
            $is_daemon=true;
        }
       switch ($name) {
            case "start":
                //加载swoole ini 配置
                Server::loadIni();

                //启动server进程
                Server::start($is_daemon);
                break;
            case "stop":
                //加载swoole ini 配置
                Server::loadIni();
                //杀死server进程
                Server::stop();
                break;
            case "restart":
            case "reload":
                break;
            default:
                $output->writeln("<error>undefined command for $name</error>");
                break;
        }
    }

    static function init($name, $file)
    {
        /*$code = "<?php\nnamespace App\\Controller;\n\n";
        $code .= "use Swoole\\Controller;\n\n";
        $code .= "class $name extends Controller\n{\n\n}";
        return file_put_contents($file, $code);*/
    }
}