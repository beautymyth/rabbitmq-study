<?php

if (!defined('BM_COMMODULE_LOADER_ClASS')) {
    define('BM_COMMODULE_LOADER_ClASS', 1);

    /**
     * 自动加载类
     */
    class Loader {

        /**
         * 路径映射
         */
        public static $arrVendorMap = array(
            'Lib' => LIB_PATH
        );

        /**
         * 自动加载器
         */
        public static function autoLoad($strClassName) {
            $strFileName = self::findFile($strClassName);
            if (file_exists($strFileName)) {
                self::includeFile($strFileName);
            }
        }

        /**
         * 获取文件路径
         */
        private static function findFile($strClassName) {
            //顶级命名空间
            $strVendor = substr($strClassName, 0, strpos($strClassName, '\\'));
            //文件根目录
            $strVendorDir = self::$arrVendorMap[$strVendor];
            //文件相对路径
            $strFilePath = substr($strClassName, strlen($strVendor) + 1) . '.php';
            //文件路径
            return strtr($strVendorDir . $strFilePath, '\\', DIRECTORY_SEPARATOR);
        }

        /**
         * 加载文件
         */
        private static function includeFile($strFileName) {
            if (is_file($strFileName)) {
                include $strFileName;
            }
        }

    }

    /**
     * 注册自动加载
     */
    spl_autoload_register('Loader::autoLoad');
}
?>