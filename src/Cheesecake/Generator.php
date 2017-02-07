<?php

namespace Cheesecake;

use Cheesecake\Exception\CheesecakeNotFoundExeption;
use Cheesecake\Exception\CheesecakeUnknownTemplateException;
use Cheesecake\Exception\CheesecakeFilesystemExeption;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use GitElephant\Repository;
use Stringy\StaticStringy as Stringy;
use cli;

class Generator
{
    const TEMPLATE_TYPE_UNKNOWN = -1;
    const TEMPLATE_TYPE_DIR = 1;
    const TEMPLATE_TYPE_REMOTE_GIT = 2;
    const TEMPLATE_TYPE_LOCAL_GIT = 3;

    const OPT_OUTPUT='OUTPUTDIR';
    const OPT_NO_INTERACTION='--no-interaction';

    private $template;
    private $templateType;
    private $params;
    private $output;
    private $noInteraction;

    private $mustache;
    private $fs;

    /**
     * Let's do this ...
     * @param $template string The path or url to a tasty cheesecake template
     * @param $params array Optional parameters that are merged with existing
     *                      params from a cheesecake.json file :O
     * @param $options array Options
     */
    public function __construct($template, array $params = [], array $options = [])
    {
        $this->template = $template;
        $this->templateType = $this->detectTemplateType($template);
        $this->params = $params;
        $this->output = $this->getoa($options, self::OPT_OUTPUT, '.');
        $this->noInteraction = $this->getoa(
            $options,
            self::OPT_NO_INTERACTION,
            false
        );

        $options = ['pragmas' => [\Mustache_Engine::PRAGMA_FILTERS]];
        $this->mustache = new \Mustache_Engine($options);
        $this->mustache->addHelper('string', [
            'toLowerCase' => function ($value) {
                return Stringy::toLowerCase($value);
            },
            'toUpperCase' => function ($value) {
                return Stringy::toUpperCase($value);
            },
            'upperCaseFirst' => function ($value) {
                return Stringy::upperCaseFirst($value);
            },
            'lowerCaseFirst' => function ($value) {
                return Stringy::lowerCaseFirst($value);
            },
            'humanize' => function ($value) {
                return Stringy::humanize($value);
            },
            'camelize' => function ($value) {
                return Stringy::camelize($value);
            },
            'upperCamelize' => function ($value) {
                return Stringy::upperCamelize($value);
            },
            'slugify' => function ($value) {
                return Stringy::slugify($value);
            },
        ]);
        $this->fs = new Filesystem();
    }

    private function getoa(array $options, $name, $default)
    {
        return (
            isset($options[$name]) || isset($options['--'.$name])
        ) ? $options[$name] : $default;
    }

    private function detectTemplateType($template)
    {
        if (is_dir($template)) {
            if (is_dir($this->join($template, '.git'))) {
                return self::TEMPLATE_TYPE_LOCAL_GIT;
            } else {
                return self::TEMPLATE_TYPE_DIR;
            }
        }

        if (Stringy::startsWith($template, 'https')
            || Stringy::startsWith($template, 'git')) {
                return self::TEMPLATE_TYPE_REMOTE_GIT;
        }

        return self::TEMPLATE_TYPE_UNKNOWN;
    }

