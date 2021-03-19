<?php
require_once __DIR__ . '/vendor/autoload.php';
$chainId      = '21dcae42c0182200e93f954a074011f9048a7624c6fe81d3c9541a614a88bd1c';
$nodeUrl      = 'https://fio.greymass.com';
$explorer_url = "https://fio.bloks.io/";
$use_testnet  = true;
if ($use_testnet) {
    $chainId = 'b20901380af44ef59c5918439a1f9a41d83669020319a80574b804a5f95cbd7e';
    //$nodeUrl = 'https://testnet.fioprotocol.io';
    $nodeUrl      = 'https://testnet.fio.eosdetroit.io';
    $explorer_url = "https://fio-test.bloks.io/";
}
$client = new GuzzleHttp\Client(['base_uri' => $nodeUrl]);
include "header.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <meta name="description" content="<?php print $description;?>">
  <meta name="author" content="Luke Stokes">
  <link rel="icon" href="favicon.ico" type="image/x-icon" />

  <!-- Facebook -->
  <!--<meta property="og:url"           content="<?php print $url;?>" />-->
  <meta property="og:type"          content="website" />
  <meta property="og:title"         content="<?php print $title;?>" />
  <meta property="og:description"   content="<?php print $description;?>" />
  <!--<meta property="og:image"         content="<?php print $image;?>" />-->

  <!-- Twitter -->
  <meta name="twitter:creator" content="@lukestokes">
  <meta name="twitter:title" content="<?php print $title;?>">
  <meta name="twitter:description" content="<?php print $description;?>">
  <!--<meta name="twitter:image" content="<?php print $image;?>">-->

  <!-- Bootstrap core CSS -->
  <link href="css/bootstrap.min.css" rel="stylesheet">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>

  <!-- Custom styles for this template -->
  <link href="css/simple-sidebar.css" rel="stylesheet">

  <title><?php print $title;?></title>
</head>

<body onload="restoreSession()">

  <div class="d-flex" id="wrapper">

    <!-- Sidebar -->
    <div class="bg-light border-right" id="sidebar-wrapper">
      <div class="sidebar-heading">FIO Groups<br />
        <?php
if ($domain != "") {
    ?>
        <a href="<?php print $explorer_url;?>account/<?php print $Group->group_account;?>" target="_blank" class="list-group-item list-group-item-action bg-light"><?php print $domain;?></a>
        <?php
}
?>
      </div>
      <div class="list-group list-group-flush">
        <a href="?action=home" class="list-group-item list-group-item-action bg-light">All Groups</a>
        <?php
if ($domain != "") {
    ?>
        <a href="?domain=<?php print $domain;?>&show=" class="list-group-item list-group-item-action bg-light">Group Home</a>
        <a href="?domain=<?php print $domain;?>&show=apply_for_membership" class="list-group-item list-group-item-action bg-light">Apply For Membership</a>
        <a href="?domain=<?php print $domain;?>&show=pending_members" class="list-group-item list-group-item-action bg-light">Pending Members</a>
        <a href="?domain=<?php print $domain;?>&show=members" class="list-group-item list-group-item-action bg-light">Members</a>
        <a href="?domain=<?php print $domain;?>&show=disabled_members" class="list-group-item list-group-item-action bg-light">Disabled Members</a>
        <a href="?domain=<?php print $domain;?>&show=inactive_members" class="list-group-item list-group-item-action bg-light">Inactive Members</a>
        <a href="?domain=<?php print $domain;?>&show=admins" class="list-group-item list-group-item-action bg-light">Admins</a>
        <a href="?domain=<?php print $domain;?>&show=elections" class="list-group-item list-group-item-action bg-light">Elections</a>
        <?php
}
?>
      </div>
    </div>
    <!-- /#sidebar-wrapper -->

    <!-- Page Content -->
    <div id="page-content-wrapper">
      <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
        <!--<button class="btn btn-primary" id="menu-toggle">Toggle Menu</button>-->

        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <?php
