<?php

date_default_timezone_set('America/Puerto_Rico');

function br()
{return (PHP_SAPI === 'cli' ? "\n" : "<br />");}

class Util
{
    public $client;
    public $fio_public_key;
    public $actor;
    public $balance;
    public $transfer_fee;
    public $domain_fee;

    function __construct($client) {
        $this->client = $client;
    }

    function getFIOPublicKey() {
        if ($this->fio_public_key) {
            return $this->fio_public_key;
        }
        $params = array(
            "account_name" => $this->actor
        );
        try {
            $get_account_response = $this->client->post('/v1/chain/get_account', [
                GuzzleHttp\RequestOptions::JSON => $params
            ]);
            $response = json_decode($get_account_response->getBody());
            //var_dump($response);
            foreach ($response->permissions as $key => $permission) {
              //var_dump($permission);
              if ($permission->perm_name == "active") {
                  if (isset($permission->required_auth->keys[0])) {
                      $this->fio_public_key = $permission->required_auth->keys[0]->key;
                  }
              }
            }
        } catch(\Exception $e) {
            //print $e->getMessage() . "\n";
        }
        return $this->fio_public_key;
    }

    function getFIOBalance() {
        if ($this->balance) {
            return $this->balance;
        }
        $this->getFIOPublicKey();
        $params = ["fio_public_key" => $this->fio_public_key];
        $get_fio_balance_response = $this->client->post('/v1/chain/get_fio_balance', [
            GuzzleHttp\RequestOptions::JSON => $params
        ]);
        $fio_balance_results = json_decode($get_fio_balance_response->getBody(), true);
        $balance = $fio_balance_results['balance'];
        $balance = $balance / 1000000000;
        $this->balance = $balance;
        return $this->balance;
    }

    function getFee($endpoint,$fio_address) {
        $fee = 0;
        try {
            $params = array(
                "end_point" => $endpoint,
                "fio_address" => $fio_address
            );
            $response = $this->client->post('/v1/chain/get_fee', [
                GuzzleHttp\RequestOptions::JSON => $params
            ]);
            $result = json_decode($response->getBody());
            $fee = $result->fee;
        } catch(\Exception $e) { }
        return $fee + ($fee * .1); // add 10% extra in case something changes between now and when it is executed.
    }

    public function getPendingMsig($account, $membership_proposal_name)
    {
        try {
            $params = array(
                "json" => true,
                "code" => "eosio.msig",
                "scope" => $account,
                "table" => "proposal",
                "lower_bound" => $membership_proposal_name,
                "upper_bound" => "",
                "index_position" => 1,
                "key_type" => "name",
                "limit" => 1,
            );
            $response = $this->client->post('/v1/chain/get_table_rows', [
                GuzzleHttp\RequestOptions::JSON => $params,
            ]);
            $result = json_decode($response->getBody());
        } catch (\Exception $e) {}
        return $result;
    }

    function getTransferFee() {
        if ($this->transfer_fee) {
            return $this->transfer_fee;
        }
        $this->transfer_fee = $this->getFee("transfer_tokens_pub_key","faucet@stokes");
        return $this->transfer_fee;
    }
    function getRegisterDomainFee() {
        if ($this->domain_fee) {
            return $this->domain_fee;
        }
        $this->domain_fee = $this->getFee("register_fio_domain","faucet@stokes");
        return $this->domain_fee;
    }
    function FIOToSUF($amount) {
      return ($amount * 1000000000);
    }
    function SUFToFIO($amount) {
      return ($amount / 1000000000);
    }

    function getProposalName() {
      $proposal_name = "";
      for ($i = 0; $i < 7; $i++) {
        $proposal_name .= rand(1,5);
      }
      return "apply" + $proposal_name;
    }

}

class Factory
{
    public $dataDir;

    public function __construct($dataDir = null)
    {
        $this->dataDir = __DIR__ . "/data";
        if ($dataDir) {
            $this->dataDir = $dataDir;
        }
    }

    function new ($object_type) {
        return new $object_type($object_type, $this);
    }
}

class BaseObject
{
    public $_id;

    public $internal_fields      = array('internal_fields', 'non_printable_fields', 'sort_field', 'dataDir', 'dataStore', 'factory');
    public $non_printable_fields = array();
    public $sort_field = "_id";
    public $dataDir;
    public $dataStore;
    public $factory;

