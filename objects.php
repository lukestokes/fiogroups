<?php

date_default_timezone_set('America/Puerto_Rico');

function br() { return (PHP_SAPI === 'cli' ? "\n" : "<br />"); }

class Factory {
    public $dataDir;

    function __construct($dataDir = null) {
        $this->dataDir = __DIR__ . "/data";
        if ($dataDir) {
            $this->dataDir = $dataDir;
        }
    }

    function new($object_type) {
        return new $object_type($object_type, $this);
    }
}

class BaseObject {
    public $_id;

    public $dataDir;
    public $dataStore;
    public $factory;

    function __construct($object_type, $factory) {
        $this->dataDir = $factory->dataDir;
        $this->dataStore = new \SleekDB\Store($object_type, $this->dataDir);
        $this->factory = $factory;
    }

    function save() {
        $object_data = get_object_vars($this);
        unset($object_data["dataDir"]);
        unset($object_data["dataStore"]);
        unset($object_data["factory"]);
        if ($object_data["_id"]) {
            $this->dataStore->update($object_data);
        } else {
            unset($object_data["_id"]);
            $new_object_data = $this->dataStore->insert($object_data);
            $this->_id = $new_object_data["_id"];
        }
    }

    function delete() {
        if ($this->_id) {
            $this->dataStore->deleteById($this->_id);
        }
    }

    function loadData($data) {
        foreach (get_object_vars($this) as $key => $value) {
            if (array_key_exists($key, $data)) {
                $this->$key = $data[$key];
            }
        }
    }

    function read($criteria = null) {
        $object_data = null;
        if ($this->_id) {
            $object_data = $this->dataStore->findById($this->_id);
        } elseif($criteria) {
            $object_data = $this->dataStore->findOneBy($criteria);
        }
        if (!is_null($object_data)) {
            $this->loadData($object_data);
        }
        return !is_null($object_data);
    }

    function readAll($criteria) {
        $objects = array();
        $objects_data = $this->dataStore->findBy($criteria);
        foreach ($objects_data as $object_data) {
            $Object = $this->factory->new(get_class($this));
            $Object->loadData($object_data);
            $objects[] = $Object;
        }
        return $objects;
    }

    function print() {
        $object_data = get_object_vars($this);
        unset($object_data["dataDir"]);
        unset($object_data["dataStore"]);
        unset($object_data["factory"]);

        foreach ($object_data as $key => $value) {
            print $key . " = " . $value . br();
        }
    }
}

class Group extends BaseObject {
    /**
     * FIO Public Key of the account which owns the domain.
     * Used for historical purposes once account becomes an msig.
     */
    public $group_fio_public_key;
    /**
     * NEW
     */
    public $group_account;
    public $domain;
    /**
     * in SUFs
     */
    public $member_application_fee;
    /**
     * NEW
     * Used to keep track of the current election
     */
    public $epoch;
    public $date_created;

    /**
     * Throws exception
     */
    function create($fio_public_key, $group_account, $domain, $member_application_fee) {
        $Group = $this->factory->new("Group");
        $criteria = ["domain","=",$domain];
        $found = $Group->read($criteria);
        if ($found) {
            throw new Exception("A group for domain " . $domain . " already exists.", 1);
        }
        // TODO: check to see if this key has an account yet
        $Group->group_fio_public_key = $fio_public_key;
        // TODO: check to see if this domain exists on chain yet
        // TODO: support existing domains if owned by the key?
        // TODO: create domain if needed
        $Group->domain = $domain;
        // TODO: should I calculate this automatically using the pub key?
        $Group->group_account = $group_account;
        $Group->member_application_fee = $member_application_fee;
        $Group->date_created = time();
        $Group->save();
        return $Group;
    }

    function getMembers() {
        $Member = $this->factory->new("Member");
        $criteria = ["domain","=",$this->domain];
        $member_data = $Member->dataStore->findBy($criteria);

    }

    function getApplicatonFee($client) {
        $fee = 0;
        try {
            $params = array(
                "end_point" => "register_fio_address",
                "fio_address" => ""
            );
            $response = $client->post('/v1/chain/get_fee', [
                GuzzleHttp\RequestOptions::JSON => $params
            ]);
            $result = json_decode($response->getBody());
            $fee = $result->fee;
        } catch(\Exception $e) { }
        return ($this->member_application_fee + $fee);
    }

