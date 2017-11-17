<?php
declare(strict_types=1);
/**
 * @package PHPClassCollection
 * @subpackage TarArchive
 * @link http://php-classes.sourceforge.net/ PHP Class Collection
 * @author Dennis Wronka <reptiler@users.sourceforge.net>
 */
namespace unrealization\PHPClassCollection;
/**
 * @package PHPClassCollection
 * @subpackage TarArchive
 * @link http://php-classes.sourceforge.net/ PHP Class Collection
 * @author Dennis Wronka <reptiler@users.sourceforge.net>
 * @version 0.9.1
 * @license http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html LGPL 2.1
 */
class TarArchive
{
	/**
	 * Type flag for files
	 * @var int
	 */
	const TYPEFLAG_FILE			= 0;
	/**
	 * Type flag for hard links
	 * @var int
	 */
	const TYPEFLAG_LINK			= 1;
	/**
	 * Type flag for symbolic links
	 * @var int
	 */
	const TYPEFLAG_SYMLINK		= 2;
	/**
	 * Type flag for character devices
	 * @var int
	 */
	const TYPEFLAG_CHARDEV		= 3;
	/**
	 * Type flag for block devices
	 * @var integer
	 */
	const TYPEFLAG_BLOCKDEV		= 4;
	/**
	 * Type flag for directories
	 * @var integer
	 */
	const TYPEFLAG_DIRECTORY	= 5;
	/**
	 * Type flag for fifos
	 * @var integer
	 */
	const TYPEFLAG_FIFO			= 6;
	/**
	 * Data structure for archive entries
	 * @var array
	 */
	const FILE_STRUCTURE		= array(
			'header'		=> null,
			'name'			=> null,
			'type'			=> null,
			'permissions'	=> null,
			'modified'		=> null,
			'size'			=> null,
			'offset'		=> null,
			'user'			=> array(
					'id'	=> null,
					'name'	=> null
			),
			'group'			=> array(
					'id'	=> null,
					'name'	=> null
			),
			'linkName'		=> null,
			'devMajor'		=> null,
			'devMinor'		=> null,
			'prefix'		=> null
	);

	/**
	 * The filename of the archive
	 * @var string
	 */
	private $fileName = null;
	/**
	 * The list of files in the archive
	 * @var array
	 */
	private $fileList = array();

	/**
	 * Constructor
	 * @param string $fileName
	 */
	public function __construct(string $fileName = null)
	{
		if (!is_null($fileName))
		{
			$this->loadArchive($fileName);
		}
	}

	/**
	 * Load the archive file
	 * @param string $fileName
	 * @throws \Exception
	 */
	public function loadArchive(string $fileName)
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

