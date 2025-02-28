<?php

namespace Zettr;

use Symfony\Component\Console\Output\OutputInterface;

class Processor {

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var string
     */
    protected $settingsFilePath;

    /**
     * @var HandlerCollection
     */
    protected $handlerCollection;

    /**
     * @var array
     */
    protected $groups = array();

    /**
     * @var array
     */
    protected $excludeGroups = array();

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;


    /**
     * Constructor
     *
     * @param $environment
     * @param $settingsFilePath
     * @param array $groups
     * @param array $excludeGroups
     * @throws \InvalidArgumentException
     */
    public function __construct($environment, $settingsFilePath, $groups=null, $excludeGroups=null, $skipEnvMissingError=false) {
        if (empty($environment)) {
            throw new \InvalidArgumentException('No environment parameter set.');
        }
        if (empty($settingsFilePath)) {
            throw new \InvalidArgumentException('No settings file set.');
        }
        if (!file_exists($settingsFilePath)) {
            throw new \InvalidArgumentException('Could not read settings file: "'.$settingsFilePath.'"');
        }

        $this->environment = $environment;
        $this->settingsFilePath = $settingsFilePath;
        $this->handlerCollection = new HandlerCollection();

        if ($groups) {
            $this->groups = Div::trimExplode(',', $groups, true);
        }
        if ($excludeGroups) {
            $this->excludeGroups = Div::trimExplode(',', $excludeGroups, true);
        }

        $this->handlerCollection->buildFromSettingsCSVFile(
            $this->settingsFilePath,
            $this->environment,
            'DEFAULT',
            $this->groups,
            $this->excludeGroups,
            $skipEnvMissingError
        );
    }

    public function setOutput(\Symfony\Component\Console\Output\OutputInterface $output) {
        $this->output = $output;
    }

    /**
     * @return bool
     */
    public function check() {
        foreach ($this->handlerCollection as $handler) { /* @var $handler Handler\HandlerInterface */
            $handler->check();
        }
    }

    /**
     * Apply settings to current environment
     *
     * @throws \Exception
     * @return bool
     */
    public function apply() {
        foreach ($this->handlerCollection as $handler) { /* @var $handler Handler\HandlerInterface */
            $res = $handler->apply();
            if (!$res) {
                throw new \Exception('Error in handler: ' . $handler->getLabel());
            }
        }
        DbConnection::commitAllTransactions();
        return true;
    }

    /**
     * Apply settings to current environment
     *
     * @throws \Exception
     * @return bool
     */
    public function dryRun() {
        foreach ($this->handlerCollection as $handler) { /* @var $handler Handler\HandlerInterface */
            $this->output($handler->getLabel());
        }
        return true;
    }

    /**
     * Get value
     *
     * @param $handlerClassName
     * @param $param1
     * @param $param2
     * @param $param3
     * @throws \Exception
     * @internal param $handler
     * @return Handler\AbstractHandler
     */
    public function getHandler($handlerClassName, $param1, $param2, $param3) {
        $handler = $this->handlerCollection->getHandler($handlerClassName, $param1, $param2, $param3);
        if ($handler === false) {
            throw new \Exception('No handler found with given specification: '."$handlerClassName, $param1, $param2, $param3");
        }
        return $handler;
    }

    /**
     * Print result
     */
    public function printResults() {
        $statistics = array();
        foreach ($this->handlerCollection as $handler) { /* @var $handler Handler\HandlerInterface */
            // Collecting some statistics
            $statistics[$handler->getStatus()][] = $handler;

            // skipping handlers that weren't executed
            if ($handler->getStatus() == Handler\HandlerInterface::STATUS_NOTEXECUTED) {
                continue;
            }

            $this->output();
            $label = $handler->getLabel();
            $this->output($label);
            $this->output(str_repeat('-', strlen($label)));

            foreach ($handler->getMessages() as $message) { /* @var $message Message */
                $this->output($message->getColoredText());
            }
        }

        $this->output();
        $this->output('Status summary:');
        $this->output(str_repeat('=', strlen(('Status summary:'))));

        foreach ($statistics as $status => $handlers) {
            $this->output(sprintf("%s: %s handler(s)", $status, count($handlers)));
        }

        $this->output();
        if (count($this->groups)) {
            $this->output('Groups: ' . implode(', ', $this->groups));
        }
        if (count($this->excludeGroups)) {
            $this->output('Excluded groups: ' . implode(', ', $this->excludeGroups));
        }

    }

    protected function output($message='', $newLine=true) {
        if ($this->output) {
            if ($newLine) {
                $this->output->writeln($message);
            } else {
                $this->output->write($message);
            }
        }
    }

}