if ($use_testnet) {
    print "<h3 style='color:red; margin-left: 10px; padding-top: 5px;'>TESTNET</h3>";
}
if (isset($_SESSION["username"])) {
    print "<h3 style='margin-left: 10px; padding-top: 5px;'>Logged In: " . $_SESSION["username"] . "</h3>";
}
?>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <ul class="navbar-nav ml-auto mt-2 mt-lg-0">
            <li class="nav-item">
              <a class="nav-link" href="?action=home">Home</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#" onclick="login(); return false;">Login</a>
            </li>
            <li class="nav-item">
              <?php
$explorer_link = $explorer_url;
if (isset($_SESSION["username"])) {
    $explorer_link .= "account/" . $_SESSION["username"];
}
?>
              <a class="nav-link" href="<?php print $explorer_link;?>" target="_blank">Block Explorer</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="https://greymass.com/en/anchor/" target="_blank">Get Anchor Wallet</a>
            </li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                Actions
              </a>
              <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                <?php
if (isset($_SESSION['fio_balance'])) {
    ?>
                <div class="dropdown-item"><?php print number_format($_SESSION['fio_balance'], 3);?> FIO</div>
                <?php
}
?>
                <a class="dropdown-item" href="?action=show_create_group">Create Group</a>
                <a class="dropdown-item" href="?action=logout">Logout</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="https://fioprotocol.io" target="_blank">What is FIO?</a>
                <a class="dropdown-item" href="https://fioprotocol.atlassian.net/wiki/spaces/FC/pages/62423205/FIO+Groups" target="_blank">What is a FIO Group?</a>
                <a class="dropdown-item" href="https://github.com/lukestokes/fiogroups" target="_blank">Is this Open Source?</a>
                <a class="dropdown-item" href="https://fioprotocol.io/free-fio-addresses/" target="_blank">Can I get a free FIO Address?</a>
                <a class="dropdown-item" href="https://robbiepoe.medium.com/fio-is-great-you-should-get-some-3bb966aa1c2" target="_blank">Where do I get FIO Tokens?</a>
              </div>
            </li>
          </ul>
        </div>
      </nav>

      <div class="container-fluid">
          <?php
if ($action == "finish_login") {
    print "<h2>Welcome to FIO Groups! Please join or create a group.</h2>";
}

?>
          <span id="feedback"></span><br />
          <?php

