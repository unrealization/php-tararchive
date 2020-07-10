<?php
declare(strict_types=1);
/**
 * @package PHPClassCollection
 * @subpackage TarArchive
 * @link http://php-classes.sourceforge.net/ PHP Class Collection
 * @author Dennis Wronka <reptiler@users.sourceforge.net>
 */
namespace unrealization\PHPClassCollection\TarArchive;

use unrealization\PHPClassCollection\MbRegEx;
/**
 * @package PHPClassCollection
 * @subpackage TarArchive
 * @link http://php-classes.sourceforge.net/ PHP Class Collection
 * @author Dennis Wronka <reptiler@users.sourceforge.net>
 * @version 0.99.0
 * @license http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html LGPL 2.1
 */
class ArchiveEntry
{
	/**
	 * Type flag for files
	 * @var int
	 */
	public const TYPE_FILE		= 0;
	/**
	 * Type flag for hard links
	 * @var int
	 */
	public const TYPE_LINK		= 1;
	/**
	 * Type flag for symbolic links
	 * @var int
	 */
	public const TYPE_SYMLINK	= 2;
	/**
	 * Type flag for character devices
	 * @var int
	 */
	public const TYPE_CHARDEV	= 3;
	/**
	 * Type flag for block devices
	 * @var integer
	 */
	public const TYPE_BLOCKDEV	= 4;
	/**
	 * Type flag for directories
	 * @var integer
	 */
	public const TYPE_DIRECTORY	= 5;
	/**
	 * Type flag for fifos
	 * @var integer
	 */
	public const TYPE_FIFO		= 6;

	private $header			= null;
	private $name			= null;
	private $type			= null;
	private $permissions	= null;
	private $modified		= null;
	private $size			= 0;
	private $offset			= null;
	private $owner			= null;
	private $linkName		= '';
	private $devMajor		= 0;
	private $devMinor		= 0;
	private $prefix			= '';

	public static function fromHeader(string $header): self
	{
		$archiveEntry = new self();
		$archiveEntry->setHeader($header);
		$archiveEntry->setName(trim(mb_substr($header, 0, 100)));
		$archiveEntry->setType((int)mb_substr($header, 156, 1));
		$archiveEntry->setPermissions(octdec(trim(mb_substr($header, 100, 8))));

		try
		{
			$archiveEntry->setModificationDate(new \DateTime(date('Y-m-d H:i:s', octdec(trim(mb_substr($header, 136, 12))))));
		}
		catch (\Exception $e)
		{
			error_log($e->getMessage());
			$archiveEntry->setModificationDate(new \DateTime());
		}

		$archiveEntry->setSize(octdec(mb_substr($header, 124, 12)));
		$archiveEntry->setOwner(new Owner(octdec(mb_substr($header, 108, 8)), trim(mb_substr($header, 265, 32)), octdec(mb_substr($header, 116, 8)), trim(mb_substr($header, 297, 32))));
		$archiveEntry->setLinkName(trim(mb_substr($header, 157, 100)));
		$archiveEntry->setDevMajor(octdec(mb_substr($header, 329, 8)));
		$archiveEntry->setDevMinor(octdec(mb_substr($header, 337, 8)));
		$archiveEntry->setPrefix(trim(mb_substr($header, 345, 155)));
		return $archiveEntry;
	}