			if (substr($data, 257, 5) == 'ustar')
			{
				$typeFlag = substr($data, 156, 1);
				$offset = null;

				switch ($typeFlag)
				{
					case self::TYPEFLAG_FILE:
						$offset = ftell($file);
						break;
					case self::TYPEFLAG_LINK:
					case self::TYPEFLAG_SYMLINK:
					case self::TYPEFLAG_CHARDEV:
					case self::TYPEFLAG_BLOCKDEV:
					case self::TYPEFLAG_DIRECTORY:
					case self::TYPEFLAG_FIFO:
						break;
					default:
						throw new \Exception('Unknown type flag '.$typeFlag);
						break;
				}

				$fileInfo = self::FILE_STRUCTURE;
				$fileInfo['header'] = $data;
				$fileInfo['name'] = trim(substr($data, 0, 100));
				$fileInfo['type'] = $typeFlag;
				$fileInfo['permissions'] = octdec(trim(substr($data, 100, 8)));

				try
				{
					$fileInfo['modified'] = new \DateTime(date('Y-m-d H:i:s', octdec(trim(substr($data, 136, 12)))));
				}
				catch (\Exception $e)
				{
					echo PHP_EOL.'Fnu!!!'.PHP_EOL.PHP_EOL;
					$fileInfo['modified'] = new \DateTime();
				}

				$fileInfo['size'] = octdec(substr($data, 124, 12));
				$fileInfo['offset'] = $offset;
				$fileInfo['user']['id'] = octdec(substr($data, 108, 8));
				$fileInfo['user']['name'] = trim(substr($data, 265, 32));
				$fileInfo['group']['id'] = octdec(substr($data, 116, 8));
				$fileInfo['group']['name'] = trim(substr($data, 297, 32));
				$fileInfo['linkName'] = trim(substr($data, 157, 100));
				$fileInfo['devMajor'] = octdec(substr($data, 329, 8));
				$fileInfo['devMinor'] = octdec(substr($data, 337, 8));
				$fileInfo['prefix'] = trim(substr($data, 345, 155));
				$this->fileList[] = $fileInfo;
			}
		}

		fclose($file);
		$this->fileName = $fileName;
	}

	/**
	 * Get the list of files in the archive
	 * @return array
	 */
	public function getFileList(): array
	{
		return $this->fileList;
	}

	/**
	 * Extract a file from the archive
	 * @param int $index
	 * @throws \Exception
	 */
	public function extract(int $index)
	{
		$data = null;

		try
		{
			$data = $this->extractData($index);
		}
		catch (\UnexpectedValueException $e)
		{
		}
		catch (\Exception $e)
		{
			throw $e;
		}

		$fileInfo = $this->fileList[$index];
		$fileDir = dirname($fileInfo['name']);

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
			$file = @fopen($fileInfo['name'], 'w');

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

			switch ($fileInfo['type'])
			{
				case self::TYPEFLAG_LINK:
					$created = link($fileInfo['linkName'], $fileInfo['name']);
					break;
				case self::TYPEFLAG_SYMLINK:
					$created = symlink($fileInfo['linkName'], $fileInfo['name']);
					break;
				case self::TYPEFLAG_CHARDEV:
					$fileMode = POSIX_S_IFCHR | (int)$fileInfo['permissions'];
					$created = posix_mknod($fileInfo['name'], $fileMode, $fileInfo['devMajor'], $fileInfo['devMinor']);
					break;
				case self::TYPEFLAG_BLOCKDEV:
					$fileMode = POSIX_S_IFBLK | (int)$fileInfo['permissions'];
					$created = posix_mknod($fileInfo['name'], $fileMode, $fileInfo['devMajor'], $fileInfo['devMinor']);
					break;
				case self::TYPEFLAG_DIRECTORY:
					$created = mkdir($fileInfo['name'], (int)$fileInfo['permissions']);
					break;
				case self::TYPEFLAG_FIFO:
					$created = posix_mkfifo($fileInfo['name'], (int)$fileInfo['permissions']);
					break;
				default:
					throw new \Exception('Unknown type flag '.$fileInfo['type']);
					break;
			}

			if ($created == false)
			{
				throw new \Exception('Cannot create file');
			}
		}
	}

	/**
	 * Extract a file, return data instead of writing it to disk
	 * @param int $index
	 * @throws \OutOfBoundsException
	 * @throws \UnexpectedValueException
	 * @throws \Exception
	 * @return string
	 */
	public function extractData(int $index): string
	{
		if (!isset($this->fileList[$index]))
		{
			throw new \OutOfBoundsException('Index '.$index.' does not exist');
		}

		$fileInfo = $this->fileList[$index];

		if ($fileInfo['type'] != self::TYPEFLAG_FILE)
		{
			throw new \UnexpectedValueException('Not a regular file');
		}

		$file = @fopen($this->fileName, 'r');

		if ($file === false)
		{
			throw new \Exception('Cannot open the file for reading');
		}

		fseek($file, $fileInfo['offset']);
		$data = fread($file, $fileInfo['size']);
		fclose($file);

		return $data;
	}

	/**
	 * Save the archive to a file
	 * @param string $fileName
	 * @throws \Exception
	 */
	public function saveArchive(string $fileName = null)
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

			if ($fileInfo['type'] == self::TYPEFLAG_FILE)
			{
				$this->fileList[$index]['offset'] = ftell($file) + 512;
			}

			fwrite($file, $archiveData);
		}

		fclose($file);

		if (!preg_match('@^\.?\/.+$@', $saveFileName))
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
			if ($fileInfo['type'] == self::TYPEFLAG_FILE)
			{
				$this->fileList[$index]['offset'] = strlen($archiveData) + 512;
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

		if (!is_null($this->fileList[$index]['header']))
		{
			$archiveData = $this->fileList[$index]['header'];

			if ($this->fileList[$index]['type'] == self::TYPEFLAG_FILE)
			{
				$fileData = $this->extractData($index);
				$rest = strlen($fileData) % 512;

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
			$tmpData = $this->fileList[$index];
			$tmpData['name'] = str_pad(preg_replace('@^\.?\/?(.+)$@', '\1', $tmpData['name']), 100, chr(0), STR_PAD_RIGHT);
			$tmpData['permissions'] = str_pad(decoct($tmpData['permissions']).chr(0), 8, '0', STR_PAD_LEFT);
			$tmpData['modified'] = str_pad(decoct($tmpData['modified']->format('U')).chr(0), 12, '0', STR_PAD_LEFT);
			$tmpData['size'] = str_pad(decoct($tmpData['size']).chr(0), 12, '0', STR_PAD_LEFT);
			$tmpData['user']['id'] = str_pad(decoct($tmpData['user']['id']).chr(0), 8, '0', STR_PAD_LEFT);
			$tmpData['user']['name'] = str_pad($tmpData['user']['name'], 32, chr(0), STR_PAD_RIGHT);
			$tmpData['group']['id'] = str_pad(decoct($tmpData['group']['id']).chr(0), 8, '0', STR_PAD_LEFT);
			$tmpData['group']['name'] = str_pad($tmpData['group']['name'], 32, chr(0), STR_PAD_RIGHT);
			$tmpData['linkName'] = str_pad($tmpData['linkName'], 100, chr(0), STR_PAD_RIGHT);
			$tmpData['devMajor'] =str_pad(decoct($tmpData['devMajor']), 8, chr(0), STR_PAD_RIGHT);
			$tmpData['devMinor'] = str_pad(decoct($tmpData['devMinor']), 8, chr(0), STR_PAD_RIGHT);
			//TODO
			$tmpData['prefix'] = str_repeat(chr(0), 155);

			$ustar = 'ustar  '.chr(0);
			$checkSum = str_repeat(' ', 8);
			$header = $tmpData['name'].$tmpData['permissions'].$tmpData['user']['id'].$tmpData['group']['id'].$tmpData['size'].$tmpData['modified'].$checkSum.$tmpData['type'].$tmpData['linkName'].$ustar.$tmpData['user']['name'].$tmpData['group']['name'].$tmpData['devMajor'].$tmpData['devMinor'].$tmpData['prefix'];
			$checkSum = 0;

			for ($pos = 0; $pos < strlen($header); $pos++)
			{
				$checkSum += ord(substr($header, $pos, 1));
			}

			$checkSum = str_pad(decoct($checkSum).chr(0).' ', 8, '0', STR_PAD_LEFT);
			$header = str_pad($tmpData['name'].$tmpData['permissions'].$tmpData['user']['id'].$tmpData['group']['id'].$tmpData['size'].$tmpData['modified'].$checkSum.$tmpData['type'].$tmpData['linkName'].$ustar.$tmpData['user']['name'].$tmpData['group']['name'].$tmpData['devMajor'].$tmpData['devMinor'].$tmpData['prefix'], 512, chr(0), STR_PAD_RIGHT);
			$tmpData['header'] = $header;

			//Data
			$archiveData = $tmpData['header'];

			if ($this->fileList[$index]['type'] == self::TYPEFLAG_FILE)
			{
				$file = @fopen($this->fileList[$index]['name'], 'r');
				$fileData = fread($file, $this->fileList[$index]['size']);
				fclose($file);
				$rest = strlen($fileData) % 512;

				if ($rest != 0)
				{
					$missing = 512 - $rest;
					$fileData .= str_repeat(chr(0), $missing);
				}

				$archiveData .= $fileData;
			}

			$this->fileList[$index]['header'] = $tmpData['header'];
			$this->fileList[$index]['name'] = preg_replace('@^\.?\/?(.+)$@', '\1', $this->fileList[$index]['name']);
		}

		return $archiveData;
	}

	/**
	 * Add a file to the archive
	 * @param string $fileName
	 * @throws \Exception
	 */
	public function add(string $fileName)
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
				$typeFlag = self::TYPEFLAG_BLOCKDEV;
				break;
			case 'char':
				$typeFlag = self::TYPEFLAG_CHARDEV;
				break;
			case 'dir':
				$typeFlag = self::TYPEFLAG_DIRECTORY;
				break;
			case 'fifo':
				$typeFlag = self::TYPEFLAG_FIFO;
				break;
			case 'file':
				$typeFlag = self::TYPEFLAG_FILE;
				break;
			case 'link':
				$typeFlag = self::TYPEFLAG_SYMLINK;
				break;
			case 'unknown':
			default:
				throw new \Exception('Unknown file typek '.$fileType);
				break;
		}

		if (($typeFlag == self::TYPEFLAG_DIRECTORY) || ($typeFlag == self::TYPEFLAG_FILE) || ($typeFlag == self::TYPEFLAG_LINK) || ($typeFlag == self::TYPEFLAG_SYMLINK))
		{
			if (!is_readable($fileName))
			{
				throw new \Exception('Cannot read the file');
			}
		}

		$userId = fileowner($fileName);
		$userInfo = posix_getpwuid($userId);
		$groupId = filegroup($fileName);
		$groupInfo = posix_getgrgid($groupId);
		$fileStat = stat($fileName);

		$fileInfo = self::FILE_STRUCTURE;

		$fileInfo['name'] = $fileName;
		$fileInfo['type'] = $typeFlag;
		$fileInfo['permissions'] = decoct(fileperms($fileName));

		try
		{
			$fileInfo['modified'] = new \DateTime(date('Y-m-d H:i:s', filectime($fileName)));
		}
		catch (\Exception $e)
		{
			$fileInfo['modified'] = new \DateTime();
		}

		if ($typeFlag == self::TYPEFLAG_FILE)
		{
			$fileInfo['size'] = filesize($fileName);
		}
		else
		{
			$fileInfo['size'] = 0;
		}

		$fileInfo['user']['id'] = $userId;
		$fileInfo['user']['name'] = $userInfo['name'];
		$fileInfo['group']['id'] = $groupId;
		$fileInfo['group']['name'] = $groupInfo['name'];

		if ($typeFlag == self::TYPEFLAG_SYMLINK)
		{
			$fileInfo['linkName'] = readlink($fileName);
		}
		else
		{
			$fileInfo['linkName'] = '';
		}

		if (($typeFlag == self::TYPEFLAG_BLOCKDEV) || ($typeFlag == self::TYPEFLAG_CHARDEV))
		{
			$fileInfo['devMajor'] = ($fileStat['rdev'] >> 8) & 0xFF;
			$fileInfo['devMinor'] = $fileStat['rdev'] & 0xFF;
		}
		else
		{
			$fileInfo['devMajor'] = 0;
			$fileInfo['devMinor'] = 0;
		}

		$fileInfo['prefix'] = '';
		$this->fileList[] = $fileInfo;
	}

	/**
	 * Remove a file from the archive
	 * @param int $index
	 * @throws \OutOfBoundsException
	 */
	public function remove(int $index)
	{
		if (!isset($this->fileList[$index]))
		{
			throw new \OutOfBoundsException('Index '.$index.' does not exist');
		}

		$tmpFileList = array();

		foreach ($this->fileList as $loopIndex => $fileInfo)
		{
			if ($loopIndex == $index)
			{
				continue;
			}

			$tmpFileList[] = $fileInfo;
		}

		$this->fileList = $tmpFileList;
	}
}
?>