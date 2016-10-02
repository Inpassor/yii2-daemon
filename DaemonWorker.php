<?php
/**
 * The daemon worker base class.
 *
 * @author Inpassor <inpassor@yandex.com>
 * @link https://github.com/Inpassor/yii2-daemon
 *
 * @version 0.1 (2016.10.01)
 *
 * All the daemon workes should extend this class.
 * The name of the daemon worker should start with the worker string UID and end with "Worker".
 */

namespace inpassor\daemon;

class DaemonWorker extends \yii\base\Object
{

    /**
     * @var bool
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

    /**
     * @var array params to be passed to run method of the daemon worker.
     */
    public $params = [];

    public $logFile = null;

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
            file_put_contents($this->logFile, date('d.m.Y H:i:s') . ' - ' . $message . PHP_EOL);
        }
    }

    /**
     * The daemon worker main action. It should be overriden in a child class.
     * If the method returns array, it will be stored and passed to the next execution of this method.
     */
    public function run($params = [])
    {
        return $params;
    }

}
