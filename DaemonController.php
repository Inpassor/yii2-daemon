<?php
/**
 * The simple daemon extension for the Yii 2 framework
 *
 * @author Inpassor <inpassor@yandex.com>
 * @link https://github.com/Inpassor/yii2-daemon
 *
 * @version 0.1 (2016.10.01)
 */

namespace inpassor\daemon;

use Yii;
use \yii\helpers\FileHelper;

set_time_limit(0);
ignore_user_abort(true);
declare(ticks = 1);

class DaemonController extends \yii\console\Controller
{

    /**
     * @var string the daemon UID. Givind daemons different UIDs makes possible to run several daemons.
     * It is possible to set this parameter through command line:
     * yii daemon --uid=<TheDaemonUID>
     */
    public $uid = 'daemon';

    /**
     * @var string the daemon workers directory. Defaults to @app/daemon/<TheDaemonUID>
     * It is possible to set this parameter through command line:
     * yii daemon --workersdir=<path_to_workers>
     */
    public $workersdir = null;

    protected $_pid = false;
    protected $_stop = false;
    protected $_workersData = [];
    protected $_filesDir = null;
    protected $_pidFile = null;
    protected $_logFile = null;
    protected $_errorLogFile = null;
    protected $_stdout = null;
    protected $_stderr = null;

    /**
     * @param $signo
     * @param $pid
     * @param $status
     */
    protected function _signalHandler($signo, $pid = null, $status = null)
    {
        switch ($signo) {
            case SIGTERM:
                $this->_stop = true;
                break;
            case SIGCHLD:
                if (!$pid) {
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }
                while ($pid > 0) {
                    foreach ($this->_workersData as $workerUid => $data) {
                        if (($key = array_search($pid, $data['pids'])) !== false) {
                            unset($this->_workersData[$workerUid]['_pids'][$key]);
                        }
                    }
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }
                break;
        }
    }

    /**
     * Logs one or several messages into log file.
     * @param array|string $messages
     */
    protected function _log($messages)
    {
        if (!is_array($messages)) {
            $messages = [$messages];
        }
        foreach ($messages as $message) {
            echo date('d.m.Y H:i:s') . ' - ' . $message . PHP_EOL;
        }
    }

    /**
     * Gets the PID of the main process, false on fail.
     * @return bool|string
     */
    protected function _getPid()
    {
        $this->_pidFile = $this->_filesDir . DIRECTORY_SEPARATOR . $this->uid . '.pid';
        if (!file_exists($this->_pidFile)) {
            return false;
        }
        return (($this->_pid = file_get_contents($this->_pidFile)) && posix_kill($this->_pid, 0)) ? $this->_pid : false;
    }

    /**
     * Tries to kill the PID of the main process.
     */
    protected function _killPid()
    {
        if (file_exists($this->_pidFile)) {
            unlink($this->_pidFile);
        }
        if ($this->_pid) {
            posix_kill($this->_pid, SIGTERM);
        }
    }

    /**
     * Redirects I/O streams to log files.
     */
    protected function _redirectIO()
    {
        $this->_logFile = $this->_filesDir . DIRECTORY_SEPARATOR . $this->uid . '.log';
        $this->_errorLogFile = $this->_filesDir . DIRECTORY_SEPARATOR . $this->uid . '_error.log';
        ini_set('error_log', $this->_errorLogFile);
        if (defined('STDERR')) {
            fclose(STDERR);
            $this->_stderr = fopen($this->_errorLogFile, 'a');
        }
        if (defined('STDOUT')) {
            fclose(STDOUT);
            $this->_stdout = fopen($this->_logFile, 'a');
        }
    }