if ($domain != "") {

    if ($show == "") {
        ?>
        <h1>Welcome to <?php print $domain;?>!</h1>
        <?php
$Group->print();
    }

    if ($show == "admins") {
        ?>
              <h2>Admins:</h2>
              <table class="table table-striped table-bordered">
              <?php
$admins = $Group->getAdmins();
        if (count($admins)) {
            $admins[0]->print('table_header');
        }
        $admin_accounts_string = '[';
        foreach ($admins as $Admin) {
            $admin_accounts_string .= "'" . $Admin->account . "',";
            $Admin->print('table');
        }
        $admin_accounts_string = trim($admin_accounts_string, ',');
        $admin_accounts_string .= ']';
        ?>
              </table>
              <?php
$last_certified_election_vote_threshold = 1;
        try {
            $Election                               = $Group->getLastCertifiedElection();
            $last_certified_election_vote_threshold = $Election->vote_threshold;
        } catch (Exception $e) {}
        ?>
              <div class="form-group">
                <button type="submit" class="btn btn-primary" onclick="verifyOwners('<?php print $domain;?>',<?php print $admin_accounts_string;?>,<?php print $last_certified_election_vote_threshold;?>); return false;">Verify</button>
              </div>

              <?php
$admincandidates = $Group->getAdminCandidates();
        if (count($admincandidates)) {
            ?>
                <h2>Admin Candidates:</h2>
                <table class="table table-striped table-bordered">
                <?php
$admincandidates[0]->print('table_header');
            foreach ($admincandidates as $AdminCandidate) {
                $controls = array();
                if ($Group->hasActiveElection()) {
                    $controls = array(
                        'account' => '$account [<a href="?show=admins&action=vote&domain=$domain&candidate_account=$account">Vote</a>]',
                    );
                }
                $AdminCandidate->print('table', $controls);
            }
            ?>
                </table>
                <?php
}
    }

    if ($show == "elections") {
        ?>
              <p>
                [<a href="?show=elections&action=testing_change_vote_date&domain=<?php print $domain;?>">TESTING: Change Election Vote Date</a>]<br />
              </p>

              <h2>All Elections:</h2>
              <?php

        // TODO: work with multiple elections, not ust the current one, to show historical data

        $Elections     = $Factory->new("Election");
        $criteria      = ["domain", "=", $domain];
        $all_elections = $Elections->readAll($criteria);
        if (count($all_elections)) {
            ?>
                <table class="table table-striped table-bordered">
                <?php
$all_elections[0]->print('table_header');
            foreach ($all_elections as $Election) {
                $Election->print('table');
            }
            ?>
                </table>

              <h2>Current Election:</h2>

                <?php
}
        $show_create_election = false;
        $show_vote_results    = false;
        $election_form_values = array();
        try {
            $Election = $Group->getCurrentElection();
            if ($Election->is_complete) {
                $show_create_election = true;
                $show_vote_results    = true;
                $election_form_values = array(
                    'number_of_admins' => $Election->number_of_admins,
                    'vote_threshold'   => $Election->vote_threshold,
                    'votes_per_member' => $Election->votes_per_member,
                );
            }
            ?>
                <table class="table table-striped table-bordered">
                <?php
$Election->print('table_header');
            $controls = array(
                'vote_date' => '$vote_date<br /><a href="?show=elections&action=show_votes&domain=$domain" class="btn btn-secondary btn-sm">Show Votes</a>',
            );
            if (!$Election->is_complete && $Election->vote_date < time()) {
                $controls['vote_date'] .= '<br /><a href="?show=elections&action=record_vote_results&domain=$domain" class="btn btn-secondary btn-sm">Record Vote Results</a>';
            }
            if ($Election->is_complete) {
                if (is_null($Election->date_certified)) {
                    if ($Election->results_proposal_name == "") {
                        $vote_results = $Election->getVoteResults();
                        if ($Election->number_of_admins > count($vote_results)) {
                            $Election->date_certified = time();
                            $Election->save();
                            $Group->epoch++;
                            $Group->save();
                        } else {
                            $new_admin_string = '[';
                            foreach ($vote_results as $VoteResult) {
                                $new_admin_string .= "'" . $VoteResult->candidate_account . "',";
                            }
                            $new_admin_string = trim($new_admin_string, ",");
                            $new_admin_string .= ']';
                            $controls['vote_date'] .= '<br /><a href="#" class="btn btn-secondary btn-sm" onclick="completeElection(\'$domain\',' . $new_admin_string . ',' . $Election->vote_threshold . '); return false;">Complete Election</a>';
                        }
                    } else {
                        // TODO: don't show viewmsig if there is no msig (maybe it tried and failed or something)
                        $controls['vote_date'] .= '<br /><a href="?show=elections&action=certify_vote_results&domain=$domain" class="btn btn-secondary btn-sm">Certify Vote Results</a><br /><a href="' . $explorer_url . 'msig/$results_proposer/$results_proposal_name" target="_blank" class="btn btn-secondary btn-sm">View Msig</a>';
                    }
                } else {
                    $controls['vote_date'] .= '<br /><a href="?show=elections&action=show_vote_results&domain=$domain" class="btn btn-secondary btn-sm">Show Vote Results</a>';
                }
            }
            $Election->print('table', $controls);
            ?>
                </table>

                <form id="election" method="POST">
                  <input type="hidden" id="action" name="action" value="complete_election" />
                  <input type="hidden" id="domain" name="domain" value="<?php print $domain;?>" />
                  <input type="hidden" id="results_proposer" name="results_proposer" value="" />
                  <input type="hidden" id="results_proposal_name" name="results_proposal_name" value="" />
                </form>

                <?php
if ($show_vote_results || $action == "show_vote_results") {
                // TODO: sort by rank desc
                $VoteResult   = $Factory->new("VoteResult");
                $criteria     = [["domain", "=", $domain], ["epoch", "=", $Election->epoch]];
                $vote_results = $VoteResult->readAll($criteria);
                if (count($vote_results)) {
                    ?>
                    <h3>Vote Results</h3>
                    <table class="table table-striped table-bordered">
                    <?php
$vote_results[0]->print('table_header');
                    foreach ($vote_results as $VoteResult) {
                        $VoteResult->print('table');
                    }
                    ?>
                    </table>
                    <?php
}
            }
            if ($action == "show_votes") {
                $Vote     = $Factory->new("Vote");
                $criteria = [["domain", "=", $domain], ["epoch", "=", $Election->epoch]];
                $votes    = $Vote->readAll($criteria);
                if (count($votes)) {
                    ?>
                    <h3>Votes</h3>
                    <table class="table table-striped table-bordered">
                    <?php
$votes[0]->print('table_header');
                    foreach ($votes as $Vote) {
                        $controls = array();
                        if ($logged_in_user == $Vote->voter_account) {
                            $controls = array('voter_account' => '$voter_account<br /><a href="?show=elections&action=remove_vote&domain=$domain&candidate_account=$candidate_account" class="btn btn-secondary btn-sm">Remove Vote</a>');
                        }
                        $Vote->print('table', $controls);
                    }
                    ?>
                    </table>
                    <?php
}
            }

            // NOTE: this is duplicated above as well.
            $admincandidates = $Group->getAdminCandidates();
            if (count($admincandidates)) {
                ?>
                    <h2>Admin Candidates:</h2>
                    <table class="table table-striped table-bordered">
                    <?php
$admincandidates[0]->print('table_header');
                foreach ($admincandidates as $AdminCandidate) {
                    $controls = array();
                    if ($Group->hasActiveElection()) {
                        $controls = array(
                            'account' => '$account [<a href="?show=elections&action=vote&domain=$domain&candidate_account=$account">Vote</a>]',
                        );
                    }
                    $AdminCandidate->print('table', $controls);
                }
                ?>
                    </table>
                    <?php
}

        } catch (Exception $e) {
            $show_create_election = true;
        }
        if ($show_create_election) {
            if (!isset($_SESSION["username"])) {
                print "<p>To create an election, please login with <a href=\"https://greymass.com/en/anchor/\">Anchor Wallet</a> first.";
            } else {
                ?>

                  <h2>Create Election:</h2>

                  <form method="POST" id="create_election">
                    <input type="hidden" name="action" value="create_election">
                    <input type="hidden" name="domain" value="<?php print $domain;?>">
                      <?php
$Election = $Factory->new("Election");
                print $Election->formHTML($election_form_values);
                $vote_date = date("Y-m-d", time() + (30 * 24 * 60 * 60));
                ?>
                    <div class="col-sm-2">
                        <div class="form-group">
                            <label>Vote Date</label>
                            <input type="text" name="vote_date" id="vote_date" class="form-control" value="<?php print $vote_date;?>" placeholder="YYYY-mm-dd">
                        </div>
                    </div>
                    <div class="form-group">
                      <button type="submit" class="btn btn-primary">Create Election</button>
                    </div>
                  </form>
                  <?php
}
        }
    }

    if ($show == "inactive_members") {
        ?>
              <h2>Inactive Members:</h2>
              <?php

        $inactive_members = $Group->getInactiveMembers();
        if (count($inactive_members)) {
            ?>
                <table id="inactive_members" class="table table-striped table-bordered">
                <?php
$inactive_members[0]->print('table_header');
            foreach ($inactive_members as $InactiveMember) {
                $controls = array(
                    'account' => '$account<br /><a href="?show=inactive_members&action=activate_member&domain=$domain&account=$account" class="btn btn-secondary btn-sm">Activate</a>',
                );
                if ($is_admin) {
                    $controls['account'] .= '<br /><a href="?show=disabled_member&action=disable_member&domain=$domain&account=$account" class="btn btn-secondary btn-sm">Disable</a>';
                }
                $InactiveMember->print('table', $controls);
            }
            ?>
                </table>
                <?php
} else {
            print "<p>There are currently no inactive members</p>";
        }

    }

    if ($show == "disabled_members") {
        ?>
              <h2>Disabled Members:</h2>
              <?php

        $disabled_members = $Group->getDisabledMembers();
        if (count($disabled_members)) {
            ?>
                <table id="disabled_members" class="table table-striped table-bordered">
                <?php
$disabled_members[0]->print('table_header');
            foreach ($disabled_members as $DisabledMember) {
                $controls = array();
                if ($is_admin) {
                    $controls = array(
                        'account' => '$account<br /><a href="?show=disabled_members&action=enable_member&domain=$domain&account=$account" class="btn btn-secondary btn-sm">Enable</a>',
                    );
                }
                $DisabledMember->print('table', $controls);
            }
            ?>
                </table>
                <?php
} else {
            print "<p>There are currently no disabled members</p>";
        }

    }

    if ($show == "members") {
        ?>
              <h2>Members:</h2>
              <?php

        $members = $Group->getMembers();
        if (count($members)) {
            ?>
                <table id="members" class="table table-striped table-bordered">
                <?php
$members[0]->print('table_header');
            foreach ($members as $Member) {
                $controls = array(
                    'account'     => '$account',
                    'member_name' => '<a href="' . $explorer_url . 'address/$member_name@$domain" target="_blank">$member_name</a>',
                );
                if ($logged_in_user == $Member->account) {
                    $controls['account'] .= '<br /><a href="?show=inactive_members&action=deactivate_member&domain=$domain&account=$account" class="btn btn-secondary btn-sm">Deactivate</a>';
                    $AdminCandidate     = $Factory->new("AdminCandidate");
                    $is_admin_candidate = $AdminCandidate->readAll([["domain", "=", $domain], ["account", "=", $Member->account]]);
                    if (!$is_admin_candidate) {
                        $controls['account'] .= '<br /><a href="?show=admins&action=register_candidate&domain=$domain&account=$account" class="btn btn-secondary btn-sm">Register Candidate</a>';
                    }
                }
                if ($is_admin) {
                    $controls['account'] .= '<br /><a href="?show=disabled_members&action=disable_member&domain=$domain&account=$account" class="btn btn-secondary btn-sm">Disable</a>';
                }
                $Member->print('table', $controls);
            }
            ?>
                </table>
                <?php
} else {
            print "<p>There are currently no members</p>";
        }
    }

    if ($show == "pending_members") {
        ?>
              <h2>Pending Members:</h2>
              <?php

        $pendingmembers = $Group->getPendingMembers();
        if (count($pendingmembers)) {
            ?>
                <table id="pending_members" class="table table-striped table-bordered">
                <?php
$pendingmembers[0]->print('table_header');
            foreach ($pendingmembers as $PendingMember) {
                // TODO: change this to be a javascript form POST, not a get. Protect against CSRF.

                // TODO: check not only that the msig isn't pending, but that the FIO address was assigned.
                $controls = array(
                    'application_date' => '<a href="' . $explorer_url . 'transaction/$membership_payment_transaction_id" target="_blank">$application_date</a>',
                );
                if ($is_admin) {
                    $controls['account'] = '$account<br /><a href="?show=pending_members&action=approve_pending_member&domain=$domain&account=$account&membership_proposal_name=$membership_proposal_name" class="btn btn-secondary btn-sm">Approve</a><br /><a href="' . $explorer_url . 'msig/$account/$membership_proposal_name" target="_blank" class="btn btn-secondary btn-sm">View Msig</a>';
                }
                $PendingMember->print('table', $controls);
            }
            ?>
                </table>
                <?php
} else {
            print "<p>There are currently no pending members</p>";
        }

    }

    if ($show == "apply_for_membership") {

        if (!isset($_SESSION["username"])) {
            print "<p>Please login with <a href=\"https://greymass.com/en/anchor/\">Anchor Wallet</a> first.";
        } else {
            ?>
                <h1>Apply</h1>
                <form method="POST" id="apply_to_group">
                  <input type="hidden" name="action" value="apply_to_group">
                  <input type="hidden" id="domain" name="domain" value="<?php print $domain;?>">
                  <input type="hidden" id="group_fio_public_key" name="group_fio_public_key" value="<?php print $Group->group_fio_public_key;?>">
                  <input type="hidden" id="group_account" name="group_account" value="<?php print $Group->group_account;?>">
                  <input type="hidden" id="member_application_fee" name="member_application_fee" value="<?php print $Group->member_application_fee;?>">
                  <input type="hidden" id="membership_payment_transaction_id" name="membership_payment_transaction_id" value="">
                  <input type="hidden" id="membership_proposal_name" name="membership_proposal_name" value="">
                    <?php
/*
            $PendingMember = $Factory->new("PendingMember");
            print $PendingMember->formHTML();
             */
            ?>
                  <div class="col-sm-2">
                      <div class="form-group">
                          <label>Member Name Requested (@<?php print $domain;?>)</label>
                          <input type="text" name="member_name_requested" id="member_name_requested" class="form-control" value="">
                      </div>
                  </div>
                  <div class="col-sm-2">
                      <div class="form-group">
                        <label for="bio">Biography (tell us about yourself)</label>
                        <textarea class="form-control" name="bio" id="bio" rows="3"></textarea>
                      </div>
                  </div>
                  <div class="form-group">
                    <button type="button" id="apply_to_group_button" class="btn btn-primary">Apply for Membership</button>
                  </div>
                </form>
                <?php
}
    }
}