    public function __construct($object_type, $factory)
    {
        $this->dataDir              = $factory->dataDir;
        $this->dataStore            = new \SleekDB\Store($object_type, $this->dataDir);
        $this->factory              = $factory;
        $this->non_printable_fields = array_merge($this->internal_fields, $this->non_printable_fields);
    }

    public function getData()
    {
        $object_data = get_object_vars($this);
        foreach ($this->internal_fields as $internal_field) {
            unset($object_data[$internal_field]);
        }
        return $object_data;
    }

    public function getPrintableFields()
    {
        $object_data = get_object_vars($this);
        foreach ($this->non_printable_fields as $non_printable_field) {
            unset($object_data[$non_printable_field]);
        }
        return $object_data;
    }

    public function save()
    {
        $object_data = $this->getData();
        if ($object_data["_id"]) {
            $this->dataStore->update($object_data);
        } else {
            unset($object_data["_id"]);
            $new_object_data = $this->dataStore->insert($object_data);
            $this->_id       = $new_object_data["_id"];
        }
    }

    public function delete()
    {
        if ($this->_id) {
            $this->dataStore->deleteById($this->_id);
        }
    }

    public function loadData($data)
    {
        $object_data = $this->getData();
        foreach ($object_data as $key => $value) {
            if (array_key_exists($key, $data)) {
                $this->$key = $data[$key];
            }
        }
    }

    public function read($criteria = null)
    {
        $object_data = null;
        if ($this->_id) {
            $object_data = $this->dataStore->findById($this->_id);
        } elseif ($criteria) {
            $object_data = $this->dataStore->findOneBy($criteria);
        }
        if (!is_null($object_data)) {
            $this->loadData($object_data);
        }
        return !is_null($object_data);
    }

    public function readAll($criteria)
    {
        $objects      = array();
        $queryBuilder = $this->dataStore->createQueryBuilder();
        $objects_data = $queryBuilder
            ->where($criteria)
            ->orderBy([$this->sort_field => "asc"])
            ->getQuery()
            ->fetch();
        foreach ($objects_data as $object_data) {
            $Object = $this->factory->new(get_class($this));
            $Object->loadData($object_data);
            $objects[] = $Object;
        }
        return $objects;
    }

