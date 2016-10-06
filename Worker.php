<?php
/**
 * This file is part of The simple daemon extension for the Yii 2 framework
 *
 * The daemon worker base class.
 *
 * @author Inpassor <inpassor@yandex.com>
 * @link https://github.com/Inpassor/yii2-daemon
 *
 * @version 0.1.2 (2016.10.06)
 *
 * All the daemon workes should extend this class.
 * The name of the daemon worker should start with the worker string UID and end with "Worker".
 */

namespace inpassor\daemon;

class Worker extends \yii\base\Object
{

    /**
     * @var bool If set to false, worker is disabled. This var take effect only if set in daemon's workersMap config.
     */
    public $active = true;

    /**
     * @var int The number of maximum processes of the daemon worker running at once.
     */
    public $maxProcesses = 1;

    /**
     * @var int The time, in seconds, the timer should delay in between executions of the daemon worker.
     */
    public $delay = 60;

    public $uid = '';
    public $logFile = '';
    public $errorLogFile = '';

    protected $_meetRequerements = false;
    protected $_stdin = null;
    protected $_stdout = null;
    protected $_stderr = null;

    /**
     * Redirects I/O sreams to the log files.
     */
    protected function _redirectIO()
    {
        if (!$this->_meetRequerements) {
            return;
        }
        if (defined('STDIN') && is_resource(STDIN)) {
            fclose(STDIN);
            $this->_stdin = fopen('/dev/null', 'r');
        }
        if (defined('STDOUT') && is_resource(STDOUT)) {
            fclose(STDOUT);
            $this->_stdout = fopen($this->logFile, 'a');
        }
        if (defined('STDERR') && is_resource(STDERR)) {
            ini_set('error_log', $this->errorLogFile);
            fclose(STDERR);
            $this->_stderr = fopen($this->errorLogFile, 'a');
        }
    }

    /**
     * Logs one or several messages into daemon log file.
     * @param array|string $messages
     */
    public function log($messages)
    {
        if (!is_array($messages)) {
            $messages = [$messages];
        }
        foreach ($messages as $message) {
            $_message = date('d.m.Y H:i:s') . ' - ' . $message . PHP_EOL;
            if ($this->_stdout && is_resource($this->_stdout)) {
                fwrite($this->_stdout, $_message);
            } else {
                echo $_message;
            }
        }
    }

    /**
     * The daemon worker main action. It should be overriden in a child class.
     */
    public function run()
    {
    }

    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        $this->_redirectIO();
        $this->_meetRequerements = extension_loaded('pcntl') && extension_loaded('posix');
        parent::__construct($config);
    }

}