if ($domain == "") {

    if ($action == "show_create_group") {
        if (!isset($_SESSION["username"])) {
            print "<p>Please login with <a href=\"https://greymass.com/en/anchor/\">Anchor Wallet</a> first.";
        } else {
            ?>
                  <h1>Create Group</h1>
                  <h2>Please read this carefully.</h2>
                  <p>You will be asked to sign two transactions.<br/>
                  <strong>The first transaction will</strong>:</p>
                    <ol>
                      <li>Register your group FIO Domain.</li>
                      <li>Register your FIO Name as the first FIO Address at your group domain.</li>
                      <li>Send 10 FIO to a new group account.</li>
                    </ol>
                  <p>
                    At this point the permissions on the new group account will be updated so that you are the admin.<br />
                    You will then be asked to <strong>sign another transaction</strong> to transfer the domain to the new group.<br />
                    After this is completed, your group will be saved into the sytem.
                  </p>
                  <p>
                    Register Domain Fee: <span id="register_domain_fee"><?php print $Util->SUFToFIO($Util->getRegisterDomainFee());?> FIO</span><br />
                    Register Address Fee: <span id="register_address_fee"><?php print $Util->SUFToFIO($Util->getRegisterAddressFee());?> FIO</span><br />
                    Transfer Token Fee: <span id="transfer_tokens_fee"><?php print $Util->SUFToFIO($Util->getTransferFee());?> FIO</span><br />
                    Transfer Domain Fee: <span id="transfer_domain_fee"><?php print $Util->SUFToFIO($Util->getTransferDomainFee());?> FIO</span><br />
                  </p>
                  <p>
                    Leave the Group Fio Public Key and Group Account fields blank as they were be filled in automatically for you.
                  </p>
                  <form method="POST" id="create_group">
                    <input type="hidden" name="action" value="create_group">
                    <div class="col-sm-2">
                        <div class="form-group">
                            <label>Member Name</label>
                            <input type="text" name="creator_member_name" id="creator_member_name" class="form-control" value="">
                        </div>
                    </div>
                    <?php
$Group = $Factory->new("Group");
            print $Group->formHTML();
            ?>
                    <div class="form-group">
                      <button type="button" id="create_group_button" class="btn btn-primary">Create Group</button>
                    </div>
                  </form>
                <?php
}
    }

    if ($action == "" || $action == "home") {
        ?>
                <div class="form-group">
                  <a href="?action=show_create_group" class="btn btn-primary">Create Group</a>
                </div>
              <?php
$Group    = $Factory->new("Group");
        $criteria = ['domain', '!=', ''];
        if ($domain != "") {
            $criteria = ['domain', '=', $domain];
        }
        $groups = $Group->readAll($criteria);
        if (count($groups)) {
            ?>
                <h1>Groups:</h1>
                <table id="groups" class="table table-striped table-bordered">
                <?php
$groups[0]->print('table_header');
            foreach ($groups as $Group) {
                $controls = array(
                    'domain'        => '<a href="?domain=$domain">$domain</a>',
                    'group_account' => '<a href="' . $explorer_url . 'account/$group_account" target="_blank">$group_account</a>',
                );
                $Group->print('table', $controls);
            }
            ?>
                </table>
                <?php
}
    }
}
?>

          <form id="login" method="POST">
            <input id="identity_proof" name="identity_proof" value="" type="hidden">
            <input id="actor" name="actor" value="" type="hidden">
            <input id="action" name="action" value="finish_login" type="hidden">
          </form>

