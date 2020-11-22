<?php

namespace React\Sh;

use Clue\React\Stdio\Stdio;

if (class_exists('\\Monolog\\Handler\\AbstractProcessingHandler'))
{
    class StdioHandler extends \Monolog\Handler\AbstractProcessingHandler
    {
        private $stdio;

        public function __construct(Stdio $stdio, $level = \Monolog\Logger::DEBUG, bool $bubble = true)
        {
            parent::__construct($level, $bubble);
            $this->stdio = $stdio;
        }

        protected function write(array $record): void
        {
            $this->stdio->write($record['formatted']);
        }
    }
}