    function apply($account, $member_name_requested, $bio, $membership_payment_transaction_id) {
        $PendingMember = $this->factory->new("PendingMember");
        // TODO: double check on chain member_name_requested hasn't been claimed already.
        $criteria = [["domain","=",$this->domain],["account","=",$account]];
        $found = $PendingMember->read($criteria);
        if ($found) {
            throw new Exception($account . " is already a pending member for " . $this->domain . ".", 1);
        }
        $criteria = [["domain","=",$this->domain],["member_name_requested","=",$member_name_requested]];
        $found = $PendingMember->read($criteria);
        if ($found) {
            throw new Exception($member_name_requested . "@" . $this->domain . " has already been requested by a pending member of " . $this->domain . ".", 1);
        }
        $Member = $this->factory->new("Member");
        $criteria = [["domain","=",$this->domain],["account","=",$account]];
        $found = $Member->read($criteria);
        if ($found) {
            throw new Exception($account . " is already a member of " . $this->domain . ".", 1);
        }
        $criteria = [["domain","=",$this->domain],["member_name","=",$member_name_requested]];
        $found = $Member->read($criteria);
        if ($found) {
            throw new Exception($member_name_requested . "@" . $this->domain . " has already been claimed by an existing member of " . $this->domain . ".", 1);
        }
        $PendingMember->member_name_requested = $member_name_requested;
        $PendingMember->domain = $this->domain;
        $PendingMember->account = $account;
        $PendingMember->bio = $bio;
        $PendingMember->application_date = time();
        $PendingMember->membership_payment_transaction_id = $membership_payment_transaction_id;
        $PendingMember->save();
        return $PendingMember;
    }

    function approve($account) {
        $PendingMember = $this->factory->new("PendingMember");
        $criteria = [["domain","=",$this->domain],["account","=",$account]];
        $found = $PendingMember->read($criteria);
        if (!$found) {
            throw new Exception($account . " is not a pending member for " . $this->domain . ".", 1);
        }
        // TODO: check on chain to ensure membership_payment_transaction_id is valid
        // TODO: double check member_name_requested hasn't been claimed already.
        $Member = $this->factory->new("Member");
        $Member->member_name = $PendingMember->member_name_requested;
        $Member->domain = $this->domain;
        $Member->account = $account;
        $Member->bio = $PendingMember->bio;
        $Member->date_added = time();
        $Member->last_verified_date = time();
        // TODO: set to true if there are no other group members?
        $Member->is_admin = false;
        $Member->save();
        $PendingMember->delete();
        return $Member;
    }

    function updateBio($account, $bio) {
        $Member = $this->factory->new("Member");
        $criteria = [["domain","=",$this->domain],["account","=",$account]];
        $found = $Member->read($criteria);
        if (!$found) {
            throw new Exception($account . " is not a member of " . $this->domain . ".", 1);
        }
        $Member->bio = $bio;
        $Member->save();
    }
    function removeMember($account) {
        $Member = $this->factory->new("Member");
        $criteria = [["domain","=",$this->domain],["account","=",$account]];
        $found = $Member->read($criteria);
        if (!$found) {
            throw new Exception($account . " is not a member of " . $this->domain . ".", 1);
        }
        $Member->delete();
    }
    function verifyMember($account) {
        $Member = $this->factory->new("Member");
        $criteria = [["domain","=",$this->domain],["account","=",$account]];
        $found = $Member->read($criteria);
        if (!$found) {
            throw new Exception($account . " is not a member of " . $this->domain . ".", 1);
        }
        $Member->last_verified_date = time();
        $Member->save();
    }
    function registerCandidate($account) {
        $Member = $this->factory->new("Member");
        $criteria = [["domain","=",$this->domain],["account","=",$account]];
        $found = $Member->read($criteria);
        if (!$found) {
            throw new Exception($account . " is not a member of " . $this->domain . ".", 1);
        }
        $AdminCandidate = $this->factory->new("AdminCandidate");
        $criteria = [["domain","=",$this->domain],["account","=",$account]];
        $found = $AdminCandidate->read($criteria);
        if ($found) {
            throw new Exception($account . " is already an admin candidate for " . $this->domain . ".", 1);
        }
        $AdminCandidate->domain = $this->domain;
        $AdminCandidate->account = $account;
        $AdminCandidate->save();
    }
    function createElection($number_of_admins, $vote_threshold, $votes_per_member, $vote_time) {
        $Election = $this->factory->new("Election");
        $criteria = [["domain","=",$this->domain],["is_complete","=",false]];
        $found = $Election->read($criteria);
        if ($found) {
            throw new Exception("An election with epoch " . $Election->epoch . " is still pending. Please complete that election before creating a new one.", 1);
        }
        $electionQueryBuilder = $Election->dataStore->createQueryBuilder();
        $result = $electionQueryBuilder
            ->select(["max_epoch" => ["MAX" => "epoch"]])
            ->where(["domain","=",$this->domain])
            ->groupBy(["domain"], "domain_group")
            ->getQuery()
            ->fetch();
        $epoch = 1;
        if (isset($result["epoch"])) {
            $epoch = $result["epoch"]+1;
        }
        $this->epoch = $epoch;
        $this->save();
        $Election->domain = $this->domain;
        $Election->epoch = $epoch;
        $Election->vote_time = $vote_time;
        $Election->number_of_admins = $number_of_admins;
        $Election->vote_threshold = $vote_threshold;
        $Election->votes_per_member = $votes_per_member;
        $Election->save();
        return $Election;
    }

