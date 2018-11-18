<?php
/**
 * This file is part of The simple daemon extension for the Yii 2 framework
 *
 * The daemon worker base class.
 *
 * @author Inpassor <inpassor@yandex.com>
 * @link https://github.com/Inpassor/yii2-daemon
 *
 * All the daemon workes should extend this class.
 */

namespace inpassor\daemon;

use yii\helpers\FileHelper;

class Worker extends \yii\base\BaseObject
{

    /**
     * @var bool If set to false, worker is disabled. This parameter take effect only if set in daemon's workersMap config.
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

    public $uid;
    public $logFile;
    public $errorLogFile;

    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
        ini_set('error_log', $this->errorLogFile);
    }

    /**
     * @inheritdoc
     * @throws \yii\base\Exception
     */
    public function init()
    {
        parent::init();

        if ($this->errorLogFile) {
            $this->errorLogFile = \Yii::getAlias($this->errorLogFile);
            $errorLogDir = dirname($this->errorLogFile);
            if (!is_dir($errorLogDir)) {
                FileHelper::createDirectory($errorLogDir);
            }
        }
        if ($this->logFile) {
            $this->logFile = \Yii::getAlias($this->logFile);
            $logDir = dirname($this->logFile);
            if (!is_dir($logDir)) {
                FileHelper::createDirectory($logDir);
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
     * Logs one or several messages into daemon log file.
     * @param array|string $messages
     */
    public function log($messages)
    {
        if (!is_array($messages)) {
            $messages = [$messages];
        }
        foreach ($messages as $message) {
            file_put_contents($this->logFile, date('d.m.Y H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}
