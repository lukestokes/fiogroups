<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class GroupTest extends TestCase
{
    public $factory;
    public $fio_public_key = "FIO7EwPGwmYGZF6fkvBNzbzYegj2q2dAZsp292P9oxkK8yjso5uDq";
    public $group_account = "pmz3qk1c4jqj";
    public $domain = "testing";
    public $member_application_fee = 10 * 1000000000;

    public static function setUpBeforeClass(): void
    {
        exec("rm -rf \"" . __DIR__ . "/data\"");
        exec("mkdir \"" . __DIR__ . "/data\"");
        if (!function_exists("br")) {
            include __DIR__ . "/../objects.php";
        }
    }
    public function setUp(): void
    {
        $this->factory = new Factory(__DIR__ . "/data");
    }

    public function testCanCreateGroup(): void
    {
        // Create a Group
        $Group = $this->factory->new("Group");
        $Group = $Group->create($this->fio_public_key, $this->group_account, $this->domain, $this->member_application_fee);
        $this->assertInstanceOf(
            'Group',
            $Group
        );
    }

    public function testCanNotCreateDuplicateGroup(): void
    {
        // Create a Group
        $Group = $this->factory->new("Group");
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("A group for domain testing already exists.");
        $Group = $Group->create($this->fio_public_key, $this->group_account, $this->domain, $this->member_application_fee);
    }

    public function testCanCreateMultipleGroups(): void
    {
        // Create a Group
        $Group = $this->factory->new("Group");
        $Group = $Group->create($this->fio_public_key, $this->group_account, $this->domain . "1", $this->member_application_fee);
        $this->assertEquals("testing1",$Group->domain);
    }

    public function testCanListGroups(): void
    {
        $Group = $this->factory->new("Group");
        $groups_data = $Group->dataStore->findAll();
        $this->assertCount(2,$groups_data);
        foreach ($groups_data as $group_data) {
          $Group = $this->factory->new("Group");
          $Group->loadData($group_data);
          if ($Group->_id == 1) {
              $this->assertEquals("testing",$Group->domain);
          }
          if ($Group->_id == 2) {
              $this->assertEquals("testing1",$Group->domain);
          }
        }
    }


}
