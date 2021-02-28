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

        <h1>Groups:</h1>
        <?php
        $Group = new Factory("Group");
        $groups_data = $Group->dataStore->findAll();
        foreach ($groups_data as $group_data) {
          $Group = new Factory("Group");
          $Group->loadData($group_data);
          $Group->print();
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