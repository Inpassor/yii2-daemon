The simple daemon extension for the Yii 2 framework
===================================================

Author: Inpassor <inpassor@yandex.com>

GitHub repository: https://github.com/Inpassor/yii2-daemon

This daemon is console application of Yii2, implementing multitasking
processes on PHP.
Once runned stays in memory and launches workers.
Every worker process have individual number of max processes running at once.
Inside the worker you can access any of Yii 2 resources. 

Please note that for normal daemon work php extensions **pcntl** and **posix**
are required. If running on Windows system, no forking available.
Also daemon main process stays in console until it break (Ctrl-C).

## Installation

1) Add package to your project using composer:
```
composer require inpassor/yii2-daemon
```

2) Add the daemon command to console config file in "controllerMap" section:
```
    'controllerMap' => [
        ...
        'daemon' => [
            'class' => 'inpassor\daemon\Controller',
            'uid' => 'daemon', // The daemon UID. Giving daemons different UIDs makes possible to run several daemons.
            'pidDir' => '@runtime/daemon', // PID file directory.
            'logsDir' => '@runtime/logs', // Log files directory.
            'clearLogs' => false, // Clear log files on start.
            'workersMap' => [
                'watcher' => [
                    'class' => 'inpassor\daemon\workers\Watcher',
                    'active' => true, // If set to false, worker is disabled.
                    'maxProcesses' => 1, // The number of maximum processes of the daemon worker running at once.
                    'delay' => 60, // The time, in seconds, the timer should delay in between executions of the daemon worker.
                ],
                ...
            ],
        ],
    ],
```

Parameter "class" is the only one that required. It is possible to set
"workersMap" section as array:
```
    'workersMap' => [
        'watcher' => 'inpassor\daemon\workers\Watcher',
        ...
    ],
```
In this case all the parameters will be taken from worker class.

Note that watchers config variables, defined in daemon's "workersMap" config section
have priority over the corresponding properties of worker class.

3) Create the daemon workers. All the workers classes should extend
inpassor\daemon\Worker :
```
class MyWorker extends inpassor\daemon\Worker
{
    public $active = true;
    public $maxProcesses = 1;
    public $delay = 60;

    public function run()
    {
        // The daemon worker's job goes here.
    }

}
```

## Run as system service for Ubuntu / Debian

1) Make sure that you have "yii" console application launcher under your
project directory. Check if "yii" file is executable.

2) Check if "vendor/inpassor/yii2-daemon/yiid" file is executable.

3) Run in root console:
```
ln -s /path_to_your_project/vendor/inpassor/yii2-daemon/yiid /etc/init.d/yiid
```

4) Create the file /lib/systemd/system/yiid.service :
```
[Unit]
Description=yiid
 
[Service]
User=www-data
PIDFile=/path_to_your_project/runtime/daemon/daemon.pid
Type=forking
KillMode=process
ExecStart=/path_to_your_project/vendor/inpassor/yii2-daemon/yiid start
ExecStop=/path_to_your_project/vendor/inpassor/yii2-daemon/yiid stop
 
[Install]
WantedBy=multi-user.target
```

5) Run in root console:
```
systemctl enable yiid.service
service yiid start
```
