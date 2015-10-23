<?php

namespace Cheesecake;

use Illuminate\Filesystem\Filesystem;

class CheesecakeException extends \Exception
{
};
class CheesecakeNotFoundExeption extends CheesecakeException
{
}
class CheesecakeFilesystemExeption extends CheesecakeException
{
}

class Generator
{
    private $template;
    private $params;
    private $output;
    private $mustache;
    private $fs;

    public function __construct($template, array $params = [], $output = '.')
    {
        $this->template = $template;
        $this->params = $params;
        $this->output = $output;
        $this->mustache = new \Mustache_Engine();
        $this->mustache->addHelper('case', [
            'lower' => function ($value) { return strtolower((string) $value); },
            'upper' => function ($value) { return strtoupper((string) $value); },
            'upperfirst' => function ($value) { return ucfirst((string) $value); },
        ]);
        $this->fs = new Filesystem();
    }

    public function run()
    {
        $cakeJson = realpath($this->template).DIRECTORY_SEPARATOR.'cheesecake.json';
        if (!file_exists($cakeJson)) {
            throw new CheesecakeNotFoundExeption('Hi there! There seems to be no cheesecake.json file, ey!');
        }
        $replace = [];
        if(is_file($cakeJson)) {
            $args = json_decode(file_get_contents($cakeJson), true);
            $replace = ['cheesecake' => $args];
        }

        $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid();
        if (!$this->fs->copyDirectory($this->template, $tmpDir)) {
            throw new CheesecakeFilesystemExeption('Meeeh!');
        }

        $this->processDirs($tmpDir, $replace);
        $this->processFiles($tmpDir, $replace);

        if (!$this->fs->copyDirectory($tmpDir, $this->output)) {
            throw new CheesecakeFilesystemExeption('Could not create output directory.');
        }

        if (!$this->fs->deleteDirectory($tmpDir)) {
            throw new CheesecakeFilesystemExeption('Could not delete temporary directory.');
        }

        return true;
    }

    protected function processDirs($tmpDir, $replace)
    {
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
            \RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        $dirs = [];
        array_multisort(array_map('strlen', $dirs), $dirs);
        foreach ($iter as $path => $dir) {
            if ($dir->isDir()) {
                $dirs[] = $path;
            }
        }

        while($path = array_pop($dirs)) {
            $renamed = $this->mustache->render($path, $replace);
            if ($renamed === $path) {
                continue;
            }
            if (!$this->fs->move($path, $renamed)) {
                throw new CheesecakeFilesystemExeption(
                    'Could not rename directory'
                );
            }
        }

    }

    protected function processFiles($tmpDir, $replace)
    {
        $iter = new \RecursiveDirectoryIterator($tmpDir);

        foreach (new \RecursiveIteratorIterator($iter) as $filename => $cur) {
            $info = pathinfo($filename);
            $baseName = $info['basename'];

            if ($baseName === 'cheesecake.json' or $baseName === 'cookiecutter.json') {
                continue;
            }

            if (is_file($filename)) {
                $contents = file_get_contents($filename);
                $rendered = $this->mustache->render($contents, $replace);
                file_put_contents($filename, $rendered);
            }
        }
    }
}
