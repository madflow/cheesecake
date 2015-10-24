<?php

namespace Cheesecake\Test;

use Cheesecake\Generator;
use Cheesecake\TestCase;
use Illuminate\Filesystem\Filesystem;

class GeneratorTest extends TestCase
{
    public function testInstance()
    {
        $template = __DIR__ .'/resources/minimal-cake';
        $o = new Generator($template);
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testInvalidInstance()
    {
        $o = new Generator();
    }

    public function testRunMinimal()
    {
        $template = __DIR__ .'/resources/minimal-cake';
        $output = $this->createTemporaryOutput();
        $o = new Generator($template, [], $output);
        $o->run();

        $this->assertMinimalCake($output);
        $this->clean($output);
    }

    public function testRunMinimalNoOutput()
    {
        $output = $this->createTemporaryOutput();
        mkdir($output);
        chdir($output);
        $template = __DIR__ .'/resources/minimal-cake';
        $o = new Generator($template, []);
        $o->run();

        $this->assertMinimalCake($output);
        $this->clean($output);
    }

    protected function assertMinimalCake($output)
    {
        $this->assertTrue(
            is_file($output.'/README.md')
        );
        $this->assertEquals('# Cheesecake',
            trim(file_get_contents($output.'/README.md'))
        );

        $this->assertTrue(
            is_dir($output.'/'.'cheesecake')
        );

        $this->assertTrue(
            is_file($output.'/cheesecake/.env')
        );

        $this->assertEquals(
            'cheesecake', trim(file_get_contents($output.'/cheesecake/.env'))
        );
    }

    public function testFilters()
    {
        $template = __DIR__ .'/resources/string-filters';
        $output = $this->createTemporaryOutput();
        $o = new Generator($template, [], $output);
        $o->run();

        $json = json_decode(file_get_contents($output.'/FILTERS.json'));
        $this->assertEquals(
            $json->toLowerCase, 'hello good sir!'
        );
        $this->assertEquals(
            $json->humanize, 'Hello Good Sir!'
        );
        $this->assertEquals(
            $json->camelize, 'helloGoodSir!'
        );
        $this->assertEquals(
            $json->upperCamelize, 'HelloGoodSir!'
        );
        $this->assertEquals(
            $json->lowerCaseFirst, 'hello Good Sir!'
        );
        $this->assertEquals(
            $json->upperCaseFirst, 'Hello Good Sir!'
        );
        $this->clean($output);
    }

    public function testRecursiceDirectories()
    {
        /**
        $template = __DIR__ .'/resources/recursive-directories';
        $output = sys_get_temp_dir().'/'.uniqid();
        $o = new Generator($template, [], $output);
        $o->run();

        $this->clean($output);
        */
    }

    protected function createTemporaryOutput()
    {
        return sys_get_temp_dir().'/'.sha1(uniqid());
    }

    protected function clean($output)
    {
        $fs = new Filesystem();
        $fs->deleteDirectory($output);
    }
}
