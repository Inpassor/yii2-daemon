<?php
/**
 * The simple daemon extension for the Yii 2 framework
 *
 * @author Inpassor <inpassor@yandex.com>
 * @link https://github.com/Inpassor/yii2-daemon
 *
 * @version 0.2.4
 */

namespace inpassor\daemon;

use \yii\helpers\FileHelper;

class Controller extends \yii\console\Controller
{

    /**
     * @var string The daemon version.
     */
    public $version = '0.2.4';

    /**
     * @inheritdoc
     */
    public $defaultAction = 'start';

    /**
     * @var string The daemon UID. Giving daemons different UIDs makes possible to run several daemons.
     */
    public $uid = 'daemon';

    /**
     * @var array Workers config.
     */
    public $workersMap = [];

    /**
     * @var string PID file directory.
     */
    public $pidDir = '@runtime/daemon';

    /**
     * @var string Log files directory.
     */
    public $logsDir = '@runtime/logs';

    /**
     * @var bool Clear log files on start.
     */
    public $clearLogs = false;

    public static $workersPids = [];

    protected static $_stop = false;
    protected static $_workersConfig = [];
    protected static $_workersData = [];

    protected $_meetRequerements = false;
    protected $_pid = false;
    protected $_logFile = null;
    protected $_errorLogFile = null;
    protected $_pidFile = null;
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
            $this->_stdout = fopen($this->_logFile, 'a');
        }
        if (defined('STDERR') && is_resource(STDERR)) {
            ini_set('error_log', $this->_errorLogFile);
            fclose(STDERR);
            $this->_stderr = fopen($this->_errorLogFile, 'a');
        }
    }

    /**
     * Logs one or several messages into daemon log file.
     * @param array|string $messages
     */
    protected function _log($messages)
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
     * Gets the PID of the main process, false on fail.
     * @return bool|string
     */
    protected function _getPid()
    {
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
     * Gets all the daemon workers.
     */
    protected function _getWorkers()
    {
        foreach ($this->workersMap as $workerUid => $workerConfig) {
            if (is_string($workerConfig)) {
                $workerConfig = [
                    'class' => $workerConfig,
                ];
            }
            if (
                !isset($workerConfig['class'])
                || (isset($workerConfig['active']) && !$workerConfig['active'])
            ) {
                continue;
            }
            if (
                !isset($workerConfig['delay'])
                || !isset($workerConfig['maxProcesses'])
            ) {
                $worker = new $workerConfig['class']();
                if (!isset($workerConfig['delay'])) {
                    $workerConfig['delay'] = $worker->delay;
                }
                if (!isset($workerConfig['maxProcesses'])) {
                    $workerConfig['maxProcesses'] = $worker->maxProcesses;
                }
                if (!isset($workerConfig['active'])) {
                    $workerConfig['active'] = $worker->active;
                }
                unset($worker);
                if (!$workerConfig['active']) {
                    continue;
                }
            }
            self::$_workersData[$workerUid] = [
                'class' => $workerConfig['class'],
                'maxProcesses' => $workerConfig['maxProcesses'],
                'delay' => $workerConfig['delay'],
                'tick' => 1,
            ];
            unset($workerConfig['class']);
            self::$_workersConfig[$workerUid] = $workerConfig;
            self::$workersPids[$workerUid] = [];
        }
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->_meetRequerements = extension_loaded('pcntl') && extension_loaded('posix');
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $this->pidDir = \Yii::getAlias($this->pidDir);
        if (!file_exists($this->pidDir)) {
            FileHelper::createDirectory($this->pidDir, 0755, true);
        }
        $this->_pidFile = $this->pidDir . DIRECTORY_SEPARATOR . $this->uid . '.pid';
        $this->logsDir = \Yii::getAlias($this->logsDir);
        if (!file_exists($this->logsDir)) {
            FileHelper::createDirectory($this->logsDir, 0755, true);
        }
        $this->_logFile = $this->logsDir . DIRECTORY_SEPARATOR . $this->uid . '.log';
        $this->_errorLogFile = $this->logsDir . DIRECTORY_SEPARATOR . $this->uid . '_error.log';
        return true;
    }

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        return [
            'uid',
            'clearLogs',
        ];
    }

    /**
     * @inheritdoc
     */
    public function optionAliases()
    {
        return [
            'u' => 'uid',
            'c' => 'clearLogs',
        ];
    }

    /**
     * PNCTL signal handler.
     * @param $signo
     * @param $pid
     * @param $status
     */
    public static function signalHandler($signo, $pid = null, $status = null)
    {
        switch ($signo) {
            case SIGTERM:
            case SIGINT:
                self::$_stop = true;
                break;
            case SIGCHLD:
                if (!$pid) {
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }
                while ($pid > 0) {
                    foreach (self::$workersPids as $workerUid => $workerPids) {
                        if (($key = array_search($pid, $workerPids)) !== false) {
                            unset(self::$workersPids[$workerUid][$key]);
                        }
                    }
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }
                break;
        }
    }

    /**
     * The daemon boobs.
     * @return int
     */
    public function actionBoobs()
    {
        if ($boobs = FileHelper::findFiles(__DIR__ . DIRECTORY_SEPARATOR . 'boobs', ['only' => ['boobs*.bin']])) {
            $b = $boobs[mt_rand(0, count($boobs) - 1)];
            echo gzuncompress(file_get_contents($b));
        }
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * The daemon start command.
     * @return int
     */
    public function actionStart()
    {
        if ($this->clearLogs) {
            if (file_exists($this->_logFile)) {
                unlink($this->_logFile);
            }
            if (file_exists($this->_errorLogFile)) {
                unlink($this->_errorLogFile);
            }
        }

        $message = 'Starting Yii 2 Daemon v' . $this->version . '... ';

        if ($this->_getPid() === false) {
            $this->_getWorkers();
            if (!self::$_workersData) {
                $message .= 'No tasks found. Stopping!';
                echo $message . PHP_EOL;
                $this->_redirectIO();
                $this->_log($message);
                return self::EXIT_CODE_ERROR;
            }
            if ($this->_meetRequerements) {
                pcntl_signal(SIGTERM, ['inpassor\daemon\Controller', 'signalHandler']);
                pcntl_signal(SIGINT, ['inpassor\daemon\Controller', 'signalHandler']);
                pcntl_signal(SIGCHLD, ['inpassor\daemon\Controller', 'signalHandler']);
            }
        } else {
            $message .= 'Service is already running!';
            echo $message . PHP_EOL;
            $this->_redirectIO();
            $this->_log($message);
            return self::EXIT_CODE_NORMAL;
        }

        $this->_pid = $this->_meetRequerements ? pcntl_fork() : 0;
        if ($this->_pid == -1) {
            $message .= 'Could not start service!';
            echo $message . PHP_EOL;
            $this->_redirectIO();
            $this->_log($message);
            return self::EXIT_CODE_ERROR;
        } elseif ($this->_pid) {
            file_put_contents($this->_pidFile, $this->_pid);
            return self::EXIT_CODE_NORMAL;
        }
        if ($this->_meetRequerements) {
            posix_setsid();
        }

        $message .= 'OK.';
        echo $message . PHP_EOL;
        $this->_redirectIO();
        $this->_log($message);

        if ($this->_meetRequerements) {
            declare(ticks=1);
        };

        $previousSec = null;

        while (!self::$_stop) {
            $currentSec = date('s');
            $tickPlus = $currentSec === $previousSec ? 0 : 1;
            if ($tickPlus) {
                foreach (self::$_workersData as $workerUid => $workerData) {
                    if ($workerData['tick'] >= $workerData['delay']) {
                        self::$_workersData[$workerUid]['tick'] = 0;
                        $pid = 0;
                        if ($this->_meetRequerements) {
                            if (!isset(self::$workersPids[$workerUid])) {
                                self::$workersPids[$workerUid] = [];
                            }
                            $pid = (count(self::$workersPids[$workerUid]) < $workerData['maxProcesses']) ? pcntl_fork() : -2;
                        }
                        if ($pid == -1) {
                            $this->_log('Could not launch worker "' . $workerUid . '"');
                        } elseif ($pid == -2) {
                            $this->_log('Max processes exceed for launch worker "' . $workerUid . '"');
                        } elseif ($pid) {
                            self::$workersPids[$workerUid][] = $pid;
                        } else {
                            /** @var \inpassor\daemon\Worker $worker */
                            $worker = new $workerData['class'](array_merge(isset(self::$_workersConfig[$workerUid]) ? self::$_workersConfig[$workerUid] : [], [
                                'uid' => $workerUid,
                                'logFile' => $this->_logFile,
                                'errorLogFile' => $this->_errorLogFile,
                            ]));
                            $worker->run();
                            if ($this->_meetRequerements) {
                                return self::EXIT_CODE_NORMAL;
                            }
                        }
                    }
                    self::$_workersData[$workerUid]['tick'] += $tickPlus;
                }
            }
            usleep(500000);
            $previousSec = $currentSec;
        }
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * The daemon stop command.
     * @return int
     */
    public function actionStop()
    {
        $message = 'Stopping Yii 2 Daemon v' . $this->version . '... ';
        $result = self::EXIT_CODE_NORMAL;
        if ($this->_getPid() !== false) {
            $this->_killPid();
            $message .= 'OK.';
        } else {
            $message .= 'Service is not running!';
            $result = self::EXIT_CODE_ERROR;
        }
        echo $message . PHP_EOL;
        $this->_redirectIO();
        $this->_log($message);
        return $result;
    }

    /**
     * The daemon restart command.
     * @return int
     */
    public function actionRestart()
    {
        $result = $this->actionStop();
        if ($result !== self::EXIT_CODE_NORMAL) {
            return self::EXIT_CODE_ERROR;
        }
        return $this->actionStart();
    }

    /**
     * The daemon status command.
     * @return int
     */
    public function actionStatus()
    {
        if ($this->_getPid()) {
            echo 'Yii 2 Daemon v' . $this->version . ' status: running.' . PHP_EOL;
            return self::EXIT_CODE_NORMAL;
        }
        echo 'Yii 2 Daemon v' . $this->version . ' status: not running!' . PHP_EOL;
        return self::EXIT_CODE_ERROR;
    }

}
