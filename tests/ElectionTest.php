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
            $this->group = $Group->create($this->fio_public_key, $this->group_account, $this->domain, $this->member_application_fee);
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
        $this->expectExceptionMessage("An election with epoch  for testing can't be found.");
        $this->group->vote($this->account, $this->account, 1, 10);
    }

    public function testCanCreateElection(): void
    {
        $Election = $this->factory->new("Election");
        $election_data = $Election->dataStore->findAll();
        $this->assertCount(0,$election_data);
        $vote_time = time() + 100000;
        $Election = $this->group->createElection(3, 2, 5, $vote_time);
        $this->assertInstanceOf(
            'Election',
            $Election
        );
        $election_data = $Election->dataStore->findAll();
        $this->assertCount(1,$election_data);
    }

    public function testCanNotCreateElectionWithExistingPendingElection(): void
    {
        $vote_time = time() + 100000;
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("An election with epoch 1 is still pending. Please complete that election before creating a new one.");
        $PendingMember = $this->group->createElection(3, 2, 5, $vote_time);
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

    // TODO: recordVoteResults

}
