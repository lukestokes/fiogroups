<?php

/** FOR TESTING **/
if ($action == "testing_change_vote_date") {
    try {
        $Election            = $Group->getCurrentElection();
        $Election->vote_date = time() - 1000;
        $Election->save();
        $notice = "TESTING: Election vote date set in the past.";
    } catch (Exception $e) {}
}
if ($action == "testing_clear_all_data") {
    exec("rm -rf \"" . __DIR__ . "/data\"");
    exec("mkdir \"" . __DIR__ . "/data\"");
}
if ($action == "testing_make_admin") {
    $Member   = $Factory->new("Member");
    $criteria = [["domain", "=", $domain], ["account", "=", $logged_in_user]];
    try {
        $found = $Member->read($criteria);
        if ($found) {
            $Member->is_admin = true;
            $Member->save();
            $is_admin = true;
        }
    } catch (Exception $e) {
        $notice = $e->getMessage();
    }
}
if ($action == "testing_unmake_admin") {
    $Member   = $Factory->new("Member");
    $criteria = [["domain", "=", $domain], ["account", "=", $logged_in_user]];
    try {
        $found = $Member->read($criteria);
        if ($found) {
            $Member->is_admin = false;
            $Member->save();
            $is_admin = false;
        }
    } catch (Exception $e) {
        $notice = $e->getMessage();
    }
}
/** FOR TESTING **/

if ($action == "login") {
    $proof = json_decode($_REQUEST["identity_proof"], true);
    try {
        $identity_response = $client->post('https://eosio.greymass.com/prove', [
            GuzzleHttp\RequestOptions::JSON => ['proof' => $proof], // or 'json' => [...]
        ]);
        $identity_results = json_decode($identity_response->getBody(), true);
        $logged_in_user   = $identity_results["account_name"];
        $_SESSION['username']    = $identity_results["account_name"];
        $Util->actor = $_SESSION['username'];
        $fio_balance = $Util->getFIOBalance();
        $_SESSION['fio_balance'] = $fio_balance;
        // are they a member of any Groups?
        $Member   = $Factory->new("Member");
        $criteria = ["account", "=", $logged_in_user];
        if ($domain != "") {
            $criteria = [["domain", "=", $domain], ["account", "=", $logged_in_user]];
        }
        $members = $Member->readAll($criteria);
        if (count($members)) {
            $Member                  = $members[0];
            // TODO: check this against onchain data of the permissions of the group
            $is_admin                = $Member->is_admin;
            $_SESSION['fio_address'] = $Member->member_name . "@" . $Member->domain;
            $_SESSION['domain']      = $Member->domain;
            $Member->last_login_date = time();
            $Member->save();
        }
    } catch (Exception $e) {
        $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '<br />Pleae login again.</div>';
    }
}

if ($domain != "") {
    $Member   = $Factory->new("Member");
    $criteria = [["domain", "=", $domain], ["account", "=", $logged_in_user]];
    $found    = $Member->read($criteria);
    if ($found) {
        $is_admin = $Member->is_admin;
    }
}

if ($action == "create_group") {
    $Group = $Factory->new("Group");
    try {
        $Group->create(
            $logged_in_user,
            strip_tags($_POST["creator_member_name"]),
            strip_tags($_POST["group_fio_public_key"]),
            strip_tags($_POST["group_account"]),
            strip_tags($_POST["domain"]),
            strip_tags($_POST["member_application_fee"])
        );
        $Util->balance = null;
        $fio_balance = $Util->getFIOBalance();
        $_SESSION['fio_balance'] = $fio_balance;
    } catch (Exception $e) {
        $notice = $e->getMessage();
    }
}

if ($action == "apply_to_group") {
    try {
        $Group->apply(
            strip_tags($logged_in_user),
            strip_tags($_POST["member_name_requested"]),
            strip_tags($_POST["bio"]),
            strip_tags($_POST["membership_payment_transaction_id"]),
            strip_tags($_POST["membership_proposal_name"]),
        );
    } catch (Exception $e) {
        $notice = $e->getMessage();
    }
}

if ($action == "approve_pending_member") {
    try {
        $Group->approve(
            strip_tags($_REQUEST["account"])
        );
    } catch (Exception $e) {
        $notice = $e->getMessage();
    }
}

if ($action == "create_election") {
    try {
        $vote_date = strtotime(strip_tags($_REQUEST["vote_date"]));
        $Group->createElection(
            strip_tags($_REQUEST["number_of_admins"]),
            strip_tags($_REQUEST["vote_threshold"]),
            strip_tags($_REQUEST["votes_per_member"]),
            $vote_date,
        );
    } catch (Exception $e) {
        $notice = $e->getMessage();
    }
}

if ($action == "register_candidate") {
    try {
        $Group->registerCandidate(
            strip_tags($_REQUEST["account"])
        );
    } catch (Exception $e) {
        $notice = $e->getMessage();
    }
}

if ($action == "disable_member") {
    try {
        $Group->disable(
            strip_tags($_REQUEST["account"])
        );
    } catch (Exception $e) {
        $notice = $e->getMessage();
    }
}

if ($action == "enable_member") {
    try {
        $Group->enable(
            strip_tags($_REQUEST["account"])
        );
    } catch (Exception $e) {
        $notice = $e->getMessage();
    }
}

if ($action == "deactivate_member") {
    try {
        $Group->deactivate(
            strip_tags($_REQUEST["account"])
        );
    } catch (Exception $e) {
        $notice = $e->getMessage();
    }
}

if ($action == "activate_member") {
    try {
        $Group->activate(
            strip_tags($_REQUEST["account"])
        );
    } catch (Exception $e) {
        $notice = $e->getMessage();
    }
}

if ($action == "vote") {
    try {
        // count votes already cast and set rank that way?
        $rank = 1;
        // count tokens or something?
        $vote_weight = 1;
        $Group->vote(
            strip_tags($logged_in_user),
            strip_tags($_REQUEST["candidate_account"]),
            $rank,
            $vote_weight
        );
        $action = "show_votes";
        $notice = "Vote cast for " . strip_tags($_REQUEST["candidate_account"]) . " by " . $logged_in_user . ".";
    } catch (Exception $e) {
        $notice = $e->getMessage();
    }
}
if ($action == "remove_vote") {
    try {
        $Group->removeVote(
            strip_tags($logged_in_user),
            strip_tags($_REQUEST["candidate_account"]),
        );
        $action = "show_votes";
    } catch (Exception $e) {
        $notice = $e->getMessage();
    }
}

if ($action == "record_vote_results") {
    try {
        $Group->recordVoteResults();
    } catch (Exception $e) {
        $notice = $e->getMessage();
    }
}

if ($notice != "") {
    ?>
      <div class="alert alert-info" role="alert">
        <?php print $notice;?>
      </div>
    <?php
}
