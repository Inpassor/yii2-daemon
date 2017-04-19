# The simple daemon extension for the Yii 2 framework

[![Latest Stable Version](https://poser.pugx.org/inpassor/yii2-daemon/version)](https://packagist.org/packages/inpassor/yii2-daemon)
[![Total Downloads](https://poser.pugx.org/inpassor/yii2-daemon/downloads)](https://packagist.org/packages/inpassor/yii2-daemon)
[![License](https://poser.pugx.org/inpassor/yii2-daemon/license)](https://packagist.org/packages/inpassor/yii2-daemon)

Author: Inpassor <inpassor@yandex.com>

GitHub repository: https://github.com/Inpassor/yii2-daemon

This daemon is a console application of Yii2, implementing multitasking
processes on PHP.
When started, it stays in memory and launches workers.
Every worker process has an individual number of max processes running at once.
Inside the worker you can access any of Yii2 resources. 

Note that for the normal operation of the daemon you need the PHP extensions **pcntl**
and **posix**.
If the daemon is running on a Windows system, forking is not available.
Also, the main process of the daemon remains in the console until it is interrupted (Ctrl-C).

## Installation

1) Add package to your project using composer:
~~~
composer require inpassor/yii2-daemon
~~~

2) Add the daemon command to the console config file in the "controllerMap" section:
~~~
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
~~~

A workers of the daemon sould be listed in the "workersMap" section. Parameter "class"
is the only one that required. It is possible to set a worker config such way:
~~~
'workersMap' => [
    'watcher' => 'inpassor\daemon\workers\Watcher',
    ...
],
~~~
In this case all the parameters will be taken from a worker class.

Note that config variables of a worker defined in the "workersMap" config section
of the daemon have priority over the corresponding properties of a worker class.

The daemon contains the worker "inpassor\daemon\workers\Watcher".
This worker run once per minute and checks if workers of the daemon alive
and removes a dead ones from the memory. It is not required.

3) Create workers of the daemon. All the worker classes should extend
inpassor\daemon\Worker :
~~~
class MyWorker extends \inpassor\daemon\Worker
{
    public $active = true;
    public $maxProcesses = 1;
    public $delay = 60;

    public function run()
    {
        $this->log('I live... again!');
        // The daemon worker's job goes here.
    }

}
~~~

The public method run() of the worker should be overridden in the derivative class.
Don't forget to add your workers to the "workersMap" section of the config of the daemon.

## Run as system service for Ubuntu / Debian

1) Make sure that in your project directory there is the console application "yii".
Check if the "yii" file is executable.

2) Check if the "vendor/inpassor/yii2-daemon/yiid" file is executable.

3) Run in root console:
~~~
ln -s /path_to_your_project/vendor/inpassor/yii2-daemon/yiid /etc/init.d/yiid
~~~

4) Create the file /lib/systemd/system/yiid.service :
~~~
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
~~~

5) Run in a root console:
~~~
systemctl enable yiid.service
service yiid start
~~~
