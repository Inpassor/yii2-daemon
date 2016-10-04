<?php

namespace inpassor\daemon;

use Yii;
use \yii\helpers\FileHelper;

class Installer
{

    public static function postCreate($event)
    {
        $params = $event->getComposer()->getPackage()->getExtra();
        if (isset($params[__METHOD__]) && is_array($params[__METHOD__])) {
            foreach ($params[__METHOD__] as $method => $args) {
                call_user_func_array([__CLASS__, $method], (array)$args);
            }
        }
    }

    /**
     * @param array $paths
     */
    public static function createPaths(array $paths)
    {
        foreach ($paths as $path) {
            $path = Yii::getAlias($path);
            echo "mkdir('$path')...";
            if (!file_exists($path)) {
                if (FileHelper::createDirectory($path, 0755, true)) {
                    echo "done.\n";
                } else {
                    echo "fail.\n";
                }
            } else {
                echo "path already exists.\n";
            }
        }
    }

    /**
     * Sets the correct permission for the files and directories listed in the extra section.
     * @param array $paths the paths (keys) and the corresponding permission octal strings (values)
     */
    public static function setPermission(array $paths)
    {
        foreach ($paths as $path => $permission) {
            echo "chmod('$path', $permission)...";
            if (is_dir($path) || is_file($path)) {
                try {
                    if (chmod($path, octdec($permission))) {
                        echo "done.\n";
                    };
                } catch (\Exception $e) {
                    echo $e->getMessage() . "\n";
                }
            } else {
                echo "file not found.\n";
            }
        }
    }

}
