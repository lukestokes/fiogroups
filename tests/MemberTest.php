<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class MemberTest extends TestCase
{
    public $factory;
    public $group;
    public $group_set_up = false;
    public $fio_public_key = "FIO7EwPGwmYGZF6fkvBNzbzYegj2q2dAZsp292P9oxkK8yjso5uDq";
    public $group_account = "pmz3qk1c4jqj";
    public $domain = "testing";
    public $creator_account = "loggedinuser";
    public $creator_member_name = "satoshi@testing";
    public $member_application_fee = 10 * 1000000000;
    public $account = "wntoh3fogzcj";
    public $fio_name = "test";
    public $bio = "Some cool bio, yo.";
    public $transaction_id = "9255cd7196e6d4003a3352928f3fec63cdf2a9ae7834512f932e95363f6e5408";

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
        $this->createGroupIfNeeded();
    }

    public function createGroupIfNeeded() {
        $Group = $this->factory->new("Group");
        $group_data = $Group->dataStore->findOneBy(["domain","=",$this->domain]);
        if (is_null($group_data)) {
            $this->group = $Group->create(
                $this->creator_account,
                $this->creator_member_name,
                $this->fio_public_key,
                $this->group_account,
                $this->domain,
                $this->member_application_fee
            );
        } else {
            $Group->loadData($group_data);
            $this->group = $Group;
        }
    }

    public function testCanApplyForMembership(): void
    {
        $PendingMember = $this->group->apply($this->account, $this->fio_name, $this->bio, $this->transaction_id);
        $this->assertInstanceOf(
            'PendingMember',
            $PendingMember
        );
    }

    public function testCanNotApplyForMembershipMultipleTimes(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj is already a pending member for testing.");
        $PendingMember = $this->group->apply($this->account, $this->fio_name, $this->bio, $this->transaction_id);
    }

    public function testCanNotApplyUsingAPendingName(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("test@testing has already been requested by a pending member of testing.");
        $PendingMember = $this->group->apply($this->account."1", $this->fio_name, $this->bio, $this->transaction_id);
    }

    public function testCanApprovePendingMembership(): void
    {
        $Member = $this->group->approve($this->account);
        $this->assertInstanceOf(
            'Member',
            $Member
        );
    }

    public function testCanNotApplyUsingClaimedName(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("test@testing has already been claimed by an existing member of testing.");
        $PendingMember = $this->group->apply($this->account."1", $this->fio_name, $this->bio, $this->transaction_id);
    }

    public function testCanNotApplyForMembershipIfAlreadyAMember(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj is already a member of testing.");
        $PendingMember = $this->group->apply($this->account, $this->fio_name . "1", $this->bio, $this->transaction_id);
    }

    public function testCanNotApproveNonExistantMember(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj1 is not a pending member for testing.");
        $Member = $this->group->approve($this->account . "1");
    }

    public function testCanUpdateBio(): void
    {
        $Member = $this->factory->new("Member");
        $Member->_id = 2;
        $Member->read();
        $this->assertEquals($Member->bio, $this->bio);
        $new_bio = "This is my new bio";
        $this->group->updateBio($this->account,$new_bio);
        $Member->read();
        $this->assertEquals($Member->bio, $new_bio);
    }

    public function testCanNoUpdateBioMemberNotFound(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj1 is not a member of testing.");
        $this->group->updateBio($this->account . "1","foo");
    }

    public function testCanVerifyMember(): void
    {
        $Member = $this->factory->new("Member");
        $Member->_id = 2;
        $Member->read();
        $Member->last_verified_date = time() - 100;
        $Member->save();
        $now = time() - 1;
        $this->assertLessThan($now, $Member->last_verified_date);
        $this->group->verifyMember($this->account);
        $Member->read();
        $this->assertGreaterThan($now, $Member->last_verified_date);
    }

    public function testCanNoVerifyMemberNotFound(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj1 is not a member of testing.");
        $this->group->verifyMember($this->account . "1");
    }

    public function testCanNotRegisterCandidateMemberNotFound(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj1 is not a member of testing.");
        $this->group->registerCandidate($this->account . "1");
    }

    public function testCanRegisterCandidate(): void
    {
        $AdminCandidate = $this->factory->new("AdminCandidate");
        $criteria = ["account","=",$this->account];
        $found = $AdminCandidate->read($criteria);
        $this->assertFalse($found);
        $this->group->registerCandidate($this->account);
        $found = $AdminCandidate->read($criteria);
        $this->assertTrue($found);
    }

    public function testCanNotRegisterDuplicateCandidate(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj is already an admin candidate for testing.");
        $this->group->registerCandidate($this->account);
    }

    public function testCanNotRemoveMemberNotFound(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj1 is not a member of testing.");
        $this->group->removeMember($this->account . "1");
    }

    public function testCanPrint(): void
    {
        $Member = $this->factory->new("Member");
        $Member->_id = 2;
        $Member->read();
        $date_added = date("Y-m-d H:i:s",$Member->date_added);
        $last_verified_date = date("Y-m-d H:i:s",$Member->last_verified_date);
        ob_start();
        $Member->print("table_header");
        $Member->print("table");
        $output = ob_get_contents();
        ob_end_clean();
        $expected = '<tr>
<th>Member Name</th>
<th>Account</th>
<th>Bio</th>
<th>Date Added</th>
<th>Last Verified Date</th>
<th>Is Admin</th>
<th>Is Disabled</th>
<th>Is Active</th>
<th>Last Login Date</th>
<th> Id</th>
</tr>
<tr>
<td>test</td>
<td>wntoh3fogzcj</td>
<td>This is my new bio</td>
<td>' . $date_added . '</td>
<td>' . $last_verified_date . '</td>
<td>false</td>
<td>false</td>
<td>true</td>
<td></td>
<td>2</td>
</tr>
';
        $this->assertEquals($expected,$output);
    }

    public function testCanNotRemoveAnActiveMember(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj is still active within testing. Only deactivated members can be removed.");
        $this->group->removeMember($this->account);
    }

    public function testCanNotActivateAnActiveMember(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj is already active.");
        $this->group->activate($this->account);
    }

    public function testCanNotDeactivateAnAdmin(): void
    {
        $Member = $this->factory->new("Member");
        $Member->_id = 2;
        $Member->read();
        $Member->is_admin = true;
        $Member->save();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj is an admin. Please hold a new election first.");
        $this->group->deactivate($this->account);
    }

    public function testCanNotDisableAnAdmin(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj is an admin. Please hold a new election first.");
        $this->group->disable($this->account);
    }

    public function testCanDeactivateAMember(): void
    {
        $Member = $this->factory->new("Member");
        $Member->_id = 2;
        $Member->read();
        $Member->is_admin = false;
        $Member->save();
        $this->group->deactivate($this->account);
        $Member = $this->factory->new("Member");
        $Member->_id = 2;
        $Member->read();
        $this->assertFalse($Member->is_active);
    }

    public function testCanNotDeactivateAnInactiveMember(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj is not active.");
        $this->group->deactivate($this->account);
    }

    public function testCanActivateMember(): void
    {
        $this->group->activate($this->account);
        $Member = $this->factory->new("Member");
        $Member->_id = 2;
        $Member->read();
        $this->assertTrue($Member->is_active);
    }

    public function testCanNotEnableAMemberWhoIsNotDisabled(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj is not disabled.");
        $this->group->enable($this->account);
    }

    public function testCanDisableMember(): void
    {
        $this->group->disable($this->account);
        $Member = $this->factory->new("Member");
        $Member->_id = 2;
        $Member->read();
        $this->assertFalse($Member->is_active);
        $this->assertTrue($Member->is_disabled);
    }

    public function testCanNotActivateADisabledMember(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj is disabled. Only the admins can enable and active the account.");
        $this->group->activate($this->account);
    }

    public function testCanNotDisableADisabledMember(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj is already disabled");
        $this->group->disable($this->account);
    }

    public function testCanEnableDisableMember(): void
    {
        $this->group->enable($this->account);
        $Member = $this->factory->new("Member");
        $Member->_id = 2;
        $Member->read();
        $this->assertFalse($Member->is_disabled);
        $this->assertFalse($Member->is_active);
        $Member->is_disabled = true;
        $Member->is_active = false;
        $Member->save();
    }

    public function testCanRemoveDisabledMember(): void
    {
        $Member = $this->factory->new("Member");
        $Member->_id = 2;
        $found = $Member->read();
        $this->assertTrue($found);
        $Member->is_active = false;
        $Member->save();
        $this->group->removeMember($this->account);
        $Member = $this->factory->new("Member");
        $Member->_id = 2;
        $found = $Member->read();
        $this->assertFalse($found);
    }

}
