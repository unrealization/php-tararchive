<?php
declare(strict_types=1);
/**
 * @package PHPClassCollection
 * @subpackage TarArchive
 * @link http://php-classes.sourceforge.net/ PHP Class Collection
 * @author Dennis Wronka <reptiler@users.sourceforge.net>
 */
namespace unrealization;

use unrealization\TarArchive\ArchiveEntry;
/**
 * @package PHPClassCollection
 * @subpackage TarArchive
 * @link http://php-classes.sourceforge.net/ PHP Class Collection
 * @author Dennis Wronka <reptiler@users.sourceforge.net>
 * @version 0.99.0
 * @license http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html LGPL 2.1
 */
class TarArchive
{
	/**
	 * The filename of the archive
	 * @var string
	 */
	private ?string $fileName = null;
	/**
	 * The list of files in the archive
	 * @var ArchiveEntry[]
	 */
	private array $fileList = array();

	/**
	 * Constructor
	 * @param string $fileName
	 */
	public function __construct(?string $fileName = null)
	{
		if (!is_null($fileName))
		{
			$this->loadArchive($fileName);
		}
	}

	/**
	 * Load an archive file.
	 * @param string $fileName
	 * @return void
	 * @throws \Exception
	 */
	public function loadArchive(string $fileName): void
	{
		if (!file_exists($fileName))
		{
			throw new \Exception('File does not exist');
		}

		if (!is_readable($fileName))
		{
			throw new \Exception('Cannot read the file');
		}

		$file = @fopen($fileName, 'r');

		if ($file === false)
		{
			throw new \Exception('Cannot open the file for reading');
		}

		$this->fileList = array();

		while (!feof($file))
		{
			$data = fread($file, 512);

			if (mb_substr($data, 257, 5) === 'ustar')
			{
				$typeFlag = mb_substr($data, 156, 1);
				$offset = null;

				switch ($typeFlag)
				{
					case ArchiveEntry::TYPE_FILE:
						$offset = ftell($file);
						break;
					case ArchiveEntry::TYPE_LINK:
					case ArchiveEntry::TYPE_SYMLINK:
					case ArchiveEntry::TYPE_CHARDEV:
					case ArchiveEntry::TYPE_BLOCKDEV:
					case ArchiveEntry::TYPE_DIRECTORY:
					case ArchiveEntry::TYPE_FIFO:
						break;
					default:
						throw new \Exception('Unknown type flag '.$typeFlag);
						break;
				}

				$archiveEntry = ArchiveEntry::fromHeader($data);
				$archiveEntry->setOffset($offset);
				$this->fileList[] = $archiveEntry;
			}
		}

		fclose($file);
		$this->fileName = $fileName;
	}

	/**
	 * Get the list of files in the archive.
	 * @return array
	 */
	public function getFileList(): array
	{
		return $this->fileList;
	}

	/**
	 * Extract a file from the archive
	 * @param int $index
	 * @param bool $setPermissions
	 * @param bool $setOwner
	 * @return void
	 * @throws \Exception
	 */
	public function extract(int $index, string $targetDir = '.', bool $setPermissions = true, bool $setOwner = false): void
	{
		if (mb_substr($targetDir, -1) === '/')
		{
			$targetDir = mb_substr($targetDir, 0, -1);
		}

		$fileInfo = $this->fileList[$index];
		$targetName = $targetDir.'/'.$fileInfo->getName();
		$fileDir = dirname($targetName);

		if (file_exists($fileDir))
		{
			if (!is_dir($fileDir))
			{
				throw new \Exception($fileDir. ' is not a directory');
			}

			if (!is_writeable($fileDir))
			{
				throw new \Exception($fileDir. ' is not writeable');
			}
		}
		elseif (!mkdir($fileDir, 0777, true))
		{
			throw new \Exception('Cannot create '.$fileDir);
		}

		$data = $this->extractData($index);

		if (!is_null($data))
		{
			$file = @fopen($targetName, 'w');

			if ($file === false)
			{
				throw new \Exception('Cannot create file');
			}

			fwrite($file, $data);
			fclose($file);
		}
		else
		{
			$created = false;

			switch ($fileInfo->getType())
			{
				case ArchiveEntry::TYPE_LINK:
					$created = link($fileInfo->getLinkName(), $targetName);
					break;
				case ArchiveEntry::TYPE_SYMLINK:
					$created = symlink($fileInfo->getLinkName(), $targetName);
					break;
				case ArchiveEntry::TYPE_CHARDEV:
					$fileMode = POSIX_S_IFCHR | $fileInfo->getPermissions(true);
					$created = posix_mknod($targetName, $fileMode, $fileInfo->getDevMajor(), $fileInfo->getDevMinor());
					break;
				case ArchiveEntry::TYPE_BLOCKDEV:
					$fileMode = POSIX_S_IFBLK | $fileInfo->getPermissions(true);
					$created = posix_mknod($targetName, $fileMode, $fileInfo->getDevMajor(), $fileInfo->getDevMinor());
					//$created = posix_mknod($fileInfo->getName(), POSIX_S_IFBLK, $fileInfo->getDevMajor(), $fileInfo->getDevMinor());
					break;
				case ArchiveEntry::TYPE_DIRECTORY:
					$created = mkdir($targetName, $fileInfo->getPermissions(true));
					break;
				case ArchiveEntry::TYPE_FIFO:
					$created = posix_mkfifo($targetName, $fileInfo->getPermissions(true));
					break;
				default:
					throw new \Exception('Unknown type flag '.$fileInfo->getType());
					break;
			}

			if ($created === false)
			{
				throw new \Exception('Cannot create file');
			}
		}

		switch ($fileInfo->getType())
		{
			case ArchiveEntry::TYPE_FILE:
			case ArchiveEntry::TYPE_LINK:
			case ArchiveEntry::TYPE_SYMLINK:
				if ($setPermissions === true)
				{
					if (!chmod($targetName, $fileInfo->getPermissions(true)))
					{
						error_log('Failed to set '.$targetName.' to '.$fileInfo->getPermissions());
					}
				}
			case ArchiveEntry::TYPE_DIRECTORY:
				if ($setOwner === true)
				{
					chown($targetName, $fileInfo->getOwner()->getUserId());
					chgrp($targetName, $fileInfo->getOwner()->getGroupId());
				}
				break;
			default:
				break;
		}
	}

