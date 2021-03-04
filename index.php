<?php
require_once __DIR__ . '/vendor/autoload.php';
$client = new GuzzleHttp\Client(['base_uri' => 'http://fio.greymass.com']);
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

          if (isset($_POST["action"])) {
            $action = strip_tags($_POST["action"]);
          }
          if (isset($_REQUEST["domain"])) {
            $domain = strip_tags($_REQUEST["domain"]);
            $Group = $Factory->new("Group");
            $Group->read(['domain','=',$domain]);
          }

          if ($action == "create_group") {
            $Group = $Factory->new("Group");
            try {
              $Group->create(
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

          if ($notice != "") {
            ?>
              <div class="alert alert-info" role="alert">
                <?php print $notice; ?>
              </div>
            <?php
          }

          if ($domain != "") {
            $Group = $Factory->new("Group");
            $Group->read(['domain','=',$domain]);
            ?>
            <h1><?php print $domain; ?></h1>
            <h2>Members:</h2>
            <table class="table table-striped table-bordered">
            <?php
            $members = $Group->getMembers();
            if (count($members)) {
              $members[0]->print('table_header');
            }
            foreach ($members as $Member) {
              $Member->print('table');
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
              $PendingMember->print('table');
            }
            ?>
            </table>
            <h1>Apply</h1>
            <form method="POST">
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
            ?>
            <h1>Groups:</h1>
            <table class="table table-striped table-bordered">
            <?php
            $Group = $Factory->new("Group");
            $criteria = ['domain','!=',''];
            if ($domain != "") {
              $criteria = ['domain','=',$domain];
            }
            $groups = $Group->readAll($criteria);
            foreach ($groups as $Group) {
              $Group->print('table_header', true);
              $controls = array('domain' => '<a href="?domain=$value">$value</a>');
              $Group->print('table',$controls);
            }
            ?>
            </table>

            <h1>Create Group</h1>
            <form method="POST">
              <input type="hidden" name="action" value="create_group">
                <?php
                $Group = $Factory->new("Group");
                print $Group->formHTML();
                ?>
              <div class="form-group">
                <button type="submit" class="btn btn-primary">Create Group</button>
              </div>
            </form>
            <?php
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