    function getCurrentElection() {
        $Election = $this->factory->new("Election");
        $found = $Election->read([["domain","=",$this->domain],["epoch","=",$this->epoch]]);
        if (!$found) {
            throw new Exception("An election with epoch " . $this->epoch . " for " . $this->domain . " can't be found.", 1);
        }
        return $Election;
    }

    function vote($voter_account, $candidate_account, $rank, $vote_weight) {
        $Election = $this->getCurrentElection();
        // note: this can throw exception
        $Election->vote($voter_account, $candidate_account, $rank, $vote_weight);
    }

    function removeVote($voter_account, $candidate_account) {
        $Election = $this->getCurrentElection();
        // note: this can throw exception
        $Election->removeVote($voter_account, $candidate_account);
    }

    function recordVoteResults() {
        $Election = $this->getCurrentElection();
        // note: this can throw exception
        $Election->recordVoteResults();
    }

}

class PendingMember extends BaseObject {
    /**
     * FIO address at domain
     */
    public $member_name_requested;
    public $domain;
    public $account;
    /**
     * Limit to 255 chars
     */
    public $bio;
    public $application_date;
    /**
     * NEW
     */
    public $membership_payment_transaction_id;
}

class Member extends BaseObject {
    public $member_name;
    public $domain;
    public $account;
    /**
     * Limit to 255 chars
     */
    public $bio;
    public $date_added;
    /**
     * Date the member was added to the group and then later updated each time membership is verified.
     */
    public $last_verified_date;
    /**
     * Set to true for groups creator or if elected as an admin of the group)
     */
    public $is_admin;
}

class AdminCandidate extends BaseObject {
    public $domain;
    /**
     * NEW: updated this to just account
     */
    public $account;
}

class Vote extends BaseObject {
    public $domain;
    /**
     * integer that increases with each election
     */
    public $epoch;
    public $voter_account;
    public $candidate_account;
    /**
     * use ranked choice voting
     * If a member votes for 5 candidates, they would rank each vote 1 through 5.
     */
    public $rank;
    /**
     * NEW: vote weight (number of tokens the user holds?)
     */
    public $vote_weight;
    /**
     * NEW:
     */
    public $time_of_vote;
}

class VoteResult extends BaseObject {
    public $domain;
    /**
     * integer that increases with each election
     */
    public $epoch;
    public $candidate_account;
    /**
     * The resulting rank for this candidate after the vote is complete
     */
    public $rank;
    /**
     * Total number of votes received by this candidate
     */
    public $votes;
}

class Election extends BaseObject {
    public $domain;
    /**
     * integer that increases with each election
     */
    public $epoch;
    public $vote_time;
    /**
     * Integer for the number of admins that are being elected.
     * This will be the number of accounts on the msig that owns the domain.
     */
    public $number_of_admins;
    /**
     * Integer for the number of admin votes need to approve any action requiring permissions of the group account.
     */
    public $vote_threshold;
    /**
     * Number of votes each member gets for this election.
     * For example, if doing approval voting like EOS, you might allow for 30 votes to fill 21 spots. Or you could use the eosDAC model of 5 votes to elect 12 positions.
     */
    public $votes_per_member;
    /**
     * Marks if this election is completed.
     * Note: this field may not be needed if we just want to use “date_certified is not null” to accomplish the same thing.
     */
    public $is_complete = false;
    /**
     * The date this election was marked as complete.
     */
    public $date_certified;

