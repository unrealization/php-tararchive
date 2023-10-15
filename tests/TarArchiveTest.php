<?php
use PHPUnit\Framework\TestCase;
use unrealization\TarArchive;
use unrealization\TarArchive\ArchiveEntry;

/**
 * TarArchive test case.
 * @covers unrealization\TarArchive
 * @uses unrealization\TarArchive\ArchiveEntry
 * @uses unrealization\TarArchive\Owner
 */
class TarArchiveTest extends TestCase
{
	public function testTarArchive()
	{
		$fileName = sys_get_temp_dir().'/TarArchiveTest_'.uniqid().'.tar';

		$tar = new TarArchive();
		$this->assertInstanceOf(TarArchive::class, $tar);
		$this->assertSame(0, count($tar->getFileList()));

		$tar->add(__FILE__);
		$this->assertSame(1, count($tar->getFileList()));
		$this->assertFileDoesNotExist($fileName);

		$tar->saveArchive($fileName);
		$this->assertFileExists($fileName);

		$this->assertSame(mb_strlen($tar->createArchive()), filesize($fileName));
		$this->assertSame(md5($tar->createArchive()), md5_file($fileName));

		$tar = new TarArchive($fileName);

		$this->assertSame(mb_substr(__FILE__, 1), $tar->getFileList()[0]->getName());
		$this->assertSame(filesize(__FILE__), $tar->getFileList()[0]->getSize());

		unlink($fileName);

		$tar = new TarArchive();
		$this->assertSame(0, count($tar->getFileList()));

		$tar->add(__DIR__);
		$this->assertSame(2, count($tar->getFileList()));
		$this->assertFileDoesNotExist($fileName);

		$tar->saveArchive($fileName);
		$this->assertFileExists($fileName);

		$fileList = $tar->getFileList();
		$dirName = sys_get_temp_dir().'/TarArchiveTest_'.uniqid();
		$this->assertFileDoesNotExist($dirName.'/'.mb_substr(__FILE__, 1));

		for ($x = 0; $x <count($fileList); $x++)
		{
			$this->assertFileDoesNotExist($dirName.'/'.$fileList[$x]->getName());

			$tar->extract($x, $dirName);
			$this->assertFileExists($dirName.'/'.$fileList[$x]->getName());

			if ($fileList[$x]->getName() === mb_substr(__FILE__, 1))
			{
				$this->assertSame(md5_file(__FILE__), md5_file($dirName.'/'.$fileList[$x]->getName()));
			}
		}

		$this->assertFileExists($dirName.'/'.mb_substr(__FILE__, 1));
		unlink($fileName);
	}
}
