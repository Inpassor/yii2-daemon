The simple daemon extension for the Yii 2 framework
===================================================

Author: Inpassor <inpassor@yandex.com>
Link: https://github.com/Inpassor/yii2-daemon

This daemon is console application of Yii2.
Once runned stays in memory and launches workers.
Every worker process have individual number of processes running at once.

Please note that for normal daemon work php extensions pcntl and posix
are required. If running on Windows system no forking available.
Also daemon main process stays in console until it break (Ctrl-C).

### Install

1) Add package to your project using composer:
```
composer require inpassor/yii2-daemon
```

If package installation fails with message
```
[InvalidArgumentException]
Could not find package inpassor/yii2-daemon at any version for your minimum-stability (stable). Check the package spelling or your minimum-stability
```
add following parameters to your composer.json file (or change existant):
```
    "minimum-stability": "dev",
    "prefer-stable": true,
```

2) Add the daemon command to console config file in "controllerMap" section:
```
    'controllerMap' => [
        ...
        'daemon' => [
            'class' => 'inpassor\daemon\DaemonController',
        ],
    ],
```

3) Use directory in your application root named "@app/daemon"
for daemon workers classes.
Notice that the daemon takes all the classes over this directory that
names ends with "Worker.php" and have property "active" set to true.
Workers are loaded during daemon start. So if you add one more worker,
daemon should be restarted to run this worker.

4) Create the daemon workers. All the workers classes should extend
inpassor\daemon\DaemonWorker :
```
class MyWorker extends inpassor\daemon\DaemonWorker
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

### Run as system service for Ubuntu / Debian

1) Make sure that you have "yii" console application launcher under your
project directory. Check if "yii" file is executable.

2) Make the file "vendor/inpassor/yii2-daemon/yiid" executable.

3) Run in root console:
```
ln -s /path_to_your_project/vendor/inpassor/yii2-daemon/yiid /etc/init.d/yiid
```

4) Create the file /lib/systemd/system/yiid.service :
```
[Unit]
Description=yiid
 
[Service]
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
