<?php
use \Wave\Filesystem\Local;

class LocalTest extends PHPUnit_Framework_TestCase
{

    private $filesystem = null;

    protected function setUp()
    {
        $workspace = getcwd() . '/tmp';
        
        if (! file_exists($workspace)) {
            if (true !== mkdir($workspace, 0755)) {
                $this->markTestIncomplete('Unable to create directory \'/tmp\'');
            }
        }
        
        $this->filesystem = new Local($workspace);
    }

    public function testCreateFileDir()
    {
        $fs = $this->filesystem;
        
        $file = $fs->create('/leaf1', 0755);
        
        $this->assertNotSame($file, NULL);
        $this->assertInstanceOf('\SplFileObject', $file);
        $this->assertTrue(is_file($fs->getPath() . '/leaf1'));
        unlink($fs->getPath() . '/leaf1');
        
        $dir = $fs->create('/branch1', 0755, 'dir');
        $this->assertNotSame($dir, NULL);
        $this->assertInstanceOf('\Wave\Filesystem\Local', $dir);
        $this->assertTrue(is_dir($fs->getPath() . '/branch1'));
        rmdir($fs->getPath() . '/branch1');
    }

    public function testOpenFileDir()
    {
        $fs = $this->filesystem;
        
        $fs->create('/readme', 0755);
        $this->assertInstanceOf('\SplFileObject', $fs->open('/readme', 'r'));
        unlink($fs->getPath() . '/readme');
        
        $fs->create('/openme', 0755, 'dir');
        $this->assertInstanceOf('\Wave\Filesystem\Local', $fs->open('/openme'));
        rmdir($fs->getPath() . '/openme');
    }

    public function testFileWrites()
    {
        $fs = $this->filesystem;
        $fs->create('/writeme', 0755);
        $fs->write('/writeme', "Hello\n\rWorld");
        unlink($fs->getPath() . '/writeme');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionWriteDir()
    {
        $fs = $this->filesystem;
        $fs->create('/dir', 0755, 'dir');
        try {
            $fs->write('/dir', "This string should fail");
        } catch (\InvalidArgumentException $e) {
            rmdir($fs->getPath() . '/dir');
            throw new \InvalidArgumentException($e->getMessage());
        }
    }

    public function testAllReads()
    {
        $fs = $this->filesystem;
        
        $fs->create('/readme', 0755);
        $fs->write('/readme', "Hello\n\rWorld");
        
        $expected = array(
            'Hello',
            'World'
        );
        
        $i = 0;
        while ($line = $fs->readln('/readme', $i)) {
            $this->assertEquals($expected[$i], $line);
            $i ++;
        }
        
        $this->assertSame(implode("\n\r", $expected), $fs->read('/readme'));
        $fs->create('/public', 0755, 'dir');
        $this->assertFalse($fs->read('/public'));
        rmdir($fs->getPath() . '/public');
        unlink($fs->getPath() . '/readme');
    }

    public function testFilePermissions()
    {
        $fp = $this->filesystem;
        
        $fp->create('/permissions', 0777);
        $this->assertEquals(substr(sprintf('%o', fileperms($fp->getPath() . '/permissions')), - 4), $fp->permissions('/permissions'));
        
        $this->assertSame($fp->permissions('/permissions', 0755), $fp);
        $this->assertEquals(substr(sprintf('%o', fileperms($fp->getPath() . '/permissions')), - 4), $fp->permissions('/permissions'));
        
        unlink($fp->getPath() . '/permissions');
    }

    public function testGetIterator()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('HHVM fails the test, because difference in resource number');
        }
        $this->assertEquals($this->filesystem->getDirectoryIterator(), new \DirectoryIterator($this->filesystem->getPath()));
    }
}
