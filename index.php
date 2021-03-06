<?php
require_once __DIR__ . '/vendor/autoload.php';
$client = new GuzzleHttp\Client(['base_uri' => 'http://fio.greymass.com']);
include "header.php";
$explorer_url = "https://fio.bloks.io/";
$explorer_url = "https://fio-test.bloks.io/";
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
          $Factory = new Factory();
          $action = "";
          $notice = "";
          $domain = "";

          // for testing
          $logged_in_user = "loggedinuser";

          if (isset($_REQUEST["action"])) {
            $action = strip_tags($_REQUEST["action"]);
          }
          if (isset($_REQUEST["domain"])) {
            $domain = strip_tags($_REQUEST["domain"]);
            $Group = $Factory->new("Group");
            $Group->read(['domain','=',$domain]);
          }

          // for testing
          if ($action == "testing_change_vote_date") {
            try {
              $Election = $Group->getCurrentElection();
              $Election->vote_date = time() - 1000;
              $Election->save();
              $notice = "TESTING: Election vote date set in the past.";
            } catch (Exception $e) { }
          }
          // for testing
          if ($action == "clear_all_data") {
            exec("rm -rf \"" . __DIR__ . "/data\"");
            exec("mkdir \"" . __DIR__ . "/data\"");
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
            } catch (Exception $e) {
              $notice = $e->getMessage();
            }
          }

          if ($action == "apply_to_group") {
            try {
              $Group->apply(
                strip_tags($_POST["account"]),
                strip_tags($_POST["member_name_requested"]),
                strip_tags($_POST["bio"]),
                strip_tags($_POST["membership_payment_transaction_id"])
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
                <?php print $notice; ?>
              </div>
            <?php
          }

          if ($domain != "") {
            $Group = $Factory->new("Group");
            $found = $Group->read(['domain','=',$domain]);
            if (!$found) {
              $domain = "";
            }
          }
          if ($domain != "") {
            ?>
            <h1><?php print $domain; ?></h1>

            <p>[<a href="?action=testing_change_vote_date&domain=<?php print $domain; ?>">TESTING: Change Election Vote Date</a>]</p>
            <p>[<a href="?action=clear_all_data&domain=<?php print $domain; ?>">TESTING: Clear Data</a>]</p>

            <h2>Admins:</h2>
            <table class="table table-striped table-bordered">
            <?php
            $admins = $Group->getAdmins();
            if (count($admins)) {
              $admins[0]->print('table_header');
            }
            foreach ($admins as $Admin) {
              $Admin->print('table');
            }
            ?>
            </table>

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
            $show_create_election = false;
            $election_form_values = array();
            try {
              $Election = $Group->getCurrentElection();
              if ($Election->is_complete) {
                $show_create_election = true;
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
                'vote_date' => '$vote_date [<a href="?action=show_votes&domain=$domain">Show Votes</a>] [<a href="?action=record_vote_results&domain=$domain">Record Vote Results</a>]'
              );
              $Election->print('table',$controls);
              ?>
              </table>
              <?php
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
                    $controls = array('voter_account' => '$voter_account [<a href="?action=remove_vote&domain=$domain&candidate_account=$candidate_account">Remove Vote</a>]');
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
            ?>

            <h2>Members:</h2>
            <table class="table table-striped table-bordered">
            <?php
            $members = $Group->getMembers();
            if (count($members)) {
              $members[0]->print('table_header');
            }
            foreach ($members as $Member) {
              $controls = array(
                'account' => '$account [<a href="?action=register_candidate&domain=$domain&account=$account">Register Candidate</a>]',
              );
              $Member->print('table',$controls);
            }
            ?>
            </table>
            <h2>Pending Members:</h2>
            <table class="table table-striped table-bordered">
            <?php
            $pendingmembers = $Group->getPendingMembers();
            if (count($pendingmembers)) {
              $pendingmembers[0]->print('table_header');
            }
            foreach ($pendingmembers as $PendingMember) {
              // TODO: change this to be a javascript form POST, not a get. Protect against CSRF.
              $controls = array(
                'account' => '$account [<a href="?action=approve_pending_member&domain=$domain&account=$account">Approve</a>]',
                'application_date' => '<a href="' . $explorer_url . '/transaction/$membership_payment_transaction_id">$application_date</a>'
              );
              $PendingMember->print('table',$controls);
            }
            ?>
            </table>
            <h1>Apply</h1>
            <form method="POST" id="apply_to_group">
              <input type="hidden" name="action" value="apply_to_group">
              <input type="hidden" name="domain" value="<?php print $domain; ?>">
              <input type="hidden" name="membership_payment_transaction_id" value="1234567890">
                <?php
                $PendingMember = $Factory->new("PendingMember");
                print $PendingMember->formHTML();
                ?>
              <div class="form-group">
                <button type="submit" class="btn btn-primary">Apply for Membership</button>
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
              <table class="table table-striped table-bordered">
              <?php
              $groups[0]->print('table_header');
              foreach ($groups as $Group) {
                $controls = array('domain' => '<a href="?domain=$domain">$domain</a>');
                $Group->print('table',$controls);
              }
              ?>
              </table>
              <?php
            }
            ?>
            <h1>Create Group</h1>
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
                <button type="submit" class="btn btn-primary">Create Group</button>
              </div>
            </form>
            <?php
          } else {
            print '<p>[<a href="?action=home">Home</a>]</p>';
          }
          ?>

      </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
    <script>
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
    <script src="js/script.js"></script>
    <!-- Bootstrap Date-Picker Plugin -->
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
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
                  chainId: '21dcae42c0182200e93f954a074011f9048a7624c6fe81d3c9541a614a88bd1c',
                  nodeUrl: 'https://fio.greymass.com',
              }
          ],
        }
      );
    // the session instance, either restored using link.restoreSession() or created with link.login()
    let session
    </script>
  </body>
</html>