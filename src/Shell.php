<?php

namespace React\Sh;

use Clue\React\Stdio\Stdio;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class Shell
{
    private $loop;
    private $stdio;
    private $scope = [];

    public function __construct(?LoopInterface $loop = null)
    {
        $this->loop = $loop ?? Factory::create();
        $this->stdio = new Stdio($this->loop);
        $this->cloner = new VarCloner();
        $this->dumper = new CliDumper();

        $this->stdio->setPrompt('>> ');
        $this->stdio->on('data', [$this, 'handleData']);

        // set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        //     throw new \Exception($errstr, $errno);
        // });
    }

    public function handleData($line)
    {
        $line = rtrim($line);
        $all = $this->stdio->listHistory();
    
        switch ($line) {
            case 'exit';
                $this->stdio->write('Goodbye'.PHP_EOL);
                return $this->stdio->end();
            case 'clear':
                system('clear');
                return;
        }
    
        if (empty($line)) return;
    
        try {
            unset($this->scope['_'], $this->scope['line']);
            extract($this->scope);
            eval('$_ = '.$line);
            $this->scope = get_defined_vars();
    
            $context = $this->dumper->dump($this->cloner->cloneVar($_), true);
            $this->stdio->write('=> '.$context);
            
            if ($line !== end($all)) {
                $this->stdio->addHistory($line);
            }
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'unexpected end of file') !== false) {
                $this->handleData($line.';');
            } else {
                $this->stdio->write($e->getMessage().PHP_EOL.$e->getTraceAsString());
            }
        }
    }

    public function setScope(array $scope)
    {
        $this->scope = $scope;
    }

    public function run(?array $scope = null)
    {
        $this->setScope($scope ?? $this->scope);
        return $this->loop->run();
    }
}