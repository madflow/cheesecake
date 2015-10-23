<?php

namespace Cheesecake;

use Illuminate\Filesystem\Filesystem;

class CheesecakeException extends \Exception {};
class CheesecakeNotFoundExeption extends CheesecakeException {}
class CheesecakeFilesystemExeption extends CheesecakeException {}

class Generator
{
    private $template;
    private $params;
    private $output;

    public function __construct($template, array $params = [], $output)
    {
        $this->template = $template;
        $this->params = $params;
        $this->output = $output;
    }

    public function run()
    {
        $cakeJson = realpath($this->template).DIRECTORY_SEPARATOR.'cheesecake.json';

        if (!file_exists($cakeJson)) {
            throw new CheesecakeNotFoundExeption('Hi there! There seems to be no cheesecake.json file, ey!');
        }

        $args = json_decode(file_get_contents($cakeJson), true);
        $replace = [];
        foreach($args as $key => $value) {
            $replace['cheesecake.'.$key] = $value;
        }

        $fs = new Filesystem();

        $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid();
        if(!$fs->copyDirectory($this->template, $tmpDir)) {
            throw new CheesecakeFilesystemExeption('Meeeh!');
        }

        $iter = new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::CURRENT_AS_FILEINFO);

        $m = new \Mustache_Engine();
        foreach (new \RecursiveIteratorIterator($iter) as $filename => $cur) {
           $okay = $m->render($filename,$replace);
        }
    }
}
