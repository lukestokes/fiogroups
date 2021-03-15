<?php
require_once __DIR__ . '/vendor/autoload.php';
//$chainId = '21dcae42c0182200e93f954a074011f9048a7624c6fe81d3c9541a614a88bd1c';
//$nodeUrl = 'https://fio.greymass.com';
$chainId = 'b20901380af44ef59c5918439a1f9a41d83669020319a80574b804a5f95cbd7e';
$nodeUrl = 'https://testnet.fioprotocol.io';
//$nodeUrl = 'https://testnet.fio.eosdetroit.io';
//$client = new GuzzleHttp\Client(['base_uri' => 'http://fio.greymass.com']);
$client = new GuzzleHttp\Client(['base_uri' => $nodeUrl]);
//$explorer_url = "https://fio.bloks.io/";
$explorer_url = "https://fio-test.bloks.io/";
include "header.php";
?>
<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="description" content="<?php print $description; ?>">
    <meta name="author" content="Luke Stokes">
    <link rel="icon" href="favicon.ico" type="image/x-icon" />

    <!-- Facebook -->
    <!--<meta property="og:url"           content="<?php print $url; ?>" />-->
    <meta property="og:type"          content="website" />
    <meta property="og:title"         content="<?php print $title; ?>" />
    <meta property="og:description"   content="<?php print $description; ?>" />
    <!--<meta property="og:image"         content="<?php print $image; ?>" />-->

    <!-- Twitter -->
    <meta name="twitter:creator" content="@lukestokes">
    <meta name="twitter:title" content="<?php print $title; ?>">
    <meta name="twitter:description" content="<?php print $description; ?>">
    <!--<meta name="twitter:image" content="<?php print $image; ?>">-->


    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-giJF6kkoqNQ00vy+HMDP7azOuL0xtbfIcaT9wjKHr8RbDVddVHyTfAAsrekwKmP1" crossorigin="anonymous">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>

  <title><?php print $title; ?></title>
  </head>
  <body onload="restoreSession()">
    <div class="container-fluid">
      <div class="row m-3">
          <?php
          if (isset($_SESSION["username"])) {
            print "<h3>Authenticated: " . $_SESSION["username"] . " (" . $_SESSION['fio_balance'] . " FIO)</h3>";
            print '<p>[<a href="?logout">Logout</a>]</p>';
          }

          if ($domain != "") {
            ?>
            <h1><?php print $domain; ?></h1>

            <p>
              [<a href="?action=testing_change_vote_date&domain=<?php print $domain; ?>">TESTING: Change Election Vote Date</a>]<br />
              [<a href="?action=testing_clear_all_data&domain=<?php print $domain; ?>">TESTING: Clear Data</a>]<br />
              [<a href="?action=testing_make_admin&domain=<?php print $domain; ?>">TESTING: Make Admin</a>]<br />
              [<a href="?action=testing_unmake_admin&domain=<?php print $domain; ?>">TESTING: Unmake Admin</a>]<br />
            </p>

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
            $admin_accounts_string = trim($admin_accounts_string,',');
            $admin_accounts_string .= ']';
            ?>
            </table>

            <!-- fix this threshold. Need to pull from the data, the last completed election -->

            <p>[<a href="#" onclick="verifyOwners('<?php print $domain; ?>',<?php print $admin_accounts_string; ?>,1); return false;">Verify</a>]</p>

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
                    'account' => '$account [<a href="?action=vote&domain=$domain&candidate_account=$account">Vote</a>]',
                  );
                }
                $AdminCandidate->print('table',$controls);
              }
              ?>
              </table>
              <?php
            }
            ?>

            <h2>Elections:</h2>
            <?php

            // TODO: work with multiple elections, not ust the current one, to show historical data

            $show_create_election = false;
            $show_vote_results = false;
            $election_form_values = array();
            try {
              $Election = $Group->getCurrentElection();
              if ($Election->is_complete) {
                $show_create_election = true;
                $show_vote_results = true;
                $election_form_values = array(
                  'number_of_admins' => $Election->number_of_admins,
                  'vote_threshold' => $Election->vote_threshold,
                  'votes_per_member' => $Election->votes_per_member,
                );
              }
              ?>
              <table class="table table-striped table-bordered">
              <?php
              $Election->print('table_header');
              $controls = array(
                'vote_date' => '$vote_date [<a href="?action=show_votes&domain=$domain">Show Votes</a>]'
              );
              if (!$Election->is_complete && $Election->vote_date < time()) {
                $controls['vote_date'] .= ' [<a href="?action=record_vote_results&domain=$domain">Record Vote Results</a>]';
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
                      $new_admin_string = trim($new_admin_string,",");
                      $new_admin_string .= ']';
                      $controls['vote_date'] .= ' [<a href="#" onclick="completeElection(\'$domain\',' . $new_admin_string . ',' . $Election->vote_threshold . '); return false;">Complete Election</a>]';
                    }
                  } else {
                    $controls['vote_date'] .= ' [<a href="?action=certify_vote_results&domain=$domain">Certify Vote Results</a>] [<a href="' . $explorer_url . 'msig/$results_proposer/$results_proposal_name" target="_blank">View Msig</a>]';
                  }
                } else {
                  $controls['vote_date'] .= ' [<a href="?action=show_vote_results&domain=$domain">Show Vote Results</a>]';
                }
              }
              $Election->print('table',$controls);
              ?>
              </table>

              <form id="election" method="POST">
                <input type="hidden" id="action" name="action" value="complete_election" />
                <input type="hidden" id="domain" name="domain" value="<?php print $domain; ?>" />
                <input type="hidden" id="results_proposer" name="results_proposer" value="" />
                <input type="hidden" id="results_proposal_name" name="results_proposal_name" value="" />
              </form>

              <?php
              if ($show_vote_results || $action == "show_vote_results") {
                // TODO: sort by rank desc
                $VoteResult = $Factory->new("VoteResult");
                $criteria = [["domain","=",$domain],["epoch","=",$Election->epoch]];
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
                $Vote = $Factory->new("Vote");
                $criteria = [["domain","=",$domain],["epoch","=",$Election->epoch]];
                $votes = $Vote->readAll($criteria);
                if (count($votes)) {
                  ?>
                  <h3>Votes</h3>
                  <table class="table table-striped table-bordered">
                  <?php
                  $votes[0]->print('table_header');
                  foreach ($votes as $Vote) {
                    $controls = array();
                    if ($logged_in_user == $Vote->voter_account) {
                      $controls = array('voter_account' => '$voter_account [<a href="?action=remove_vote&domain=$domain&candidate_account=$candidate_account">Remove Vote</a>]');
                    }
                    $Vote->print('table',$controls);
                  }
                  ?>
                  </table>
                  <?php
                }
              }
            } catch (Exception $e) {
              $show_create_election = true;
            }
            if ($show_create_election) {
              // No current election.
              ?>
              <form method="POST" id="create_election">
                <input type="hidden" name="action" value="create_election">
                <input type="hidden" name="domain" value="<?php print $domain; ?>">
                  <?php
                  $Election = $Factory->new("Election");
                  print $Election->formHTML($election_form_values);
                  $vote_date = date("Y-m-d",time() + (30 * 24 * 60 * 60));
                  ?>
                <div class="col-sm-2">
                    <div class="form-group">
                        <label>Vote Date</label>
                        <input type="text" name="vote_date" id="vote_date" class="form-control" value="<?php print $vote_date; ?>" placeholder="YYYY-mm-dd">
                    </div>
                </div>
                <div class="form-group">
                  <button type="submit" class="btn btn-primary">Create Election</button>
                </div>
              </form>
              <?php
            }

            $inactive_members = $Group->getInactiveMembers();
            if (count($inactive_members)) {
              ?>
              <h2>Inactive Members:</h2>
              <table id="inactive_members" class="table table-striped table-bordered">
              <?php
              $inactive_members[0]->print('table_header');
              foreach ($inactive_members as $InactiveMember) {
                $controls = array(
                  'account' => '$account [<a href="?action=activate_member&domain=$domain&account=$account">Activate</a>]',
                );
                if ($is_admin) {
                  $controls['account'] .= ' [<a href="?action=disable_member&domain=$domain&account=$account">Disable</a>]';
                }
                $InactiveMember->print('table',$controls);
              }
              ?>
              </table>
              <?php
            }

            $disabled_members = $Group->getDisabledMembers();
            if (count($disabled_members)) {
              ?>
              <h2>Disabled Members:</h2>
              <table id="disabled_members" class="table table-striped table-bordered">
              <?php
              $disabled_members[0]->print('table_header');
              foreach ($disabled_members as $DisabledMember) {
                $controls = array();
                if ($is_admin) {
                  $controls = array(
                    'account' => '$account [<a href="?action=enable_member&domain=$domain&account=$account">Enable</a>]',
                  );
                }
                $DisabledMember->print('table',$controls);
              }
              ?>
              </table>
              <?php
            }

            $members = $Group->getMembers();
            if (count($members)) {
              ?>
              <h2>Members:</h2>
              <table id="members" class="table table-striped table-bordered">
              <?php
              $members[0]->print('table_header');
              foreach ($members as $Member) {
                $controls = array(
                  'account' => '$account',
                  'member_name' => '<a href="' . $explorer_url . 'address/$member_name@$domain" target="_blank">$member_name</a>',
                );
                if ($logged_in_user == $Member->account) {
                  $controls['account'] .= ' [<a href="?action=register_candidate&domain=$domain&account=$account">Register Candidate</a>] [<a href="?action=deactivate_member&domain=$domain&account=$account">Deactivate</a>]';
                }
                if ($is_admin) {
                  $controls['account'] .= ' [<a href="?action=disable_member&domain=$domain&account=$account">Disable</a>]';
                }
                $Member->print('table',$controls);
              }
              ?>
              </table>
              <?php
            }

            $pendingmembers = $Group->getPendingMembers();
            if (count($pendingmembers)) {
              ?>
              <h2>Pending Members:</h2>
              <table id="pending_members" class="table table-striped table-bordered">
              <?php
              $pendingmembers[0]->print('table_header');
              foreach ($pendingmembers as $PendingMember) {
                // TODO: change this to be a javascript form POST, not a get. Protect against CSRF.
                $controls = array(
                  'application_date' => '<a href="' . $explorer_url . 'transaction/$membership_payment_transaction_id" target="_blank">$application_date</a>',
                );
                if ($is_admin) {
                  $controls['account'] = '$account [<a href="?action=approve_pending_member&domain=$domain&account=$account&membership_proposal_name=$membership_proposal_name">Approve</a>] [<a href="' . $explorer_url . 'msig/$account/$membership_proposal_name" target="_blank">View Msig</a>]';
                }
                $PendingMember->print('table',$controls);
              }
              ?>
              </table>
              <?php
            }
            ?>

            <h1>Apply</h1>
            <form method="POST" id="apply_to_group">
              <input type="hidden" name="action" value="apply_to_group">
              <input type="hidden" id="domain" name="domain" value="<?php print $domain; ?>">
              <input type="hidden" id="group_fio_public_key" name="group_fio_public_key" value="<?php print $Group->group_fio_public_key; ?>">
              <input type="hidden" id="group_account" name="group_account" value="<?php print $Group->group_account; ?>">
              <input type="hidden" id="member_application_fee" name="member_application_fee" value="<?php print $Group->member_application_fee; ?>">
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
                      <label>Member Name Requested (@<?php print $domain; ?>)</label>
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

          if ($domain == "") {
            $Group = $Factory->new("Group");
            $criteria = ['domain','!=',''];
            if ($domain != "") {
              $criteria = ['domain','=',$domain];
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
                  'domain' => '<a href="?domain=$domain">$domain</a>',
                  'group_account' => '<a href="' . $explorer_url . 'account/$group_account" target="_blank">$group_account</a>',
                );
                $Group->print('table',$controls);
              }
              ?>
              </table>
              <?php
              print '<p>[<a href="?action=show_create_group">Create Group</a>]</p>';
            }
            if (count($groups) == 0 || $action == "show_create_group") {
            ?>
              <h1>Create Group</h1>
              <p>You will be asked to sign two transactions. The first transaction will:</p>
                <ol>
                  <li>Register your group FIO Domain.</li>
                  <li>Register your FIO Name as the first FIO Address at your group domain.</li>
                  <li>Send 10 FIO to a new group account.</li>
                </ol>
              <p>
                At this point the permissions on the new group account will be updated so that you are the admin.<br />
                You will then be asked to sign another transaction to transfer the domain to the new group.<br />
                After this is completed, your group will be saved into the sytem.
              </p>
              <p>
                Create Domain Fee: <span id="create_domain_fee"><?php print $Util->SUFToFIO($Util->getRegisterDomainFee()); ?> FIO</span><br />
                Transfer Token Fee: <span id="transfer_tokens_fee"><?php print $Util->SUFToFIO($Util->getTransferFee()); ?> FIO</span><br />
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
          } else {
            print '<p>[<a href="?action=home">Home</a>]</p>';
          }
          ?>

          <form id="login" method="POST">
            <input id="identity_proof" name="identity_proof" value="" type="hidden">
            <input id="actor" name="actor" value="" type="hidden">
            <input id="action" name="action" value="login" type="hidden">
          </form>

          <div class="form-group">
            <button type="submit" class="btn btn-primary" onclick="login()">Login</button>
          </div>


      </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
    <script>
    var nodeUrl = '<?php print $nodeUrl; ?>';
    <?php if (isset($_GET{'login'})) { ?>
      $(function() {
        login();
      });
    <?php } ?>
    <?php if (isset($_GET{'logout'})) { ?>
      $(function() {
        logout();
      });
    <?php } ?>
    </script>
    <script src="https://unpkg.com/anchor-link@3"></script>
    <script src="https://unpkg.com/anchor-link-browser-transport@3"></script>
    <script src="js/long.js"></script>
    <!-- Bootstrap Date-Picker Plugin -->
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
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
                  chainId: '<?php print $chainId; ?>',
                  nodeUrl: '<?php print $nodeUrl; ?>',
              }
          ],
        }
      );
    // the session instance, either restored using link.restoreSession() or created with link.login()
    let session
    </script>
  </body>
</html>