    function print($format = "", $controls = array()) {
        $object_data           = $this->getData();
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
                        $value_to_display = date("Y-m-d H:i:s", $value);
                    } else {
                        $value_to_display = "";
                    }
                }
                $values_to_display[$key] = $value_to_display;
                $object_data[$key]       = $value_to_display;
            }
            foreach ($values_to_display as $key => $value_to_display) {
                print "<td>";
                if (array_key_exists($key, $controls)) {
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

    public function formHTML($values = array())
    {
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

class Group extends BaseObject
{
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
    public function create($creator_account, $creator_member_name, $fio_public_key, $group_account, $domain, $member_application_fee)
    {
        $Group    = $this->factory->new("Group");
        $criteria = ["domain", "=", $domain];
        $found    = $Group->read($criteria);
        if ($found) {
            throw new Exception("A group for domain " . $domain . " already exists.", 1);
        }

/*

create key pair
send funds to an address to create the domain and account there
change permissions on the account to be the account who created it
create domain in that qccount
create fio address in that account

*/

        // TODO: check to see if this key has an account yet
        $Group->group_fio_public_key = $fio_public_key;
        // TODO: check to see if this domain exists on chain yet
        // TODO: support existing domains if owned by the key?
        // TODO: create domain if needed
        $membership_payment_transaction_id = 12345;
        $Group->domain                     = $domain;
        // TODO: should I calculate this automatically using the pub key?
        $Group->group_account          = $group_account;
        $Group->member_application_fee = $member_application_fee;
        $Group->date_created           = time();
        $Group->epoch                  = 1;
        $Group->save();

        // TODO: adjust the permissions of the group so that $creator_account is the owner
        $bio = "I am Satoshi.";
        $proposal_name = "creator";
        $Group->apply($creator_account, $creator_member_name, $bio, $membership_payment_transaction_id, $proposal_name);
        $Group->approve($creator_account);
        $members              = $Group->getMembers();
        $members[0]->is_admin = true;
        $members[0]->save();
        return $Group;
    }

    public function getPendingMembers()
    {
        $PendingMember  = $this->factory->new("PendingMember");
        $criteria       = [["domain", "=", $this->domain]];
        $pendingmembers = $PendingMember->readAll($criteria);
        return $pendingmembers;
    }

    public function getMembers()
    {
        $Member   = $this->factory->new("Member");
        $criteria = [["domain", "=", $this->domain], ["is_active", "=", true]];
        $members  = $Member->readAll($criteria);
        return $members;
    }

    public function getInactiveMembers()
    {
        $Member   = $this->factory->new("Member");
        $criteria = [["domain", "=", $this->domain], ["is_active", "=", false]];
        $members  = $Member->readAll($criteria);
        return $members;
    }

    public function getDisabledMembers()
    {
        $Member   = $this->factory->new("Member");
        $criteria = [["domain", "=", $this->domain], ["is_disabled", "=", true]];
        $members  = $Member->readAll($criteria);
        return $members;
    }

    public function getAdmins()
    {
        $Member   = $this->factory->new("Member");
        $criteria = [["domain", "=", $this->domain], ["is_active", "=", true], ["is_admin", "=", true]];
        $admins   = $Member->readAll($criteria);
        return $admins;
    }

    public function getAdminCandidates()
    {
        $AdminCandidate  = $this->factory->new("AdminCandidate");
        $criteria        = [["domain", "=", $this->domain]];
        $admincandidates = $AdminCandidate->readAll($criteria);
        return $admincandidates;
    }

    public function getApplicatonFee($client)
    {
        $fee = 0;
        try {
            $params = array(
                "end_point"   => "register_fio_address",
                "fio_address" => "",
            );
            $response = $client->post('/v1/chain/get_fee', [
                GuzzleHttp\RequestOptions::JSON => $params,
            ]);
            $result = json_decode($response->getBody());
            $fee    = $result->fee;
        } catch (\Exception $e) {}
        return ($this->member_application_fee + $fee);
    }

    public function apply($account, $member_name_requested, $bio, $membership_payment_transaction_id, $membership_proposal_name)
    {
        $PendingMember = $this->factory->new("PendingMember");
        // TODO: validate $membership_payment_transaction_id
        // TODO: double check on chain member_name_requested hasn't been claimed already.
        $criteria = [["domain", "=", $this->domain], ["account", "=", $account]];
        $found    = $PendingMember->read($criteria);
        if ($found) {
            throw new Exception($account . " is already a pending member for " . $this->domain . ".", 1);
        }
        $criteria = [["domain", "=", $this->domain], ["member_name_requested", "=", $member_name_requested]];
        $found    = $PendingMember->read($criteria);
        if ($found) {
            throw new Exception($member_name_requested . "@" . $this->domain . " has already been requested by a pending member of " . $this->domain . ".", 1);
        }
        $Member   = $this->factory->new("Member");
        $criteria = [["domain", "=", $this->domain], ["account", "=", $account]];
        $found    = $Member->read($criteria);
        if ($found) {
            throw new Exception($account . " is already a member of " . $this->domain . ".", 1);
        }
        $criteria = [["domain", "=", $this->domain], ["member_name", "=", $member_name_requested]];
        $found    = $Member->read($criteria);
        if ($found) {
            throw new Exception($member_name_requested . "@" . $this->domain . " has already been claimed by an existing member of " . $this->domain . ".", 1);
        }
        $PendingMember->member_name_requested             = $member_name_requested;
        $PendingMember->domain                            = $this->domain;
        $PendingMember->account                           = $account;
        $PendingMember->bio                               = $bio;
        $PendingMember->application_date                  = time();
        $PendingMember->membership_payment_transaction_id = $membership_payment_transaction_id;
        $PendingMember->membership_proposal_name          = $membership_proposal_name;
        $PendingMember->save();
        return $PendingMember;
    }

    public function approve($account)
    {
        $PendingMember = $this->factory->new("PendingMember");
        $criteria      = [["domain", "=", $this->domain], ["account", "=", $account]];
        $found         = $PendingMember->read($criteria);
        if (!$found) {
            throw new Exception($account . " is not a pending member for " . $this->domain . ".", 1);
        }
        // TODO: check on chain to ensure membership_payment_transaction_id is valid
        // TODO: double check member_name_requested hasn't been claimed already.
        $Member                     = $this->factory->new("Member");
        $Member->member_name        = $PendingMember->member_name_requested;
        $Member->domain             = $this->domain;
        $Member->account            = $account;
        $Member->bio                = $PendingMember->bio;
        $Member->date_added         = time();
        $Member->last_verified_date = time();
        // TODO: set to true if there are no other group members?
        $Member->is_admin  = false;
        $Member->is_active = true;
        $Member->save();
        $PendingMember->delete();
        return $Member;
    }

    public function deactivate($account)
    {
        $Member   = $this->factory->new("Member");
        $criteria = [["domain", "=", $this->domain], ["account", "=", $account]];
        $found    = $Member->read($criteria);
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

    public function activate($account)
    {
        $Member   = $this->factory->new("Member");
        $criteria = [["domain", "=", $this->domain], ["account", "=", $account]];
        $found    = $Member->read($criteria);
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

    public function disable($account)
    {
        $Member   = $this->factory->new("Member");
        $criteria = [["domain", "=", $this->domain], ["account", "=", $account]];
        $found    = $Member->read($criteria);
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
        $Member->is_active   = false;
        $Member->save();
        return $Member;
    }

    public function enable($account)
    {
        $Member   = $this->factory->new("Member");
        $criteria = [["domain", "=", $this->domain], ["account", "=", $account]];
        $found    = $Member->read($criteria);
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

    public function updateBio($account, $bio)
    {
        $Member   = $this->factory->new("Member");
        $criteria = [["domain", "=", $this->domain], ["account", "=", $account]];
        $found    = $Member->read($criteria);
        if (!$found) {
            throw new Exception($account . " is not a member of " . $this->domain . ".", 1);
        }
        $Member->bio = $bio;
        $Member->save();
    }
    public function removeMember($account)
    {
        $Member   = $this->factory->new("Member");
        $criteria = [["domain", "=", $this->domain], ["account", "=", $account]];
        $found    = $Member->read($criteria);
        if (!$found) {
            throw new Exception($account . " is not a member of " . $this->domain . ".", 1);
        }
        if ($Member->is_active) {
            throw new Exception($account . " is still active within " . $this->domain . ". Only deactivated members can be removed.", 1);
        }
        $Member->delete();
    }
    public function verifyMember($account)
    {
        $Member   = $this->factory->new("Member");
        $criteria = [["domain", "=", $this->domain], ["account", "=", $account]];
        $found    = $Member->read($criteria);
        if (!$found) {
            throw new Exception($account . " is not a member of " . $this->domain . ".", 1);
        }
        if (!$Member->is_active) {
            throw new Exception($account . " is not active within " . $this->domain . ". Only activate members can be verfied.", 1);
        }
        $Member->last_verified_date = time();
        $Member->save();
    }
    public function registerCandidate($account)
    {
        $Member   = $this->factory->new("Member");
        $criteria = [["domain", "=", $this->domain], ["account", "=", $account]];
        $found    = $Member->read($criteria);
        if (!$found) {
            throw new Exception($account . " is not a member of " . $this->domain . ".", 1);
        }
        $AdminCandidate = $this->factory->new("AdminCandidate");
        $criteria       = [["domain", "=", $this->domain], ["account", "=", $account]];
        $found          = $AdminCandidate->read($criteria);
        if ($found) {
            throw new Exception($account . " is already an admin candidate for " . $this->domain . ".", 1);
        }
        $AdminCandidate->domain  = $this->domain;
        $AdminCandidate->account = $account;
        $AdminCandidate->save();
    }
    public function createElection($number_of_admins, $vote_threshold, $votes_per_member, $vote_date)
    {
        $Election = $this->factory->new("Election");
        $criteria = [["domain", "=", $this->domain], ["is_complete", "=", false]];
        $found    = $Election->read($criteria);
        if ($found) {
            throw new Exception("An election with epoch " . $Election->epoch . " is still pending. Please complete that election before creating a new one.", 1);
        }
        $Election->domain           = $this->domain;
        $Election->epoch            = $this->epoch;
        $Election->vote_date        = $vote_date;
        $Election->number_of_admins = $number_of_admins;
        $Election->vote_threshold   = $vote_threshold;
        $Election->votes_per_member = $votes_per_member;
        $Election->save();
        return $Election;
    }

    public function hasActiveElection()
    {
        $has_election = false;
        try {
            $Election = $this->getCurrentElection();
            if (!$Election->is_complete) {
                $has_election = true;
            }
        } catch (Exception $e) {}
        return $has_election;
    }

    public function getCurrentElection()
    {
        $Election = $this->factory->new("Election");
        $found    = $Election->read([["domain", "=", $this->domain], ["epoch", "=", $this->epoch]]);
        if (!$found) {
            throw new Exception("An election with epoch " . $this->epoch . " for " . $this->domain . " can't be found.", 1);
        }
        return $Election;
    }

    public function vote($voter_account, $candidate_account, $rank, $vote_weight)
    {
        $Election = $this->getCurrentElection();
        // note: this can throw exception
        $Election->vote($voter_account, $candidate_account, $rank, $vote_weight);
    }

    public function removeVote($voter_account, $candidate_account)
    {
        $Election = $this->getCurrentElection();
        // note: this can throw exception
        $Election->removeVote($voter_account, $candidate_account);
    }

    public function recordVoteResults()
    {
        $Election = $this->getCurrentElection();
        // note: this can throw exception
        $Election->recordVoteResults();
    }

    public function certifyVoteResults()
    {
        $Election = $this->getCurrentElection();
        // note: this can throw exception
        $Election->certifyVoteResults();
        $this->epoch++;
        $this->save();
    }

}

class PendingMember extends BaseObject
{
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
    public $membership_proposal_name;
    public $non_printable_fields = array('domain','membership_payment_transaction_id','membership_proposal_name');
    public $sort_field = "application_date";
}

class Member extends BaseObject
{
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
    public $last_login_date;

    public $non_printable_fields = array('domain');
    public $sort_field = "date_added";
}

class AdminCandidate extends BaseObject
{
    public $domain;
    /**
     * NEW: updated this to just account
     */
    public $account;
    public $non_printable_fields = array('domain');
}

class Vote extends BaseObject
{
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
    public $sort_field = "vote_weight";
}

class VoteResult extends BaseObject
{
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
    public $sort_field = "rank";
}

class Election extends BaseObject
{
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
    /*
     * NEW
     */
    public $results_proposer;
    public $results_proposal_name;

    public $non_printable_fields = array('domain','results_proposer','results_proposal_name');
    public $sort_field = "epoch";

    public function vote($voter_account, $candidate_account, $rank, $vote_weight)
    {
        // TODO: make sure you can only vote if you're a member

        $Vote = $this->factory->new("Vote");
        if (time() > $this->vote_date) {
            throw new Exception("Voting for epoch " . $this->epoch . " is already closed. Now: " . time() . ", deadline: " . $this->vote_date . ".", 1);
        }
        $voteQueryBuilder = $Vote->dataStore->createQueryBuilder();
        // can't vote for the same person twice
        $already_voted = $Vote->read([
            ["domain", "=", $this->domain],
            ["epoch", "=", $this->epoch],
            ["voter_account", "=", $voter_account],
            ["candidate_account", "=", $candidate_account],
        ]);
        if ($already_voted) {
            throw new Exception($voter_account . " already voted for " . $candidate_account . ".", 1);
        }
        $result = $voteQueryBuilder
            ->where([["domain", "=", $this->domain], ["epoch", "=", $this->epoch], ["voter_account", "=", $voter_account]])
            ->groupBy(["domain", "epoch", "voter_account"], "number_of_votes")
            ->getQuery()
            ->fetch();
        if (isset($result[0]['number_of_votes']) && $result[0]['number_of_votes'] >= $this->votes_per_member) {
            throw new Exception($voter_account . " already voted " . $result[0]['number_of_votes'] . " times.", 1);
        }
        $Vote->domain            = $this->domain;
        $Vote->epoch             = $this->epoch;
        $Vote->voter_account     = $voter_account;
        $Vote->candidate_account = $candidate_account;
        $Vote->rank              = $rank;
        $Vote->vote_weight       = $vote_weight;
        $Vote->date_of_vote      = time();
        $Vote->save();
    }

    public function removeVote($voter_account, $candidate_account)
    {
        $Vote = $this->factory->new("Vote");
        if (time() > $this->vote_date) {
            throw new Exception("Voting for epoch " . $this->epoch . " is already closed. Now: " . time() . ", deadline: " . $this->vote_date . ".", 1);
        }
        $already_voted = $Vote->read([
            ["domain", "=", $this->domain],
            ["epoch", "=", $this->epoch],
            ["voter_account", "=", $voter_account],
            ["candidate_account", "=", $candidate_account],
        ]);
        if (!$already_voted) {
            throw new Exception($voter_account . " has not voted for " . $candidate_account . ".", 1);
        }
        $Vote->delete();
    }

    public function getAdminCandidates()
    {
        $AdminCandidate   = $this->factory->new("AdminCandidate");
        $criteria         = ["domain", "=", $this->domain];
        $admin_candidates = $AdminCandidate->readAll($criteria);
        return $admin_candidates;
    }

    public function recordVoteResults()
    {
        // TODO: Implemented ranked choice voting
        // https://github.com/fidian/rcv/blob/master/rcv.php
        if (time() <= $this->vote_date) {
            $date_display = date("Y-m-d H:i:s", $this->vote_date);
            throw new Exception("This election is not over. Please wait until after " . $date_display, 1);
        }
        if ($this->is_complete) {
            throw new Exception("The voting results for this election have already been recorded.", 1);
        }
        $admin_candidates = $this->getAdminCandidates();
        $Vote             = $this->factory->new("Vote");
        $votes            = array();
        $criteria         = [["domain", "=", $this->domain], ["epoch", "=", $this->epoch]];
        $votes            = $Vote->readAll($criteria);
        $vote_results     = array();
        foreach ($admin_candidates as $AdminCandidate) {
            $VoteResult                    = $this->factory->new("VoteResult");
            $VoteResult->domain            = $this->domain;
            $VoteResult->epoch             = $this->epoch;
            $VoteResult->candidate_account = $AdminCandidate->account;
            $VoteResult->rank              = 0; // update this later
            $VoteResult->votes             = 0; // update this later
            foreach ($votes as $Vote) {
                if ($Vote->candidate_account == $AdminCandidate->account) {
                    $VoteResult->votes += $Vote->vote_weight;
                }
            }
            $vote_results[] = $VoteResult;
        }

        usort($vote_results, function ($a, $b) {
            return ($b->votes - $a->votes);
        });

        // finish up
        $rank = 1;
        foreach ($vote_results as $VoteResult) {
            $VoteResult->rank = $rank;
            if ($rank <= $this->number_of_admins) {
                $VoteResult->save();
            }
            $rank++;
        }
        $this->is_complete    = true;
        $this->save();
    }

    public function getVoteResults()
    {
        $VoteResult = $this->factory->new("VoteResult");
        $criteria = [["domain","=",$this->domain],["epoch","=",$this->epoch]];
        $vote_results = $VoteResult->readAll($criteria);
        return $vote_results;
    }

    public function certifyVoteResults()
    {
        global $Util; // fix this later
        global $explorer_url; // fix this later
        $results = $Util->getPendingMsig($this->results_proposer, $this->results_proposal_name);
        if (count($results->rows) > 0 && $results->rows[0]->proposal_name == $this->results_proposal_name) {
            throw new Exception('Please ensure the pending proposal <a href="' . $explorer_url . 'msig/' . $this->results_proposer . '/' . $this->results_proposal_name . '" target="_blank">' . $this->results_proposal_name . '</a> proposed by ' . $this->results_proposer . ' is approved and executed before certifying this elecion.', 1);
        }

        $vote_results = $this->getVoteResults();

        $Member   = $this->factory->new("Member");
        $criteria = ["domain", "=", $this->domain];
        $members  = $Member->readAll($criteria);
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
        $admin_candidates = $this->getAdminCandidates();

        foreach ($admin_candidates as $AdminCandidate) {
            $AdminCandidate->delete();
        }

        // TODO: queue up a multisig transaction to adjust the permissions of the group account based on the election results

        $this->date_certified = time();
        $this->save();
    }

}
