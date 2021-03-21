<?php
include "objects.php";

$Util = new Util($client);

$title            = "FIO Groups";
$description      = "FIO Groups";
$image            = "";
$url              = "";
$onboarding_pitch = '<a href="https://fioprotocol.io/free-fio-addresses/" target="_blank">get yourself a FIO Address</a> and import your private key into <a href="https://greymass.com/anchor/" target="_blank">Anchor Wallet by Greymass</a> to login.';

/** FOR TESTING **/
$logged_in_user = "loggedinuser";

$Factory  = new Factory();
$action   = "";
$show     = "";
$notice   = "";
$domain   = "";
$is_admin = false;

if (isset($_REQUEST["action"])) {
    $action = strip_tags($_REQUEST["action"]);
}
if (isset($_REQUEST["show"])) {
    $show = strip_tags($_REQUEST["show"]);
}
if (isset($_REQUEST["domain"])) {
    $domain = strip_tags($_REQUEST["domain"]);
    $Group  = $Factory->new("Group");
    $Group->read(['domain', '=', $domain]);
}

// Begin the PHP session so we have a place to store the username
session_start();

if (isset($_SESSION["username"])) {
    $logged_in_user = $_SESSION["username"];
    $Util->actor    = $_SESSION['username'];
}

include "actions.php";

if ($domain != "") {
    $Group = $Factory->new("Group");
    $found = $Group->read(['domain', '=', $domain]);
    if (!$found) {
        $domain = "";
    }
}
