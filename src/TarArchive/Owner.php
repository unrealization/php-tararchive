<?php
declare(strict_types=1);
/**
 * @package PHPClassCollection
 * @subpackage TarArchive
 * @link http://php-classes.sourceforge.net/ PHP Class Collection
 * @author Dennis Wronka <reptiler@users.sourceforge.net>
 */
namespace unrealization\TarArchive;
/**
 * @package PHPClassCollection
 * @subpackage TarArchive
 * @link http://php-classes.sourceforge.net/ PHP Class Collection
 * @author Dennis Wronka <reptiler@users.sourceforge.net>
 * @version 0.99.0
 * @license http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html LGPL 2.1
 */
class Owner
{
	private int $userId;
	private string $userName;
	private int $groupId;
	private string $groupName;

	public function __construct(int $userId, string $userName, int $groupId, string $groupName)
	{
		$this->userId = $userId;
		$this->userName = $userName;
		$this->groupId = $groupId;
		$this->groupName = $groupName;
	}

	public function getUserId(): int
	{
		return $this->userId;
	}

	public function getUserName(): string
	{
		return $this->userName;
	}

	public function getGroupId(): int
	{
		return $this->groupId;
	}

	public function getGroupName(): string
	{
		return $this->groupName;
	}
}