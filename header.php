<?php
include "objects.php";

$Util = new Util($client);

$title = "FIO Groups";
$description = "FIO Groups";
$image = "";
$url = "";
$onboarding_pitch = '<a href="https://fioprotocol.io/free-fio-addresses/" target="_blank">get yourself a FIO Address</a> and import your private key into <a href="https://greymass.com/anchor/" target="_blank">Anchor Wallet by Greymass</a> to login.';

/** FOR TESTING **/
$logged_in_user = "loggedinuser";

$Factory = new Factory();
$action = "";
$notice = "";
$domain = "";
$is_admin = false;

if (isset($_REQUEST["action"])) {
    $action = strip_tags($_REQUEST["action"]);
}
if (isset($_REQUEST["domain"])) {
    $domain = strip_tags($_REQUEST["domain"]);
    $Group = $Factory->new("Group");
    $Group->read(['domain','=',$domain]);
}

// Begin the PHP session so we have a place to store the username
session_start();

if (isset($_SESSION["username"])) {
    $logged_in_user = $_SESSION["username"];
}

include "actions.php";

if ($domain != "") {
    $Group = $Factory->new("Group");
    $found = $Group->read(['domain', '=', $domain]);
    if (!$found) {
        $domain = "";
    }
}

if (isset($_GET["logout"])) {
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
