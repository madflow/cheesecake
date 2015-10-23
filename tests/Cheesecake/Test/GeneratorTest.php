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
        $output = sys_get_temp_dir().'/'.uniqid();
        $o = new Generator($template, [], $output);
        $o->run();

        $this->assertMinimalCake($output);
        $this->clean($output);
    }

    public function testRunMinimalNoOutput()
    {
        $output = sys_get_temp_dir().'/'.uniqid();
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

        $this->assertTrue(is_dir($output.'/'.'cheesecake'));

        $this->assertTrue(
            is_file($output.'/cheesecake/.env')
        );

        $this->assertEquals(
            'cheesecake', trim(file_get_contents($output.'/cheesecake/.env'))
        );
    }

    protected function clean($output)
    {
        $fs = new Filesystem();
        $fs->deleteDirectory($output);
    }
}