<!-- Footer -->
<footer class="bg-light text-center text-lg-start">
  <!-- Grid container -->
  <div class="container p-4">
    <!--Grid row-->
    <div class="row">
      <!--Grid column-->
      <div class="col-lg-6 col-md-12 mb-4 mb-md-0">
        <h5 class="text-uppercase">FIO Groups Disclaimer</h5>

        <p>
          Use at your own risk. The author of this website and code takes no responsibility for anything here. Your data may dissappear as this is a test project for experimentation. Please <a href="https://github.com/lukestokes/fiogroups">fork the code</a> and run it yourself if you plan to use for anything important. If you have questions, suggestions, or ideas, please <a href="https://github.com/lukestokes/fiogroups/issues">submit an issue on Github</a>.
        </p>
      </div>
      <!--Grid column-->

      <!--Grid column-->
      <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
        <h5 class="text-uppercase">DACs</h5>

        <ul class="list-unstyled mb-0">
          <li>
            <a href="http://eosdac.io/" class="text-dark">eosDAC</a>
          </li>
          <li>
            <a href="https://dacfactory.io/" class="text-dark">DAC Factory</a>
          </li>
          <li>
            <a href="http://daclify.com/" class="text-dark">DAClify</a>
          </li>
          <li>
            <a href="https://www.krown.club/" class="text-dark">KROWN</a>
          </li>
          <li>
            <a href="https://joinseeds.com/" class="text-dark">Join SEEDs</a>
          </li>
        </ul>
      </div>
      <!--Grid column-->

      <!--Grid column-->
      <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
        <h5 class="text-uppercase">FIO</h5>

        <ul class="list-unstyled">
          <li>
            <a href="http://fioprotocol.io/" class="text-dark">FIO Protocol</a>
          </li>
          <li>
            <a href="https://fioprotocol.io/free-fio-addresses/" class="text-dark">Free FIO Address</a>
          </li>
          <li>
            <a href="http://kb.fioprotocol.io/" class="text-dark">FIO Knowledge Base</a>
          </li>
          <li>
            <a href="https://developers.fioprotocol.io/" class="text-dark">FIO Developer Hub</a>
          </li>
          <li>
            <a href="https://fioprotocol.atlassian.net/wiki/spaces/FC/overview?mode=global" class="text-dark">FIO Community</a>
          </li>
        </ul>
      </div>
      <!--Grid column-->
    </div>
    <!--Grid row-->
  </div>
  <!-- Grid container -->

  <!-- Copyright -->
  <div class="text-center p-3" style="background-color: rgba(0, 0, 0, 0.2);">
    DAC All The Things. <a class="text-dark" href="https://twitter.com/lukestokes">Luke on Twitter</a>
  </div>
  <!-- Copyright -->
