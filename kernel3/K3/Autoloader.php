<?php

class K3_Autoloader
{
    protected $folders = array(F_KERNEL_DIR);
    protected $classNameReplacePattern = array(
        '_'  => DIRECTORY_SEPARATOR,
        '\\' => DIRECTORY_SEPARATOR,
    );
    protected $includeFileSuffix = '.php';
    protected $fixedClassFiles = array();

    public function __construct()
    {
        spl_autoload_register(array($this, 'autoload'));
    }

    public function registerClassFile($className, $fileName)
    {
        $this->fixedClassFiles[$className] = (string) $fileName;
    }

    public function registerClassPath($dirPath)
    {
        if (!in_array($dirPath, $this->folders)) {
            $this->folders[] = $dirPath;
        }
    }

    protected function autoload($className)
    {
        $fileName = isset($this->fixedClassFiles[$className])
            ? $this->fixedClassFiles[$className]
            : strtr($className, $this->classNameReplacePattern).$this->includeFileSuffix;

        foreach ($this->folders as $folder) {
            $fullFile = $folder.DIRECTORY_SEPARATOR.$fileName;
            if (is_file($fullFile)) {
                include_once($fullFile);
                break;
            }
        }

        if (!class_exists($className) && !interface_exists($className)) {
            throw new FException('Error Loading class: '.$className);
        }
    }
}