    /**
     * Gets all the daemon workers and initializes them.
     * @return bool true on success, false on fail.
     */
    protected function _getWorkers()
    {
        if (!$this->workersdir) {
            $this->workersdir = Yii::getAlias('@app/daemon/' . $this->uid);
        }
        if (!file_exists($this->workersdir)) {
            return false;
        }
        $workers = FileHelper::findFiles($this->workersdir, [
            'only' => ['*Worker.php'],
        ]);
        if (!$workers) {
            return false;
        }
        foreach ($workers as $workerFileName) {
            $workerUid = str_replace('Worker.php', '', $workerFileName);
            $workerClass = array_pop(explode('/', strtr($workerFileName, [
                '.php' => '',
                '\\' => '/',
            ])));
            $worker = new $workerClass();
            if (!$worker->active) {
                continue;
            }
            $this->_workersData[$workerUid] = [
                '_worker' => $worker,
                '_pids' => [],
                '_tick' => 1,
                'maxProcesses' => $worker->maxProcesses,
                'delay' => $worker->delay,
                'params' => $worker->params,
            ];
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->_filesDir = Yii::getAlias('@runtime/daemon/' . $this->uid);
        if (!file_exists($this->_filesDir)) {
            FileHelper::createDirectory($this->_filesDir, 0755, true);
        }
    }

    /**
     * The daemon start command.
     * @return int
     */
    public function actionStart()
    {
        $message = 'Starting service... ';

        if ($this->_getPid() === false) {
            if (!$this->_getWorkers()) {
                $message .= 'No tasks found. Stopping!';
                echo $message . PHP_EOL;
                $this->_redirectIO();
                $this->_log($message);
                return 3;
            }
            pcntl_signal(SIGTERM, [$this, '_signalHandler']);
            pcntl_signal(SIGCHLD, [$this, '_signalHandler']);
        } else {
            $message .= 'Service is already running!';
            echo $message . PHP_EOL;
            $this->_redirectIO();
            $this->_log($message);
            return 0;
        }

        $this->_pid = pcntl_fork();
        if ($this->_pid == -1) {
            $message .= 'Could not start service!';
            echo $message . PHP_EOL;
            $this->_redirectIO();
            $this->_log($message);
            return 3;
        } elseif ($this->_pid) {
            file_put_contents($this->_pidFile, $this->_pid);
            return 0;
        }
        posix_setsid();

        $message .= 'OK.';
        echo $message . PHP_EOL;
        $this->_redirectIO();
        $this->_log($message);

        while (!$this->_stop) {
            foreach ($this->_workersData as $workerUid => $data) {
                if ($data['_tick'] % $data['delay'] === 0) {
                    $this->_workersData[$workerUid]['_tick'] = 0;
                    $pid = pcntl_fork();
                    if ($pid == -1) {
                        $this->_log('Could not launch worker "' . $workerUid . '"');
                    } elseif ($pid) {
                        $this->_workersData[$workerUid]['_pids'][] = $pid;
                    } else {
                        if (count($this->_workersData[$workerUid]['_pids']) >= $data['maxProcesses']) {
                            $this->_log('Max processes exceed for launch worker "' . $workerUid . '"');
                            return 0;
                        }
                        $result = $data['_worker']->run($data['params']);
                        if (is_array($result) && $result) {
                            $this->_workersData[$workerUid]['params'] = $result;
                        }
                        return 0;
                    }
                }
                $this->_workersData[$workerUid]['_tick']++;
            }
            sleep(1);
        }
        return 0;
    }

    public function actionStop()
    {
        $message = 'Stopping service... ';
        $result = 0;
        if ($this->_getPid() !== false) {
            $this->_killPid();
            $message .= 'OK.';
        } else {
            $message .= 'Service is not running!';
            $result = 3;
        }

        echo $message . PHP_EOL;
        $this->_redirectIO();
        $this->_log($message);
        return $result;
    }

    public function actionStatus()
    {
        if ($this->_getPid()) {
            echo 'Service status: running.' . PHP_EOL;
            return 0;
        }
        echo 'Service status: not running!' . PHP_EOL;
        return 3;
    }

}
