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
        $criteria = ["domain","!=",""];
        $groups = $Group->readAll($criteria);
        $this->assertCount(2,$groups);
        foreach ($groups as $Group) {
          if ($Group->_id == 1) {
              $this->assertEquals("testing",$Group->domain);
          }
          if ($Group->_id == 2) {
              $this->assertEquals("testing1",$Group->domain);
          }
        }
    }

    public function testCanGetMembers(): void
    {
        $Group = $this->factory->new("Group");
        $Group->_id = 1;
        $Group->read();

        $Member = $this->factory->new("Member");
        $Member->member_name = "test";
        $Member->domain = "testing";
        $Member->account = "wntoh3fogzcj";
        $Member->bio = "some info";
        $Member->date_added = 1614808323;
        $Member->last_verified_date = 1614808323;
        $Member->is_admin = false;
        $Member->is_active = true;
        $Member->save();

        $Member->_id = null;
        $Member->member_name = "test1";
        $Member->account = "wntoh3fogzcj1";
        $Member->save();

        $Member->_id = null;
        $Member->member_name = "test2";
        $Member->account = "wntoh3fogzcj2";
        $Member->save();

        $members = $Group->getMembers();
        $this->assertCount(3,$members);
        $this->assertEquals("test1",$members[1]->member_name);
    }

    public function testCanGetAdmins(): void
    {
        $Group = $this->factory->new("Group");
        $Group->_id = 1;
        $Group->read();

        $Member = $this->factory->new("Member");
        $Member->_id = 1;
        $Member->read();
        $Member->is_admin = true;
        $Member->save();

        $admins = $Group->getAdmins();
        $this->assertCount(1,$admins);
        $this->assertEquals("test",$admins[0]->member_name);
    }

    public function testCanGetAdminCandidates(): void
    {
        $Group = $this->factory->new("Group");
        $Group->_id = 1;
        $Group->read();

        $Group->registerCandidate("wntoh3fogzcj");
        $Group->registerCandidate("wntoh3fogzcj1");

        $admin_candidates = $Group->getAdminCandidates();
        $this->assertCount(2,$admin_candidates);
        $this->assertEquals("wntoh3fogzcj",$admin_candidates[0]->account);
    }

    public function testCanPrint(): void
    {
        $Group = $this->factory->new("Group");
        $Group->_id = 1;
        $Group->read();
        ob_start();
        $Group->print("table_header");
        $Group->print("table");
        $output = ob_get_contents();
        ob_end_clean();
        $expected = '<tr>
<th>Domain</th>
<th>Group Fio Public Key</th>
<th>Group Account</th>
<th>Member Application Fee</th>
<th>Epoch</th>
<th>Date Created</th>
<th> Id</th>
</tr>
<tr>
<td>testing</td>
<td>FIO7EwPGwmYGZF6fkvBNzbzYegj2q2dAZsp292P9oxkK8yjso5uDq</td>
<td>pmz3qk1c4jqj</td>
<td>10000000000</td>
<td></td>
<td>' . date("Y-m-d H:i:s",time()) . '</td>
<td>1</td>
</tr>
';
        $this->assertEquals($expected,$output);
    }
}
