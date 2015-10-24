<?php

namespace Cheesecake;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use GitElephant\Repository;
use Stringy\StaticStringy as Stringy;

class CheesecakeException extends \Exception{}
class CheesecakeNotFoundExeption extends CheesecakeException{}
class CheesecakeFilesystemExeption extends CheesecakeException{}
class CheesecakeUnknownTemplateException extends CheesecakeException{}

class Generator
{
    const TEMPLATE_TYPE_UNKNOWN = -1;
    const TEMPLATE_TYPE_DIR = 1;
    const TEMPLATE_TYPE_REMOTE_GIT = 2;
    const TEMPLATE_TYPE_LOCAL_GIT = 3;

    private $template;
    private $templateType;
    private $params;
    private $output;
    private $mustache;
    private $fs;

    public function __construct($template, array $params = [], $output = '.')
    {
        $this->template = $template;
        $this->templateType = $this->detectTemplateType($template);
        $this->params = $params;
        $this->output = $output;

        $this->mustache = new \Mustache_Engine();
        $this->mustache->addHelper('string', [
            'toLowerCase' => function ($value) { return Stringy::toLowerCase($value); },
            'toUpperCase' => function ($value) { return Stringy::toUpperCase($value); },
            'upperCaseFirst' => function ($value) { return Stringy::upperCaseFirst($value); },
            'lowerCaseFirst' => function ($value) { return Stringy::lowerCaseFirst($value); },
            'humanize' => function ($value) { return Stringy::humanize($value); },
            'camelize' => function ($value) { return Stringy::camelize($value); },
            'upperCamelize' => function ($value) { return Stringy::upperCamelize($value); },
        ]);
        $this->fs = new Filesystem();
    }

    private function detectTemplateType($template)
    {
        if(is_dir($template)) {
            if(is_dir($template.DIRECTORY_SEPARATOR.'.git')) {
                return self::TEMPLATE_TYPE_LOCAL_GIT;
            } else {
                return self::TEMPLATE_TYPE_DIR;
            }
        }

        if(Stringy::startsWith($template, 'https')
            OR Stringy::startsWith($template, 'git')) {
                return self::TEMPLATE_TYPE_REMOTE_GIT;
            }

        return self::TEMPLATE_TYPE_UNKNOWN;
    }

    public function run()
    {
        if($this->templateType === self::TEMPLATE_TYPE_DIR) {
            $cakeJson = realpath($this->template).DIRECTORY_SEPARATOR.'cheesecake.json';
        } else if($this->templateType === self::TEMPLATE_TYPE_REMOTE_GIT) {
            $repo = Repository::createFromRemote($this->template);
            $cakeJson = realpath($repo->getPath()).DIRECTORY_SEPARATOR.'cheesecake.json';
        } else if($this->templateType === self::TEMPLATE_TYPE_LOCAL_GIT) {
              $repo = Repository::open($this->template);
              $cakeJson = realpath($repo->getPath()).DIRECTORY_SEPARATOR.'cheesecake.json';
        } else {
            throw new CheesecakeUnknownTemplateException();
        }

        if (!file_exists($cakeJson)) {
            throw new CheesecakeNotFoundExeption();
        }
        $replace = [];
        if(is_file($cakeJson)) {
            $args = json_decode(file_get_contents($cakeJson), true);
            $replace = ['cheesecake' => $args];
        }

        $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid();
        if (!$this->fs->copyDirectory($this->template, $tmpDir)) {
            throw new CheesecakeFilesystemExeption();
        }

        $this->processDirs($tmpDir, $replace);
        $this->processFiles($tmpDir, $replace);

        if (!$this->fs->copyDirectory($tmpDir, $this->output)) {
            throw new CheesecakeFilesystemExeption();
        }

        if (!$this->fs->deleteDirectory($tmpDir)) {
            throw new CheesecakeFilesystemExeption();
        }

        return true;
    }

    protected function processDirs($tmpDir, $replace)
    {
        $finder = new Finder();
        $iterator = $finder
            ->directories()
            ->ignoreUnreadableDirs()
            ->sort(function(\SplFileInfo $a, \SplFileInfo $b) {
                return strlen($a->getRealpath()) > strlen($b->getRealpath());
            })
            ->in($tmpDir);

        foreach ($iterator as $dir) {
            $parts = explode(DIRECTORY_SEPARATOR, $dir->getRealpath());
            $renamed = $this->mustache->render($dir->getRealpath(), $replace);
            if ($renamed === $dir->getRealpath()) {
                continue;
            }
            if (!$this->fs->move($dir, $renamed)) {
                throw new CheesecakeFilesystemExeption();
            }
        }
    }

    protected function processFiles($tmpDir, $replace)
    {
        $finder = new Finder();
        $iterator = $finder
            ->files()
            ->size('<= 500M')
            ->ignoreDotFiles(false)
            ->in($tmpDir);

        foreach ($iterator as $file) {
            $rendered = $this->mustache->render($file->getContents(), $replace);
            file_put_contents($file->getRealpath(), $rendered);
        }
    }
}