    function vote($voter_account, $candidate_account, $rank, $vote_weight) {
        $Vote = $this->factory->new("Vote");
        if (time() > $this->vote_time) {
            throw new Exception("Voting for epoch " . $this->epoch . " is already closed. Now: " . time() . ", deadline: " . $this->vote_time . ".", 1);
        }
        $voteQueryBuilder = $Vote->dataStore->createQueryBuilder();
        // can't vote for the same person twice
        $already_voted = $Vote->read([
            ["domain","=",$this->domain],
            ["epoch","=",$this->epoch],
            ["voter_account","=",$voter_account],
            ["candidate_account","=",$candidate_account],
        ]);
        if ($already_voted) {
            throw new Exception($voter_account . " already voted for " . $candidate_account . ".", 1);
        }
        $result = $voteQueryBuilder
            ->where([["domain","=",$this->domain],["epoch","=",$this->epoch],["voter_account","=",$voter_account]])
            ->groupBy(["domain","epoch","voter_account"], "number_of_votes")
            ->getQuery()
            ->fetch();
        if (isset($result[0]['number_of_votes']) && $result[0]['number_of_votes'] >= $this->votes_per_member) {
            throw new Exception($voter_account . " already voted " . $result[0]['number_of_votes'] . " times.", 1);
        }
        $Vote->domain = $this->domain;
        $Vote->epoch = $this->epoch;
        $Vote->voter_account = $voter_account;
        $Vote->candidate_account = $candidate_account;
        $Vote->rank = $rank;
        $Vote->vote_weight = $vote_weight;
        $Vote->time_of_vote = time();
        $Vote->save();
    }

    function removeVote($voter_account, $candidate_account) {
        $Vote = $this->factory->new("Vote");
        if (time() > $this->vote_time) {
            throw new Exception("Voting for epoch " . $this->epoch . " is already closed. Now: " . time() . ", deadline: " . $this->vote_time . ".", 1);
        }
        // can't vote for the same person twice
        $already_voted = $Vote->read([
            ["domain","=",$this->domain],
            ["epoch","=",$this->epoch],
            ["voter_account","=",$voter_account],
            ["candidate_account","=",$candidate_account],
        ]);
        if (!$already_voted) {
            throw new Exception($voter_account . " has not voted for " . $candidate_account . ".", 1);
        }
        $Vote->delete();
    }

    function getAdminCandidates() {
        $AdminCandidate = $this->factory->new("AdminCandidate");
        $criteria = ["domain","=",$this->domain];
        $admin_candidates = $AdminCandidate->readAll($criteria);
        return $admin_candidates;
    }

    function recordVoteResults() {
        // TODO: Implemented ranked choice voting
        // https://github.com/fidian/rcv/blob/master/rcv.php
        if (time() <= $this->vote_time) {
            throw new Exception("This election is not over. Please wait until after " . $this->vote_time, 1);
        }
        $admin_candidates = $this->getAdminCandidates();
        $Vote = $this->factory->new("Vote");
        $votes = array();
        $criteria = [["domain","=",$this->domain],["epoch","=",$this->epoch]];
        $votes = $Vote->readAll($criteria);
        $vote_results = array();
        foreach ($admin_candidates as $AdminCandidate) {
            $VoteResult = $this->factory->new("VoteResult");
            $VoteResult->domain = $this->domain;
            $VoteResult->epoch = $this->epoch;
            $VoteResult->candidate_account = $AdminCandidate->account;
            $VoteResult->rank = 0; // update this later
            $VoteResult->votes = 0; // update this later
            foreach ($votes as $Vote) {
                if ($Vote->candidate_account == $AdminCandidate->account) {
                    $VoteResult->votes += $Vote->vote_weight;
                }
            }
            $vote_results[] = $VoteResult;
        }

        usort($vote_results, function($a, $b) {
            return ($b->votes - $a->votes);
        });

        // finish up
        $rank = 1;
        foreach ($vote_results as $VoteResult) {
            $VoteResult->rank = $rank;
            $VoteResult->save();
            $rank++;
        }
        $vote_results = array_slice($vote_results, 0, $this->number_of_admins);

        $Member = $this->factory->new("Member");
        $criteria = ["domain","=",$this->domain];
        $members = $Member->readAll($criteria);
        foreach ($members as $Member) {
            $Member->is_admin = false;
            $Member->save();
        }
        foreach ($vote_results as $VoteResult) {
            foreach ($members as $Member) {
                if ($Member->account == $VoteResult->candidate_account) {
                    $Member->is_admin = true;
                    $Member->save();
                }
            }
        }

        foreach ($admin_candidates as $AdminCandidate) {
            $AdminCandidate->delete();
        }

        // TODO: queue up a multisig transaction to adjust the permissions of the group account based on the election results

        $this->is_complete = true;
        $this->date_certified = time();
        $this->save();
    }
}
