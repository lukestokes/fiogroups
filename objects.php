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

    public $internal_fields = array('non_printable_fields','internal_fields','dataDir','dataStore','factory');
    public $non_printable_fields = array();
    public $dataDir;
    public $dataStore;
    public $factory;

    function __construct($object_type, $factory) {
        $this->dataDir = $factory->dataDir;
        $this->dataStore = new \SleekDB\Store($object_type, $this->dataDir);
        $this->factory = $factory;
        $this->non_printable_fields = array_merge($this->internal_fields, $this->non_printable_fields);
    }

    function getData() {
        $object_data = get_object_vars($this);
        foreach ($this->internal_fields as $internal_field) {
            unset($object_data[$internal_field]);
        }
        return $object_data;
    }

    function getPrintableFields() {
        $object_data = get_object_vars($this);
        foreach ($this->non_printable_fields as $non_printable_field) {
            unset($object_data[$non_printable_field]);
        }
        return $object_data;
    }

    function save() {
        $object_data = $this->getData();
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
        $object_data = $this->getData();
        foreach ($object_data as $key => $value) {
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

    function print($format = "", $controls = null) {
        $object_data = $this->getData();
        $printable_object_data = $this->getPrintableFields();
        if ($format == "") {
            foreach ($printable_object_data as $key => $value) {
                print $key . " = " . $value . br();
            }
        }
        $display_keys = array();
        foreach ($object_data as $key => $value) {
            $display_keys[] = '$' . $key;
        }
        if ($format == "table") {
            print "<tr>\n";
            // pre-parse
            $values_to_display = array();
            foreach ($printable_object_data as $key => $value) {
                $value_to_display = $value;
                if (substr($key, 0, 3) == "is_") {
                    if ($value) {
                        $value_to_display = "true";
                    } else {
                        $value_to_display = "false";
                    }
                } elseif (strpos($key, "date") !== false && $key != "candidate_account") {
                    if ($value) {
                        $value_to_display = date("Y-m-d H:i:s",$value);
                    } else {
                        $value_to_display = "";
                    }
                }
                $values_to_display[$key] = $value_to_display;
                $object_data[$key] = $value_to_display;
            }
            foreach ($values_to_display as $key => $value_to_display) {
                print "<td>";
                if ($controls && array_key_exists($key, $controls)) {
                    $value_to_display = str_replace(
                        $display_keys,
                        $object_data,
                        $controls[$key]);
                }
                print $value_to_display;
                print "</td>\n";
            }
            print "</tr>\n";
        }
        if ($format == "table_header") {
            print "<tr>\n";
            foreach ($printable_object_data as $key => $value) {
                print "<th>";
                print ucwords(str_replace("_", " ", $key));
                print "</th>\n";
            }
            print "</tr>\n";
        }
    }

    function formHTML($values = array()) {
        $object_data = $this->getPrintableFields();
        unset($object_data["_id"]);
        if (array_key_exists("epoch", $object_data)) {
            unset($object_data["epoch"]);
        }
        $form_html = '';
        foreach ($object_data as $key => $value) {
            $form_value = $value;
            if (array_key_exists($key, $values)) {
                $form_value = $values[$key];
            }
            if (substr($key, 0, 3) != "is_" && strpos($key, "date") === false) {
                $form_html .= '
                <div class="col-sm-2">
                    <div class="form-group">
                        <label>' . ucwords(str_replace("_", " ", $key)) . '</label>
                        <input type="text" name="' . $key . '" id="' . $key . '" class="form-control" value="' . $form_value . '">
                    </div>
                </div>';
            }
        }
        return $form_html;
    }

}

class Group extends BaseObject {
    public $domain;
    /**
     * FIO Public Key of the account which owns the domain.
     * Used for historical purposes once account becomes an msig.
     */
    public $group_fio_public_key;
    /**
     * NEW
     */
    public $group_account;
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
    function create($creator_account, $creator_member_name, $fio_public_key, $group_account, $domain, $member_application_fee) {
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
        $membership_payment_transaction_id = 12345;
        $Group->domain = $domain;
        // TODO: should I calculate this automatically using the pub key?
        $Group->group_account = $group_account;
        $Group->member_application_fee = $member_application_fee;
        $Group->date_created = time();
        $Group->save();

        // TODO: adjust the permissions of the group so that $creator_account is the owner
        $bio = "I am Satoshi.";
        $Group->apply($creator_account, $creator_member_name, $bio, $membership_payment_transaction_id);
        $Group->approve($creator_account);
        $members = $Group->getMembers();
        $members[0]->is_admin = true;
        $members[0]->save();
        return $Group;
    }

    function getPendingMembers() {
        $PendingMember = $this->factory->new("PendingMember");
        $criteria = [["domain","=",$this->domain]];
        $pendingmembers = $PendingMember->readAll($criteria);
        return $pendingmembers;
    }

    function getMembers() {
        $Member = $this->factory->new("Member");
        $criteria = [["domain","=",$this->domain], ["is_active", "=", true]];
        $members = $Member->readAll($criteria);
        return $members;
    }

    function getInactiveMembers() {
        $Member = $this->factory->new("Member");
        $criteria = [["domain","=",$this->domain], ["is_active", "=", false]];
        $members = $Member->readAll($criteria);
        return $members;
    }

    function getDisabledMembers() {
        $Member = $this->factory->new("Member");
        $criteria = [["domain","=",$this->domain], ["is_disabled", "=", true]];
        $members = $Member->readAll($criteria);
        return $members;
    }

    function getAdmins() {
        $Member = $this->factory->new("Member");
        $criteria = [["domain","=",$this->domain], ["is_active", "=", true], ["is_admin","=",true]];
        $admins = $Member->readAll($criteria);
        return $admins;
    }

    function getAdminCandidates() {
        $AdminCandidate = $this->factory->new("AdminCandidate");
        $criteria = [["domain","=",$this->domain]];
        $admincandidates = $AdminCandidate->readAll($criteria);
        return $admincandidates;
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
        // TODO: validate $membership_payment_transaction_id
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
        $Member->is_active = true;
        $Member->save();
        $PendingMember->delete();
        return $Member;
    }

    function deactivate($account) {
        $Member = $this->factory->new("Member");
        $criteria = [["domain","=",$this->domain],["account","=",$account]];
        $found = $Member->read($criteria);
        if (!$found) {
            throw new Exception($account . " is not a member of " . $this->domain . ".", 1);
        }
        if (!$Member->is_active) {
            throw new Exception($account . " is not active.", 1);
        }
        if ($Member->is_admin) {
            throw new Exception($account . " is an admin. Please hold a new election first.", 1);
        }
        $Member->is_active = false;
        $Member->save();
        return $Member;
    }

    function activate($account) {
        $Member = $this->factory->new("Member");
        $criteria = [["domain","=",$this->domain],["account","=",$account]];
        $found = $Member->read($criteria);
        if (!$found) {
            throw new Exception($account . " is not a member of " . $this->domain . ".", 1);
        }
        if ($Member->is_disabled) {
            throw new Exception($account . " is disabled. Only the admins can enable and active the account.", 1);
        }
        if ($Member->is_active) {
            throw new Exception($account . " is already active.", 1);
        }
        $Member->is_active = true;
        $Member->save();
        return $Member;
    }

    function disable($account) {
        $Member = $this->factory->new("Member");
        $criteria = [["domain","=",$this->domain],["account","=",$account]];
        $found = $Member->read($criteria);
        if (!$found) {
            throw new Exception($account . " is not a member of " . $this->domain . ".", 1);
        }
        if ($Member->is_disabled) {
            throw new Exception($account . " is already disabled.", 1);
        }
        if ($Member->is_admin) {
            throw new Exception($account . " is an admin. Please hold a new election first.", 1);
        }
        $Member->is_disabled = true;
        $Member->is_active = false;
        $Member->save();
        return $Member;
    }

    function enable($account) {
        $Member = $this->factory->new("Member");
        $criteria = [["domain","=",$this->domain],["account","=",$account]];
        $found = $Member->read($criteria);
        if (!$found) {
            throw new Exception($account . " is not a member of " . $this->domain . ".", 1);
        }
        if (!$Member->is_disabled) {
            throw new Exception($account . " is not disabled.", 1);
        }
        $Member->is_disabled = false;
        $Member->save();
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
        if ($Member->is_active) {
            throw new Exception($account . " is still active within " . $this->domain . ". Only deactivated members can be removed.", 1);
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
        if (!$Member->is_active) {
            throw new Exception($account . " is not active within " . $this->domain . ". Only activate members can be verfied.", 1);
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
    function createElection($number_of_admins, $vote_threshold, $votes_per_member, $vote_date) {
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
        $Election->vote_date = $vote_date;
        $Election->number_of_admins = $number_of_admins;
        $Election->vote_threshold = $vote_threshold;
        $Election->votes_per_member = $votes_per_member;
        $Election->save();
        return $Election;
    }

    function hasActiveElection() {
        $has_election = false;
        try {
            $Election = $this->getCurrentElection();
            if (!$Election->is_complete) {
                $has_election = true;
            }
        } catch (Exception $e) { }
        return $has_election;
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
        $this->epoch++;
        $this->save();
    }

}

class PendingMember extends BaseObject {
    public $domain;
    /**
     * FIO address at domain
     */
    public $member_name_requested;
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
    public $non_printable_fields = array('domain','membership_payment_transaction_id');

}

class Member extends BaseObject {
    public $domain;
    public $member_name;
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
    public $is_disabled;
    /**
     * NEW
     */
    public $is_active;

    public $non_printable_fields = array('domain');
}

class AdminCandidate extends BaseObject {
    public $domain;
    /**
     * NEW: updated this to just account
     */
    public $account;
    public $non_printable_fields = array('domain');
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
    public $date_of_vote;
    public $non_printable_fields = array('domain');
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
    public $non_printable_fields = array('domain');
}

class Election extends BaseObject {
    public $domain;
    /**
     * integer that increases with each election
     */
    public $epoch;
    public $vote_date;
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

    public $non_printable_fields = array('domain');

    function vote($voter_account, $candidate_account, $rank, $vote_weight) {
        $Vote = $this->factory->new("Vote");
        if (time() > $this->vote_date) {
            throw new Exception("Voting for epoch " . $this->epoch . " is already closed. Now: " . time() . ", deadline: " . $this->vote_date . ".", 1);
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
        $Vote->date_of_vote = time();
        $Vote->save();
    }

    function removeVote($voter_account, $candidate_account) {
        $Vote = $this->factory->new("Vote");
        if (time() > $this->vote_date) {
            throw new Exception("Voting for epoch " . $this->epoch . " is already closed. Now: " . time() . ", deadline: " . $this->vote_date . ".", 1);
        }
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
        if (time() <= $this->vote_date) {
            $date_display = date("Y-m-d H:i:s",$this->vote_date);
            throw new Exception("This election is not over. Please wait until after " . $date_display, 1);
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
