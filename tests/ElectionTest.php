<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ElectionTest extends TestCase
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
            // set up some candidates
            $this->group->apply($this->account, $this->fio_name, $this->bio, $this->transaction_id);
            $this->group->approve($this->account);
            $this->group->registerCandidate($this->account);

            $this->group->apply($this->account."1", $this->fio_name."1", $this->bio, $this->transaction_id);
            $this->group->approve($this->account."1");
            $this->group->registerCandidate($this->account."1");

            $this->group->apply($this->account."2", $this->fio_name."2", $this->bio, $this->transaction_id);
            $this->group->approve($this->account."2");
            $this->group->registerCandidate($this->account."2");

            $this->group->apply($this->account."3", $this->fio_name."3", $this->bio, $this->transaction_id);
            $this->group->approve($this->account."3");
            $this->group->registerCandidate($this->account."3");

            $this->group->apply($this->account."4", $this->fio_name."4", $this->bio, $this->transaction_id);
            $this->group->approve($this->account."4");
            $this->group->registerCandidate($this->account."4");

            // set up some normal members
            $this->group->apply($this->account."5", $this->fio_name."5", $this->bio, $this->transaction_id);
            $this->group->approve($this->account."5");
            $this->group->apply($this->account."6", $this->fio_name."6", $this->bio, $this->transaction_id);
            $this->group->approve($this->account."6");

            // set up a pending member
            $this->group->apply($this->account."7", $this->fio_name."7", $this->bio, $this->transaction_id);
        } else {
            $Group->loadData($group_data);
            $this->group = $Group;
        }
    }

    public function testCanNotVoteOnElectionNotFound(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("An election with epoch 1 for testing can't be found.");
        $this->group->vote($this->account, $this->account, 1, 10);
    }

    public function testHasNoActiveElection(): void
    {
        $has_election = $this->group->hasActiveElection();
        $this->assertFalse($has_election);
    }

    public function testCanCreateElection(): void
    {
        $Election = $this->factory->new("Election");
        $election_data = $Election->dataStore->findAll();
        $this->assertCount(0,$election_data);
        $vote_date = time() + 100000;
        $Election = $this->group->createElection(3, 2, 5, $vote_date);
        $this->assertInstanceOf(
            'Election',
            $Election
        );
        $election_data = $Election->dataStore->findAll();
        $this->assertCount(1,$election_data);
    }

    public function testHasActiveElection(): void
    {
        $has_election = $this->group->hasActiveElection();
        $this->assertTrue($has_election);
    }

    public function testCanNotCreateElectionWithExistingPendingElection(): void
    {
        $vote_date = time() + 100000;
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("An election with epoch 1 is still pending. Please complete that election before creating a new one.");
        $PendingMember = $this->group->createElection(3, 2, 5, $vote_date);
    }

    public function testCanVote(): void
    {
        $Vote = $this->factory->new("Vote");
        $criteria = [["domain","=",$this->domain],["voter_account","=",$this->account]];
        $found = $Vote->read($criteria);
        $this->assertFalse($found);
        $this->group->vote($this->account, $this->account, 1, 10);
        $found = $Vote->read($criteria);
        $this->assertTrue($found);
    }

    public function testCanNoVoteForTheSamePersonTwice(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj already voted for wntoh3fogzcj.");
        $this->group->vote($this->account, $this->account, 1, 10);
    }

    public function testCanVoteMultipleTimes(): void
    {
        $Vote = $this->factory->new("Vote");
        $vote_data = $Vote->dataStore->findAll();
        $this->assertCount(1,$vote_data);
        $this->group->vote($this->account, $this->account."1", 2, 10);
        $this->group->vote($this->account, $this->account."2", 3, 10);
        $this->group->vote($this->account, $this->account."3", 4, 10);
        $this->group->vote($this->account, $this->account."4", 5, 10);
        $vote_data = $Vote->dataStore->findAll();
        $this->assertCount(5,$vote_data);
    }

    public function testCanNotVoteMoreThanSpecifiedVotesPerMember(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("wntoh3fogzcj already voted 5 times.");
        $this->group->vote($this->account, $this->account."5", 2, 10);
    }

    public function testCanNotRecordVotesBeforeElectionVotingIsComplete(): void
    {
        $Election = $this->group->getCurrentElection();
        $date_display = date("Y-m-d H:i:s",$Election->vote_date);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("This election is not over. Please wait until after " . $date_display);
        $this->group->recordVoteResults();
    }

    public function testCanNotRemoveVoteThatHasNotBeenCast(): void
    {
        $Election = $this->group->getCurrentElection();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(" has not voted for ");
        $this->group->removeVote($this->account."4", $this->account."4");
    }

    public function testCanGetAdminCandidates(): void
    {
        $Election = $this->group->getCurrentElection();
        $admin_candidates = $Election->getAdminCandidates();
        $this->assertCount(5,$admin_candidates);
    }

    public function testCanNotVoteAfterElectionVoteDate(): void
    {
        $Election = $this->group->getCurrentElection();
        $Election->vote_date = time() - 10000;
        $Election->save();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Voting for epoch 1 is already closed. Now: " . time() . ", deadline: " . $Election->vote_date . ".");
        $this->group->vote($this->account."5", $this->account."5", 2, 100);
    }

    public function testCanNotRemoveVoteAfterElectionIsComplete(): void
    {
        $Election = $this->group->getCurrentElection();
        $Election->vote_date = time() - 10000;
        $Election->save();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Voting for epoch 1 is already closed. Now: " . time() . ", deadline: " . $Election->vote_date . ".");
        $this->group->removeVote($this->account."1", $this->account."1");
    }

    public function testCanRecordVotes(): void
    {
        $Election = $this->group->getCurrentElection();
        $Election->vote_date = time() + 100;
        $Election->save();

        $Member = $this->factory->new("Member");
        $criteria = ["domain","=",$this->domain];
        $members = $Member->readAll($criteria);
        $this->assertCount(8,$members);
        $criteria = [["domain","=",$this->domain],["is_admin","=",true]];
        $admins = $Member->readAll($criteria);
        $this->assertCount(1,$admins);

        $VoteResult = $this->factory->new("VoteResult");
        $criteria = [["domain","=",$this->domain],["epoch","=",1]];
        $voteresults = $VoteResult->readAll($criteria);
        $this->assertCount(0,$voteresults);

        $Vote = $this->factory->new("Vote");
        $criteria = [["domain","=",$this->domain],["epoch","=",1]];
        $votes = $Vote->readAll($criteria);
        $this->assertCount(5,$votes);

        $this->group->vote($this->account."1", $this->account."1", 2, 100);
        $this->group->vote($this->account."1", $this->account."2", 3, 100);
        $this->group->vote($this->account."1", $this->account."3", 4, 100);
        $this->group->vote($this->account."1", $this->account."4", 5, 10);
        $this->group->vote($this->account."1", $this->account."5", 5, 10);

        $this->group->vote($this->account."2", $this->account."1", 2, 100);
        $this->group->vote($this->account."2", $this->account."2", 3, 100);

        $this->group->vote($this->account."3", $this->account."1", 2, 1000);

        $this->group->vote($this->account."4", $this->account."2", 2, 100);

        $criteria = [["domain","=",$this->domain],["epoch","=",1]];
        $votes = $Vote->readAll($criteria);
        $this->assertCount(14,$votes);

        $Election->vote_date = time() - 10000;
        $Election->save();

        $admin_candidates = $Election->getAdminCandidates();
        $this->assertCount(5,$admin_candidates);

        $this->group->recordVoteResults();
        $this->group->read();

        $admin_candidates = $Election->getAdminCandidates();
        $this->assertCount(0,$admin_candidates);

        $criteria = [["domain","=",$this->domain],["epoch","=",1]];
        $voteresults = $VoteResult->readAll($criteria);
        $this->assertCount(5,$voteresults);

        $criteria = [["domain","=",$this->domain],["is_admin","=",true]];
        $admins = $Member->readAll($criteria);
        $this->assertCount(3,$admins);

        $this->assertEquals($this->account."1",$voteresults[0]->candidate_account);
        $this->assertEquals(1,$voteresults[0]->rank);
        $this->assertEquals(1210,$voteresults[0]->votes);

        $this->assertEquals($this->account."2",$voteresults[1]->candidate_account);
        $this->assertEquals(2,$voteresults[1]->rank);
        $this->assertEquals(310,$voteresults[1]->votes);

        $this->assertEquals($this->account."3",$voteresults[2]->candidate_account);
        $this->assertEquals(3,$voteresults[2]->rank);
        $this->assertEquals(110,$voteresults[2]->votes);

        $this->assertEquals(2,$this->group->epoch);
    }

    public function testCanCreateSecondElection(): void
    {
        $this->assertEquals(2,$this->group->epoch);
        $Election = $this->factory->new("Election");
        $criteria = ["domain","=",$this->domain];
        $elections = $Election->readAll($criteria);
        $this->assertCount(1,$elections);
        $vote_date = time() + 100000;
        $Election = $this->group->createElection(3, 2, 5, $vote_date);
        $this->assertInstanceOf(
            'Election',
            $Election
        );
        $elections = $Election->readAll($criteria);
        $this->assertCount(2,$elections);
        $this->group->read();
        $this->assertEquals(2,$this->group->epoch);

        $this->group->vote($this->account."1", $this->account."1", 2, 100);
        $this->group->vote($this->account."1", $this->account."2", 3, 100);
        $this->group->vote($this->account."1", $this->account."3", 4, 100);
        $this->group->vote($this->account."1", $this->account."4", 5, 10);
        $this->group->vote($this->account."1", $this->account."5", 5, 10);
        $this->group->vote($this->account."2", $this->account."1", 2, 100);
        $this->group->vote($this->account."2", $this->account."2", 3, 100);
        $this->group->vote($this->account."3", $this->account."1", 2, 1000);
        $this->group->vote($this->account."4", $this->account."2", 2, 100);
        $Election->vote_date = time() - 10000;
        $Election->save();
        $this->group->recordVoteResults();
        $this->group->read();
        $this->assertEquals(3,$this->group->epoch);
    }

}
