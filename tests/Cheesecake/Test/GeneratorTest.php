<?php

namespace Cheesecake\Test;

use Cheesecake\Generator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class GeneratorTest extends TestCase
{
    public function testInstance()
    {
        $template = __DIR__ .'/resources/minimal-cake';
        $o = new Generator($template);
        $this->expectNotToPerformAssertions();
    }

    public function testRunMinimal()
    {
        $template = __DIR__ .'/resources/minimal-cake';
        $output = $this->createTemporaryOutput();
        $options = [
            Generator::OPT_OUTPUT => $output,
            Generator::OPT_NO_INTERACTION => true,
        ];
        $o = new Generator($template, [], $options);
        $o->run();

        $this->assertMinimalCake($output);
        $this->clean($output);
    }

    public function testRunConstructorParams()
    {
        $template = __DIR__ .'/resources/minimal-cake';
        $output = $this->createTemporaryOutput();
        $options = [
            Generator::OPT_OUTPUT => $output,
            Generator::OPT_NO_INTERACTION => true,
        ];
        $o = new Generator($template, ['app_name' => 'YO'], $options);
        $o->run();

        $this->assertEquals('# YO',
            trim(file_get_contents($output.'/README.md'))
        );

        $this->clean($output);
    }

    public function testRunMinimalNoOutput()
    {
        $output = $this->createTemporaryOutput();
        mkdir($output);
        chdir($output);
        $template = __DIR__ .'/resources/minimal-cake';
        $o = new Generator($template, [], [Generator::OPT_NO_INTERACTION => true]);
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

        $this->assertDirectoryExists($output . '/' . 'cheesecake');

        $this->assertTrue(
            is_file($output.'/cheesecake/.env')
        );

        $this->assertTrue(
            is_file($output.'/cheesecake/cheesecake.ini')
        );

        $this->assertTrue(
            is_file($output.'/cheesecake/config/cheesecake.yml')
        );

        $this->assertNotTrue(
            is_file($output . '/' . 'cheesecake.json')
        );

        $this->assertEquals(
            'cheesecake', trim(file_get_contents($output.'/cheesecake/.env'))
        );
    }

    public function testFilters()
    {
        $template = __DIR__ .'/resources/string-filters';
        $output = $this->createTemporaryOutput();
        $options = [
            Generator::OPT_OUTPUT => $output,
            Generator::OPT_NO_INTERACTION => true,
        ];
        $o = new Generator($template, [], $options);
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
        $this->assertEquals(
            $json->slugify, 'hello-good-sir'
        );

        $this->clean($output);
    }

    public function testRecursiveDirectories()
    {
        $template = __DIR__ .'/resources/recursive-directories';
        $output = sys_get_temp_dir().'/'.uniqid();
        $options = [
            Generator::OPT_OUTPUT => $output,
            Generator::OPT_NO_INTERACTION => true,
        ];
        $o = new Generator($template, [], $options);
        $o->run();

        $this->clean($output);
        $this->expectNotToPerformAssertions();
    }

    public function testHooks()
    {
        $template = __DIR__ .'/resources/hooks';
        $output = sys_get_temp_dir().'/'.uniqid();
        $options = [
            Generator::OPT_OUTPUT => $output,
            Generator::OPT_NO_INTERACTION => true,
        ];
        $o = new Generator($template, [], $options);
        $o->run();

        $this->assertDirectoryExists($output . '/hello');
        $this->assertTrue(
            is_file($output.'/README.md')
        );
        $this->assertEquals('# Hello',
            trim(file_get_contents($output.'/README.md'))
        );

        $this->assertDirectoryNotExists($output . '/hooks');

        $this->clean($output);
    }

    public function testRunOnTwig()
    {
        $output = $this->createTemporaryOutput();
        mkdir($output);
        chdir($output);
        $template = __DIR__ .'/resources/twig';
        $o = new Generator($template, [], [Generator::OPT_NO_INTERACTION => true]);
        $o->run();

        $renderedTpl = \file_get_contents($output . '/test.html.twig');
        
        $this->assertContains('<script src="{{asset(\'/dist/hello.min.js\') }}></script>', $renderedTpl );

        $this->clean($output);
    }

    protected function createTemporaryOutput()
    {
        return sys_get_temp_dir().'/'.sha1(uniqid());
    }

    protected function clean($output)
    {
        $fs = new Filesystem();
        $fs->remove($output);
    }
}
