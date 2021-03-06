<?php

namespace Symfony\Bundle\MakerBundle\Tests\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MakerBundle\Util\AutoloaderUtil;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class AutoloaderUtilTest extends TestCase
{
    protected static $currentRootDir;

    /**
     * @beforeClass
     */
    public static function setupPaths()
    {
        $path = __DIR__.'/../tmp/current_project';

        $fs = new Filesystem();
        if (!file_exists($path)) {
            $fs->mkdir($path);
        }

        self::$currentRootDir = realpath($path);
    }

    public function testGetPathForFutureClass()
    {
        $composerJson = [
            'autoload' => [
                'psr-4' => [
                    'Also\\In\\Src\\' => 'src/SubDir',
                    'App\\' => 'src/',
                    'Other\\Namespace\\' => 'lib',
                    '' => 'fallback_dir',
                ],
                'psr-0' => [
                    'Psr0\\Package' => 'lib/other',
                ],
            ],
        ];

        $fs = new Filesystem();
        if (!file_exists(self::$currentRootDir)) {
            $fs->mkdir(self::$currentRootDir);
        }

        $fs->remove(self::$currentRootDir.'/vendor');
        file_put_contents(
            self::$currentRootDir.'/composer.json',
            json_encode($composerJson, JSON_PRETTY_PRINT)
        );
        $process = new Process('composer dump-autoload', self::$currentRootDir);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \Exception('Error running composer dump-autoload: '.$process->getErrorOutput());
        }


        $autoloaderUtil = new AutoloaderUtil(self::$currentRootDir);
        foreach ($this->getPathForFutureClassTests() as $className => $expectedPath) {
            $this->assertSame(
                // the paths will start in vendor/composer and be relative
                str_replace('\\', '/', self::$currentRootDir.'/vendor/composer/../../'.$expectedPath),
                // normalize slashes for Windows comparison
                str_replace('\\', '/', $autoloaderUtil->getPathForFutureClass($className)),
                sprintf('class "%s" should have been in path "%s"', $className, $expectedPath)
            );
        }
    }

    public function getPathForFutureClassTests()
    {
        return [
            'App\Foo' => 'src/Foo.php',
            'App\Entity\Product' => 'src/Entity/Product.php',
            'Totally\Weird' => 'fallback_dir/Totally/Weird.php',
            'Also\In\Src\Some\OtherClass' => 'src/SubDir/Some/OtherClass.php',
            'Other\Namespace\Admin\Foo' => 'lib/Admin/Foo.php',
            'Psr0\Package\Admin\Bar' => 'lib/other/Psr0/Package/Admin/Bar.php'
        ];
    }
}