</footer>
<!-- Footer -->


      </div>
      <!-- /#container-fluid -->

    </div>
    <!-- /#page-content-wrapper -->
  </div>
  <!-- /#wrapper -->

  <!-- Bootstrap core JavaScript -->
  <script src="js/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script>
  var nodeUrl = '<?php print $nodeUrl;?>';
  var explorer_url = '<?php print $explorer_url;?>';
  <?php if ($action == 'login') {?>
    $(function() {
      login();
    });
  <?php }?>
  <?php if ($action == 'logout') {?>
    $(function() {
      logout();
    });
  <?php }?>
  </script>
  <script src="https://unpkg.com/anchor-link@3"></script>
  <script src="https://unpkg.com/anchor-link-browser-transport@3"></script>
  <script src="js/long.js"></script>
  <!-- Bootstrap Date-Picker Plugin -->
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
  <!-- Menu Toggle Script -->
  <script>
    $("#menu-toggle").click(function(e) {
      e.preventDefault();
      $("#wrapper").toggleClass("toggled");
    });
  </script>
  <script src="js/script.js"></script>
  <script>
  // app identifier, should be set to the eosio contract account if applicable
  const identifier = 'fiogroups'
  // initialize the browser transport
  const transport = new AnchorLinkBrowserTransport()
  // initialize the link
  const link = new AnchorLink(
      {
        transport,
        chains: [
            {
                chainId: '<?php print $chainId;?>',
                nodeUrl: '<?php print $nodeUrl;?>',
            }
        ],
      }
    );
  // the session instance, either restored using link.restoreSession() or created with link.login()
  let session
  </script>
</body>
</html>