	/**
	 * Extract a file, return data instead of writing it to disk
	 * @param int $index
	 * @throws \OutOfBoundsException
	 * @throws \Exception
	 * @return string
	 */
	public function extractData(int $index): ?string
	{
		if (!isset($this->fileList[$index]))
		{
			throw new \OutOfBoundsException('Index '.$index.' does not exist');
		}

		$fileInfo = $this->fileList[$index];

		if ($fileInfo->getType() !== ArchiveEntry::TYPE_FILE)
		{
			return null;
		}

		if (!is_null($fileInfo->getOffset()))
		{
			$file = @fopen($this->fileName, 'r');

			if ($file === false)
			{
				throw new \Exception('Cannot open the file for reading');
			}

			fseek($file, $fileInfo->getOffset());
			$data = fread($file, $fileInfo->getSize());
			fclose($file);
		}
		else
		{
			$file = @fopen($fileInfo->getName(), 'r');

			if ($file === false)
			{
				throw new \Exception('Cannot open the file for reading');
			}

			$data = fread($file, $fileInfo->getSize());
			fclose($file);
		}

		return $data;
	}

	/**
	 * Save the archive to a file
	 * @param string $fileName
	 * @return void
	 * @throws \Exception
	 */
	public function saveArchive(?string $fileName = null): void
	{
		if ((is_null($this->fileName)) && (is_null($fileName)))
		{
			throw new \Exception('A filename is required to save a new archive');
		}

		if (is_null($fileName))
		{
			$fileName = $this->fileName;
		}

		$archiveData = $this->createArchive();

		$file = @fopen($fileName, 'w');
		fwrite($file, $archiveData);
		fclose($file);

		$this->fileName = $fileName;
	}

	/**
	 * Save the archive and return its contents
	 * @return string
	 */
	public function createArchive(): string
	{
		usort($this->fileList, array('self', 'sortFiles'));
		$archiveData = '';

		for ($index = 0; $index < count($this->fileList); $index++)
		{
			$archiveData .= $this->archiveFile($index, mb_strlen($archiveData));
		}

		return $archiveData;
	}

	/**
	 * Create an archive entry for a single file
	 * @param int $index
	 * @throws \OutOfBoundsException
	 * @return string
	 */
	private function archiveFile(int $index, int $offset = 0): string
	{
		if (!isset($this->fileList[$index]))
		{
			throw new \OutOfBoundsException('Index '.$index.' does not exist');
		}

		$fileData = '';

		if ($this->fileList[$index]->getType() === ArchiveEntry::TYPE_FILE)
		{
			$fileData = $this->extractData($index);
			$rest = mb_strlen($fileData) % 512;

			if ($rest != 0)
			{
				$missing = 512 - $rest;
				$fileData .= str_repeat(chr(0), $missing);
			}

			$this->fileList[$index]->setOffset($offset + 512);
		}

		if (is_null($this->fileList[$index]->getHeader()))
		{
			$this->fileList[$index]->setName(MbRegEx::replace('^\.?\/?(.+)$', '\1', $this->fileList[$index]->getName()));
		}

		$this->fileList[$index]->updateHeader();
		return $this->fileList[$index]->getHeader().$fileData;
	}

	/**
	 * Add a file to the archive
	 * @param string $fileName
	 * @return void
	 * @throws \Exception
	 */
	public function add(string $fileName, bool $recursive = true): void
	{
		if (mb_substr($fileName, -1) === '/')
		{
			$fileName = mb_substr($fileName, 0, -1);
		}

		if ($this->hasFile($fileName))
		{
			return;
		}

		$this->fileList[] = ArchiveEntry::fromFile($fileName);

		if ((filetype($fileName) === 'dir') && ($recursive === true))
		{
			$directory = opendir($fileName);

			while (($file = readdir($directory)) !== false)
			{
				if (($file === '.') || ($file === '..'))
				{
					continue;
				}

				$this->add($fileName.'/'.$file, true);
			}
		}
	}

	/**
	 * Remove a file from the archive
	 * @param int $index
	 * @return void
	 * @throws \OutOfBoundsException
	 */
	public function remove(int $index): void
	{
		if (!isset($this->fileList[$index]))
		{
			throw new \OutOfBoundsException('Index '.$index.' does not exist');
		}

		$tmpFileList = array();

		foreach ($this->fileList as $loopIndex => $fileInfo)
		{
			if ($loopIndex === $index)
			{
				continue;
			}

			$tmpFileList[] = $fileInfo;
		}

		$this->fileList = $tmpFileList;
	}

	public function hasFile(string $fileName): bool
	{
		foreach ($this->fileList as $fileInfo)
		{
			if ($fileInfo->getName() === $fileName)
			{
				return true;
			}
		}

		return false;
	}

	private static function sortFiles(ArchiveEntry $left, ArchiveEntry $right): int
	{
		if ($left > $right)
		{
			return 1;
		}
		elseif ($left < $right)
		{
			return -1;
		}

		return 0;
	}
}