    public function run()
    {
        $localTemplate = null;

        if ($this->templateType === self::TEMPLATE_TYPE_DIR) {
            $cakeJson = $this->join(realpath($this->template), 'cheesecake.json');
            $localTemplate = $this->template;
        } elseif ($this->templateType === self::TEMPLATE_TYPE_REMOTE_GIT) {
            $repo = Repository::createFromRemote($this->template);
            $cakeJson = $this->join(realpath($repo->getPath()), 'cheesecake.json');
            $localTemplate = $repo->getPath();
        } elseif ($this->templateType === self::TEMPLATE_TYPE_LOCAL_GIT) {
              $repo = Repository::open($this->template);
              $cakeJson = $this->join(realpath($repo->getPath()), 'cheesecake.json');
              $localTemplate = $repo->getPath();
        } else {
            throw new CheesecakeUnknownTemplateException();
        }

        if (!file_exists($cakeJson)) {
            throw new CheesecakeNotFoundExeption();
        }

        $replace = [];
        $args = json_decode(file_get_contents($cakeJson), true);

        // Hack for ie. twig templates
        // create dummy filters
        if (isset($args['filters_ignore'])) {
            $ignoreFilters = $args['filters_ignore'];
            if (is_array($ignoreFilters)) {
                foreach ($ignoreFilters as $filter) {
                    $this->mustache->addHelper($filter, function ($value) {
                        return $value;
                    });
                }
            }

            unset($args['filters_ignore']);
        }

        // Detect if we need the cli prompt
        $diff = array_diff(array_keys($args), array_keys($this->params));
        if (count($diff) > 0 && false === $this->noInteraction) {
            foreach ($args as $key => $value) { // :S
                $args[$key] = cli\prompt(
                    $key,
                    $value,
                    $marker = ' : '
                );
            }
        } else { // Merge constructor params with cheesecake.json
            $args = array_merge($args, $this->params);
        }
        $replace = ['cheesecake' => $args];

        $tmpDir = $this->join(sys_get_temp_dir(), sha1(uniqid()));
        try {
            $this->fs->copyDirectory($localTemplate, $tmpDir);
        } catch (\Exception $e) {
            throw new CheesecakeFilesystemExeption();
        }

        $this->processHook('pre_gen.php', $tmpDir);
        $this->processDirs($tmpDir, $replace);
        $this->processFiles($tmpDir, $replace);

        if (!$this->fs->delete($this->join($tmpDir, 'cheesecake.json'))) {
            throw new CheesecakeFilesystemExeption();
        }

        if (!$this->fs->copyDirectory($tmpDir, $this->output)) {
            throw new CheesecakeFilesystemExeption();
        }

        if (!$this->fs->deleteDirectory($tmpDir)) {
            throw new CheesecakeFilesystemExeption();
        }

        $this->processHook('post_gen.php', $this->output);

        $hookDir = $this->join($this->output, 'hooks');

        if (is_dir($hookDir)) {
            if (!$this->fs->deleteDirectory($hookDir)) {
                throw new CheesecakeFilesystemExeption();
            }
        }

        return true;
    }

    private function processDirs($tmpDir, $replace)
    {
        $finderDirs = new Finder();
        $dirIterator = $finderDirs
            ->directories()
            ->ignoreUnreadableDirs()
            ->sort(function (\SplFileInfo $a, \SplFileInfo $b) {
                return strlen($a->getRealpath()) > strlen($b->getRealpath());
            })
            ->in($tmpDir);
        $this->renameFilesDirs($dirIterator, $replace);

        $finderFiles = new Finder();
        $filesIterator = $finderFiles
            ->files()
            ->ignoreUnreadableDirs()
            ->sort(function (\SplFileInfo $a, \SplFileInfo $b) {
                return strlen($a->getRealpath()) > strlen($b->getRealpath());
            })
            ->in($tmpDir);
        $this->renameFilesDirs($filesIterator, $replace);
    }

    private function renameFilesDirs($iterator, $replace)
    {
        foreach ($iterator as $dir) {
            $parts = explode(DIRECTORY_SEPARATOR, $dir->getRealpath());
            while ($deepest = array_pop($parts)) {
                $renamed = $this->mustache->render($deepest, $replace);
                $leadingName = implode(DIRECTORY_SEPARATOR, $parts);
                $oldName = $this->join($leadingName, $deepest);
                $newName = $this->join($leadingName, $renamed);

                if ($oldName === $newName) {
                    continue;
                }

                if (!$this->fs->move($oldName, $newName)) {
                    throw new CheesecakeFilesystemExeption();
                }
            }
        }
    }

    private function processFiles($tmpDir, $replace)
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

    private function processHook($hook, $workingDir)
    {
        $hookDir = $this->join($workingDir, 'hooks');

        if (!is_dir($hookDir)) {
            return;
        }
        if (is_file($this->join($hookDir, $hook))) {
            chdir($workingDir);
            include $this->join($hookDir, $hook);
        }
    }

    /**
     * Join positional arguments with the DIRECTORY_SEPARATOR constant
     */
    private function join()
    {
        return implode(DIRECTORY_SEPARATOR, func_get_args());
    }
}
