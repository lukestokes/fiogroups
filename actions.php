<?php

/** FOR TESTING **/
if ($action == "testing_change_vote_date") {
    try {
        $Election            = $Group->getCurrentElection();
        $Election->vote_date = time() - 1000;
        $Election->save();
        $notice .= "TESTING: Election vote date set in the past.";
    } catch (Exception $e) {}
}
/*
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
$notice .= $e->getMessage();
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
$notice .= $e->getMessage();
}
}
 */
/** FOR TESTING **/

if ($action == "logout") {
    // Unset all of the session variables.
    $_SESSION = array();
    // If it's desired to kill the session, also delete the session cookie.
    // Note: This will destroy the session, and not just the session data!
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    // Finally, destroy the session.
    session_destroy();
    header("Location: index.php");
}

if ($action == "finish_login") {
    $proof = json_decode($_REQUEST["identity_proof"], true);
    try {
        $identity_response = $client->post('https://eosio.greymass.com/prove', [
            GuzzleHttp\RequestOptions::JSON => ['proof' => $proof], // or 'json' => [...]
        ]);
        $identity_results          = json_decode($identity_response->getBody(), true);
        $logged_in_user            = $identity_results["account_name"];
        $_SESSION['username']      = $identity_results["account_name"];
        $Util->actor               = $_SESSION['username'];
        $fio_balance               = $Util->getFIOBalance();
        $_SESSION['fio_balance']   = $fio_balance;
        $_SESSION['member_status'] = array();
        // are they a member of any Groups?
        $Member   = $Factory->new("Member");
        $criteria = ["account", "=", $logged_in_user];
        if ($domain != "") {
            $criteria = [["domain", "=", $domain], ["account", "=", $logged_in_user]];
        }
        $members = $Member->readAll($criteria);
        if (count($members)) {
            $Member = $members[0];
            /*
            $fio_addresses = $Util->getFIOAddresses();
            $Members   = $Factory->new("Member");
            $criteria = ["account", "=", $logged_in_user];
            $memberships = $Members->readAll($criteria);
            $group_domains = array();
            foreach ($fio_addresses as $key => $fio_address) {
            foreach ($memberships as $key => $membership) {
            if ($fio_address['fio_address'] == ($membership->member_name . "@" . $membership->domain)) {
            $group_domains[] = $membership->domain;
            }
            }
            }
            $member_of = array();
            foreach ($group_domains as $key => $group_domain) {
            $membership = array(
            'domain' => $group_domain,
            'domain_account' => '',
            'is_admin' => false,
            );
            $membership['domain_account'] = $Util->getDomainOwner($group_domain);
            $accounts = $Util->getAccountPermissions($membership['domain_account']);
            if (in_array($logged_in_user, $accounts)) {
            $membership['is_admin'] = true;
            }
            $member_of[] = $membership;
            }
            $_SESSION['member_of'] = $member_of;
             */

            // check each domain and see if they are an admin

            // TODO: check this against onchain data of the permissions of the group

            $membership                                 = $Util->getMemberStatusOfDomain($Member->domain, $Member->member_name . "@" . $Member->domain);
            $_SESSION['member_status'][$Member->domain] = $membership;
            $is_admin                                   = $Member->is_admin && $membership['is_admin'];
            $Member->last_login_date                    = time();
            $Member->save();
        }
    } catch (Exception $e) {
        $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '<br />Pleae login again.</div>';
    }
}

if ($domain != "") {
    if (!isset($_SESSION['member_status'])) {
        $_SESSION['member_status'] = array();
    }
    $Member   = $Factory->new("Member");
    $criteria = [["domain", "=", $domain], ["account", "=", $logged_in_user]];
    $found    = $Member->read($criteria);
    if ($found) {
        if (!array_key_exists($Member->domain, $_SESSION['member_status'])) {
            $_SESSION['member_status'][$Member->domain] = $Util->getMemberStatusOfDomain(
                $Member->domain,
                $Member->member_name . "@" . $Member->domain
            );
        }
        if ($Util->isMemberOnChain($_SESSION, $domain)) {
            $is_admin = $Member->is_admin && $Util->isAdminOnChain($_SESSION, $domain);
        }
    } else {
        if (!array_key_exists($domain, $_SESSION['member_status'])) {
            $_SESSION['member_status'][$domain] = $Util->getMemberStatusOfDomain($domain);
        }
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
        $Util->balance           = null;
        $fio_balance             = $Util->getFIOBalance();
        $_SESSION['fio_balance'] = $fio_balance;

        $_SESSION['member_status'][$domain]['fio_address']         = strip_tags($_POST["creator_member_name"]) . '@' . strip_tags($_POST["domain"]);
        $_SESSION['member_status'][$domain]['owns_member_address'] = true;

    } catch (Exception $e) {
        $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '</div>';
    }
}

if ($action == "apply_to_group") {
    try {
        $Group->apply(
            strip_tags($logged_in_user),
            strip_tags($_POST["member_name_requested"]),
            strip_tags($_POST["bio"]),
            strip_tags($_POST["membership_payment_transaction_id"]),
            strip_tags($_POST["membership_proposal_name"])
        );
    } catch (Exception $e) {
        $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '</div>';
    }
}

