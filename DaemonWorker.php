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

class DaemonWorker  extends \yii\base\Object
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

    /**
     * The daemon worker main action. It should be overriden in a child class.
     * Inside the method all echoed data will be written to the daemon log file.
     * If the method returns array, it will be stored and passed to the next execution of this method.
     */
    public function run($params)
    {
        return $params;
    }

}
