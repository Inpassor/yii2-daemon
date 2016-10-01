The simple daemon extension for the Yii 2 framework
===================================================

This daemon is console application of Yii2.
Once runned stays in memory and launches workers.

### Install

1. Add package to your project using composer:
```
composer require inpassor/yii2-daemon
```

2. Add the daemon command to console config file in "controllerMap" section:
```
    'controllerMap' => [
        ...
        'daemon' => [
            'class' => 'inpassor\daemon\DaemonController',
        ],
    ],
```

3. Create directory in your application root named "@app/daemon/daemon".
Notice that the daemon takes all the classes over this directory that
names ends with "Worker.php" and have property "active" set to true.

4. Create the daemon workers. All the workers classes should extend
inpassor\daemon\DaemonWorker :
```
class MyWorker  extends inpassor\daemon\DaemonWorker
{
    public $active = true;
    public $maxProcesses = 1;
    public $delay = 60;
    public $params = [];

    public function run($params)
    {
        // The daemon worker's job goes here.
        return $params;
    }

}
```

### Run as system service for Ubuntu / Debian

1. Make sure that you have "yii" console application launcher under your
project directory. Check if "yii" file is executable.

2. Make the file "vendor/inpassor/yii2-daemon/yiid" executable.

3. Run in root console:
```
ln -s /path_to_your_project/vendor/inpassor/yii2-daemon/yiid /etc/init.d/yiid
```

4. Create the file /lib/systemd/system/yiid.service :
```
[Unit]
Description=yiid
 
[Service]
PIDFile=/path_to_your_project/runtime/daemon/daemon/daemon.pid
Type=forking
KillMode=process
ExecStart=/path_to_your_project/vendor/inpassor/yii2-daemon/yiid start
ExecStop=/path_to_your_project/vendor/inpassor/yii2-daemon/yiid stop
 
[Install]
WantedBy=multi-user.target
```

5. Run in root console:
```
systemctl enable yiid.service
service yiid start
```
