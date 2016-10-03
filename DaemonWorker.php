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
     * @var \inpassor\daemon\DaemonController
     */
    public $daemon = null;
    public $uid = '';
    public $pids = [];
    public $tick = 1;

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
            fwrite($this->daemon->stdout, date('d.m.Y H:i:s') . ' - ' . $message . PHP_EOL);
        }
    }

    /**
     * The daemon worker main action. It should be overriden in a child class.
     */
    public function run()
    {
    }

}