if ($Util->isMemberOnChain($_SESSION, $domain)) {

    if ($action == "approve_pending_member") {
        if ($Util->isAdminOnChain($_SESSION, $domain)) {
            $results = $Util->getPendingMsig(strip_tags($_REQUEST["account"]), strip_tags($_REQUEST["membership_proposal_name"]));
            if ($results
                && count($results['rows']) > 0
                && $results['rows'][0]['proposal_name'] == strip_tags($_REQUEST["membership_proposal_name"])
            ) {
                $notice .= '<div class="alert alert-danger" role="alert">Please ensure the pending proposal <a href="' . $explorer_url . 'msig/' . strip_tags($_REQUEST["account"]) . '/' . strip_tags($_REQUEST["membership_proposal_name"]) . '" target="_blank">' . strip_tags($_REQUEST["membership_proposal_name"]) . '</a> is approved and executed before approving this member.</div>';
            } else {
                try {
                    $Group->approve(
                        strip_tags($_REQUEST["account"])
                    );
                } catch (Exception $e) {
                    $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '</div>';
                }
            }
        } else {
            $notice .= '<div class="alert alert-danger" role="alert">You are not an admin of ' . $domain . '</div>';
        }
    }

    if ($action == "create_election") {
        try {
            $vote_date = strtotime(strip_tags($_REQUEST["vote_date"]));
            $Group->createElection(
                strip_tags($_REQUEST["number_of_admins"]),
                strip_tags($_REQUEST["vote_threshold"]),
                strip_tags($_REQUEST["votes_per_member"]),
                $vote_date
            );
        } catch (Exception $e) {
            $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '</div>';
        }
    }

    if ($action == "register_candidate") {
        if ($_SESSION['username'] == $_REQUEST["account"]) {
            try {
                $Group->registerCandidate(
                    strip_tags($_REQUEST["account"])
                );
            } catch (Exception $e) {
                $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '</div>';
            }
        } else {
            $notice .= '<div class="alert alert-danger" role="alert">You can only do this for your own account.</div>';
        }
    }

    if ($action == "disable_member") {
        if ($Util->isAdminOnChain($_SESSION, $domain)) {
            try {
                $Group->disable(
                    strip_tags($_REQUEST["account"])
                );
            } catch (Exception $e) {
                $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '</div>';
            }
        } else {
            $notice .= '<div class="alert alert-danger" role="alert">You are not an admin of ' . $domain . '</div>';
        }
    }

    if ($action == "enable_member") {
        if ($Util->isAdminOnChain($_SESSION, $domain)) {
            try {
                $Group->enable(
                    strip_tags($_REQUEST["account"])
                );
            } catch (Exception $e) {
                $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '</div>';
            }
        } else {
            $notice .= '<div class="alert alert-danger" role="alert">You are not an admin of ' . $domain . '</div>';
        }
    }

    if ($action == "deactivate_member") {
        if ($_SESSION['username'] == $_REQUEST["account"]) {
            try {
                $Group->deactivate(
                    strip_tags($_REQUEST["account"])
                );
            } catch (Exception $e) {
                $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '</div>';
            }
        } else {
            $notice .= '<div class="alert alert-danger" role="alert">You can only do this for your own account.</div>';
        }
    }

    if ($action == "activate_member") {
        if ($_SESSION['username'] == $_REQUEST["account"]) {
            try {
                $Group->activate(
                    strip_tags($_REQUEST["account"])
                );
            } catch (Exception $e) {
                $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '</div>';
            }
        } else {
            $notice .= '<div class="alert alert-danger" role="alert">You can only do this for your own account.</div>';
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
            $notice .= "Vote cast for " . strip_tags($_REQUEST["candidate_account"]) . " by " . $logged_in_user . ".";
        } catch (Exception $e) {
            $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '</div>';
        }
    }
    if ($action == "remove_vote") {
        try {
            $Group->removeVote(
                strip_tags($logged_in_user),
                strip_tags($_REQUEST["candidate_account"])
            );
            $action = "show_votes";
        } catch (Exception $e) {
            $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '</div>';
        }
    }

    if ($action == "record_vote_results") {
        try {
            $Group->recordVoteResults();
            $notice .= 'Votes Recorded. Please complete the election.';
        } catch (Exception $e) {
            $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '</div>';
        }
    }

    if ($action == "complete_election") {
        try {
            $Election                        = $Group->getCurrentElection();
            $Election->results_proposer      = strip_tags($_REQUEST["results_proposer"]);
            $Election->results_proposal_name = strip_tags($_REQUEST["results_proposal_name"]);
            $Election->save();
            $notice .= 'Election complete. Please certify the voting results by asking the admins to approve the msig.';
        } catch (Exception $e) {
            $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '</div>';
        }
    }

    if ($action == "certify_vote_results") {
        try {
            $Group->certifyVoteResults();
        } catch (Exception $e) {
            $notice .= '<div class="alert alert-danger" role="alert">' . $e->getMessage() . '</div>';
        }
    }
}