	public function updateHeader(): void
	{
		$name = MbRegEx::padString(MbRegEx::replace('^\.?\/?(.+)$', '\1', $this->getName()), 100, chr(0), STR_PAD_RIGHT);
		$permissions = MbRegEx::padString(((string)decoct($this->getPermissions())).chr(0), 8, '0', STR_PAD_LEFT);
		$modified = MbRegEx::padString(((string)decoct($this->getModificationDate()->format('U'))).chr(0), 12, '0', STR_PAD_LEFT);
		$size = MbRegEx::padString(((string)decoct($this->getSize())).chr(0), 12, '0', STR_PAD_LEFT);
		$userId = MbRegEx::padString(((string)decoct($this->getOwner()->getUserId())).chr(0), 8, '0', STR_PAD_LEFT);
		$userName = MbRegEx::padString($this->getOwner()->getUserName(), 32, chr(0), STR_PAD_RIGHT);
		$groupId = MbRegEx::padString(((string)decoct($this->getOwner()->getGroupId())).chr(0), 8, '0', STR_PAD_LEFT);
		$groupName = MbRegEx::padString($this->getOwner()->getGroupName(), 32, chr(0), STR_PAD_RIGHT);
		$linkName = MbRegEx::padString($this->getLinkName(), 100, chr(0), STR_PAD_RIGHT);
		$devMajor = MbRegEx::padString((string)decoct($this->getDevMajor()), 8, chr(0), STR_PAD_RIGHT);
		$devMinor = MbRegEx::padString((string)decoct($this->getDevMinor()), 8, chr(0), STR_PAD_RIGHT);
		$prefix = MbRegEx::padString($this->getPrefix(), 155, chr(0), STR_PAD_RIGHT);
		$ustar = 'ustar  '.chr(0);
		$checkSum = str_repeat(' ', 8);
		$header = $name.$permissions.$userId.$groupId.$size.$modified.$checkSum.$this->getType().$linkName.$ustar.$userName.$groupName.$devMajor.$devMinor.$prefix;
		$checkSum = 0;

		for ($pos = 0; $pos < mb_strlen($header); $pos++)
		{
			$checkSum += ord(mb_substr($header, $pos, 1));
		}

		$checkSum = MbRegEx::padString(((string)decoct($checkSum)).chr(0).' ', 8, '0', STR_PAD_LEFT);
		$header = MbRegEx::padString($name.$permissions.$userId.$groupId.$size.$modified.$checkSum.$this->getType().$linkName.$ustar.$userName.$groupName.$devMajor.$devMinor.$prefix, 512, chr(0), STR_PAD_RIGHT);
		$this->header = $header;
	}

	public function setHeader(string $header): void
	{
		$this->header = $header;
	}

	public function getHeader(): ?string
	{
		return $this->header;
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setType(int $type): void
	{
		switch ($type)
		{
			case self::TYPE_BLOCKDEV:
			case self::TYPE_CHARDEV:
			case self::TYPE_DIRECTORY:
			case self::TYPE_FIFO:
			case self::TYPE_FILE:
			case self::TYPE_LINK:
			case self::TYPE_SYMLINK:
				break;
			default:
				throw new \InvalidArgumentException('Unknown type '.$type);
				break;
		}

		$this->type = $type;
	}

	public function getType(): int
	{
		return $this->type;
	}

	public function setPermissions(int $permissions): void
	{
		$this->permissions = $permissions;
	}

	public function getPermissions(): int
	{
		return $this->permissions;
	}

	public function setModificationDate(\DateTime $modified): void
	{
		$this ->modified = $modified;
	}

	public function getModificationDate(): \DateTime
	{
		return $this->modified;
	}

	public function setSize(int $size): void
	{
		$this->size = $size;
	}

	public function getSize(): int
	{
		return $this->size;
	}

	public function setOffset(?int $offset): void
	{
		$this->offset = $offset;
	}

	public function getOffset(): ?int
	{
		return $this->offset;
	}

	public function setOwner(Owner $owner): void
	{
		$this->owner = $owner;
	}

	public function getOwner(): Owner
	{
		return $this->owner;
	}

	public function setLinkName(string $linkName): void
	{
		$this->linkName = $linkName;
	}

	public function getLinkName(): string
	{
		return $this->linkName;
	}

	public function setDevMajor(int $devMajor): void
	{
		$this->devMajor = $devMajor;
	}

	public function getDevMajor(): int
	{
		return $this->devMajor;
	}

	public function setDevMinor(int $devMinor): void
	{
		$this->devMinor = $devMinor;
	}

	public function getDevMinor(): int
	{
		return $this->devMinor;
	}

	public function setPrefix(string $prefix): void
	{
		$this->prefix = $prefix;
	}

	public function getPrefix(): string
	{
		return $this->prefix;
	}
}