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
use unrealization\TarArchive\Owner;
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
	private $fileName = null;
	/**
	 * The list of files in the archive
	 * @var ArchiveEntry[]
	 */
	private $fileList = array();

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
	 * @return void
	 * @throws \Exception
	 */
	public function extract(int $index): void
	{
		$data = $this->extractData($index);
		$fileInfo = $this->fileList[$index];
		$fileDir = dirname($fileInfo->getName());

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

		if (!is_null($data))
		{
			$file = @fopen($fileInfo->getName(), 'w');

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
					$created = link($fileInfo->getLinkName(), $fileInfo->getName());
					break;
				case ArchiveEntry::TYPE_SYMLINK:
					$created = symlink($fileInfo->getLinkName(), $fileInfo->getName());
					break;
				case ArchiveEntry::TYPE_CHARDEV:
					$fileMode = POSIX_S_IFCHR | (int)$fileInfo->getPermissions();
					$created = posix_mknod($fileInfo->getName(), $fileMode, $fileInfo->getDevMajor(), $fileInfo->getDevMinor());
					break;
				case ArchiveEntry::TYPE_BLOCKDEV:
					$fileMode = POSIX_S_IFBLK | (int)$fileInfo->getPermissions();
					$created = posix_mknod($fileInfo->getName(), $fileMode, $fileInfo->getDevMajor(), $fileInfo->getDevMinor());
					break;
				case ArchiveEntry::TYPE_DIRECTORY:
					$created = mkdir($fileInfo->getName(), (int)$fileInfo->getPermissions());
					break;
				case ArchiveEntry::TYPE_FIFO:
					$created = posix_mkfifo($fileInfo->getName(), (int)$fileInfo->getPermissions());
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

		$file = @fopen($this->fileName, 'r');

		if ($file === false)
		{
			throw new \Exception('Cannot open the file for reading');
		}

		fseek($file, $fileInfo->getOffset());
		$data = fread($file, $fileInfo->getSize());
		fclose($file);
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
			$saveFileName = $this->fileName;
		}
		else
		{
			$saveFileName = $fileName;
		}

		$tmpName = tempnam(sys_get_temp_dir(), 'tar_');
		$file = @fopen($tmpName, 'w');

		if ($file === false)
		{
			throw new \Exception('Cannot open the file for writing');
		}

		foreach ($this->fileList as $index => $fileInfo)
		{
			$archiveData = $this->archiveFile($index);

			if ($fileInfo->getType() === ArchiveEntry::TYPE_FILE)
			{
				$this->fileList[$index]->setOffset(ftell($file) + 512);
			}

			fwrite($file, $archiveData);
		}

		fclose($file);

		if (is_null(MbRegEx::match('^\.?\/.+$', $saveFileName)))
		{
			rename($tmpName, './'.$saveFileName);
		}
		else
		{
			rename($tmpName, $saveFileName);
		}

		$this->fileName = $saveFileName;
	}

	/**
	 * Save the archive and return its contents
	 * @return string
	 */
	public function createArchive(): string
	{
		$archiveData = '';

		foreach ($this->fileList as $index => $fileInfo)
		{
			if ($fileInfo->getType() === ArchiveEntry::TYPE_FILE)
			{
				$this->fileList[$index]->setOffset(mb_strlen($archiveData) + 512);
			}

			$archiveData .= $this->archiveFile($index);
		}

		return $archiveData;
	}

	/**
	 * Create an archive entry for a single file
	 * @param int $index
	 * @throws \OutOfBoundsException
	 * @return string
	 */
	private function archiveFile(int $index): string
	{
		if (!isset($this->fileList[$index]))
		{
			throw new \OutOfBoundsException('Index '.$index.' does not exist');
		}

		$archiveData = '';

		if (!is_null($archiveData = $this->fileList[$index]->getHeader()))
		{
			$archiveData = $this->fileList[$index]->getHeader();

			if ($this->fileList[$index]->getType() === ArchiveEntry::TYPE_FILE)
			{
				$fileData = $this->extractData($index);
				$rest = mb_strlen($fileData) % 512;

				if ($rest != 0)
				{
					$missing = 512 - $rest;
					$fileData .= str_repeat(chr(0), $missing);
				}

				$archiveData .= $fileData;
			}
		}
		else
		{
			$this->fileList[$index]->updateHeader();
			$archiveData = $this->fileList[$index]->getHeader();

			if ($this->fileList[$index]->getType() === ArchiveEntry::TYPE_FILE)
			{
				$file = @fopen($this->fileList[$index]->getName(), 'r');
				$fileData = fread($file, $this->fileList[$index]->getSize());
				fclose($file);
				$rest = mb_strlen($fileData) % 512;

				if ($rest != 0)
				{
					$missing = 512 - $rest;
					$fileData .= str_repeat(chr(0), $missing);
				}

				$archiveData .= $fileData;
			}

			$this->fileList[$index]->setName(MbRegEx::replace('^\.?\/?(.+)$', '\1', $this->fileList[$index]->getName()));
		}

		return $archiveData;
	}

	/**
	 * Add a file to the archive
	 * @param string $fileName
	 * @return void
	 * @throws \Exception
	 */
	public function add(string $fileName): void
	{
		if (!file_exists($fileName))
		{
			throw new \Exception('File does not exist');
		}

		/*if (!is_readable($fileName))
		{
			throw new \Exception('Cannot read the file');
		}*/

		$fileType = filetype($fileName);
		$typeFlag = null;

		switch ($fileType)
		{
			case 'block':
				$typeFlag = ArchiveEntry::TYPE_BLOCKDEV;
				break;
			case 'char':
				$typeFlag = ArchiveEntry::TYPE_CHARDEV;
				break;
			case 'dir':
				$typeFlag = ArchiveEntry::TYPE_DIRECTORY;
				break;
			case 'fifo':
				$typeFlag = ArchiveEntry::TYPE_FIFO;
				break;
			case 'file':
				$typeFlag = ArchiveEntry::TYPE_FILE;
				break;
			case 'link':
				$typeFlag = ArchiveEntry::TYPE_SYMLINK;
				break;
			case 'unknown':
			default:
				throw new \Exception('Unknown file type '.$fileType);
				break;
		}

		if (($typeFlag === ArchiveEntry::TYPE_DIRECTORY) || ($typeFlag === ArchiveEntry::TYPE_FILE) || ($typeFlag === ArchiveEntry::TYPE_LINK) || ($typeFlag === ArchiveEntry::TYPE_SYMLINK))
		{
			if (!is_readable($fileName))
			{
				throw new \Exception('Cannot read the file');
			}
		}

		$archiveEntry = new ArchiveEntry();
		$archiveEntry->setName($fileName);
		$archiveEntry->setType($typeFlag);
		$archiveEntry->setPermissions((int)decoct(fileperms($fileName)));

		try
		{
			$archiveEntry->setModificationDate(new \DateTime(date('Y-m-d H:i:s', filectime($fileName))));
		}
		catch (\Exception $e)
		{
			$archiveEntry->setModificationDate(new \DateTime());
		}

		$userId = fileowner($fileName);
		$userInfo = posix_getpwuid($userId);
		$groupId = filegroup($fileName);
		$groupInfo = posix_getgrgid($groupId);
		$owner = new Owner($userId, $userInfo['name'], $groupId, $groupInfo['name']);
		$archiveEntry->setOwner($owner);

		if ($typeFlag === ArchiveEntry::TYPE_FILE)
		{
			$archiveEntry->setSize(filesize($fileName));
		}

		if ($typeFlag == ArchiveEntry::TYPE_SYMLINK)
		{
			$archiveEntry->setLinkName(readlink($fileName));
		}

		if (($typeFlag == ArchiveEntry::TYPE_BLOCKDEV) || ($typeFlag == ArchiveEntry::TYPE_CHARDEV))
		{
			$fileStat = stat($fileName);
			$archiveEntry->setDevMajor(($fileStat['rdev'] >> 8) & 0xFF);
			$archiveEntry->setDevMinor($fileStat['rdev'] & 0xFF);
		}

		$this->fileList[] = $archiveEntry;
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
}