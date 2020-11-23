<?php

namespace React\Sh;

use Clue\React\Stdio\Stdio;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class Shell
{
    private $cloner;
    private $dumper;
    private $loop;
    private $stdio;
    private $scope = [];

    public function __construct(?LoopInterface $loop = null)
    {
        $this->loop = $loop ?? Factory::create();
        $this->stdio = new Stdio($this->loop);
        $this->cloner = new VarCloner();
        $this->dumper = new CliDumper();
        $this->cloner->setMaxItems(5);

        $this->stdio->setPrompt('>> ');
        $this->stdio->on('data', [$this, 'handleData']);
        $this->stdio->setAutocomplete(function () {
            return [
                'quit',
                'exit',
                'clear',
                'ls',
                'await',
            ];
        });
    }

    public function handleError($errno, $errstr, $errfile, $errline)
    {  
        // supress warnings not in error reporting 
        if (! (error_reporting() & $errno)) return;

        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public function handleData($line, $first = true)
    {
        $line = rtrim($line);
    
        switch ($line) {
            case 'exit':
            case 'quit':
                $this->stdio->write('Goodbye'.PHP_EOL);
                $this->stdio->end();
                return $this->loop->stop();
            case 'clear':
                system('clear');
                return;
            case 'ls':
                foreach (array_keys($this->scope) as $var) {
                    $this->stdio->write('$'.$var.' ');
                }

                $this->stdio->write(PHP_EOL);
                return;
        }
    
        if (empty($line)) return;

        if ($first) {
            $this->stdio->addHistory($line);
        }
        
        $await = false;
        set_error_handler([$this, 'handleError']);

        try {
            $args = explode(' ', $line);
            
            if (array_shift($args) == 'await') {
                $await = true;
                $line = implode($args);

                $_ = (function ($line) {
                    $_ = null;

                    extract($this->scope);
                    eval('$_prom = '.$line);
                    $_prom->done(function ($result) use (&$_) {
                        $_ = $result;
                        $this->scope = array_merge($this->scope, get_defined_vars());
                        unset($this->scope['line']);

                        $context = $this->dumper->dump($this->cloner->cloneVar($_), true);
                        $this->stdio->write('=> '.$context);

                        restore_error_handler();
                    });
                })($line);
            } else {
                $_ = (function ($line) {
                    $_ = null;
                    
                    extract($this->scope);
                    eval('$_ = '.$line);
                    $this->scope = get_defined_vars();
                    unset($this->scope['line']);
    
                    return $_;
                })($line);
        
                $context = $this->dumper->dump($this->cloner->cloneVar($_), true);
                $this->stdio->write('=> '.$context);

                restore_error_handler();
            }
        } catch (\Throwable $e) {
            restore_error_handler();

            if (strpos($e->getMessage(), 'unexpected end of file') !== false) {
                $this->handleData(($await ? 'await ' : '') . $line.';', false);
            } else {
                $this->stdio->write($e->getMessage().PHP_EOL.$e->getTraceAsString().PHP_EOL);
            }
        }
    }

    public function getStdio()
    {
        return $this->stdio;
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