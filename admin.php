<?php
require_once __DIR__ . '/vendor/autoload.php';
$client = new GuzzleHttp\Client(['base_uri' => 'http://fio.greymass.com']);
include "header.php";

if (php_sapi_name() != "cli") {
    die("Access Denied");
}

/*
Public Key: FIO7EwPGwmYGZF6fkvBNzbzYegj2q2dAZsp292P9oxkK8yjso5uDq
Private key: 5JwFrxkiqDdahVCZrPfNZQoMotBg5rNDhFzA4UTuCtyh77uRpUJ

FIO Internal Account (actor name): pmz3qk1c4jqj
*/

// Create a Group
$Factory = new Factory();
$Group = $Factory->new("Group");
$fio_public_key = "FIO7EwPGwmYGZF6fkvBNzbzYegj2q2dAZsp292P9oxkK8yjso5uDq";
$group_account = "pmz3qk1c4jqj";
$domain = "testing";
$member_application_fee = 10 * 1000000000;

try {
	$Group = $Group->create($fio_public_key, $group_account, $domain, $member_application_fee);
} catch (Exception $e) {
	print $e->getMessage() . br();
}

// List Groups
print "---------------- GROUPS -----------------------\n";
$Group = $Factory->new("Group");
$groups_data = $Group->dataStore->findAll();
foreach ($groups_data as $group_data) {
  $Group = $Factory->new("Group");
  $Group->loadData($group_data);
  $Group->print();
}

// apply for membership
$Group = $Factory->new("Group");
$Group->_id = 1;
$Group->read();

/*
Public Key: FIO56ukRYzRpej1evEYuf21tBg293QPzF2s7o597xCubRH63pPZfN
Private key: 5KZKvDRYKNKuZ6B6vM5wXsvHEFiZnn4SrgqNEBAxWUzLGexRpxg

FIO Internal Account (actor name): wntoh3fogzcj
*/
$account = "wntoh3fogzcj";
$fio_address = "test@testing";
try {
	$PendingMember = $Group->apply($account, $fio_address, "Some cool bio, yo.", "9255cd7196e6d4003a3352928f3fec63cdf2a9ae7834512f932e95363f6e5408");
} catch (Exception $e) {
	print $e->getMessage() . br();
}

// List Pending Members
print "---------------- Pending Members -----------------------\n";
$PendingMember = $Factory->new("PendingMember");
$pending_members_data = $PendingMember->dataStore->findAll();
foreach ($pending_members_data as $pending_member_data) {
  $PendingMember = $Factory->new("PendingMember");
  $PendingMember->loadData($pending_member_data);
  $PendingMember->print();
}

try {
	$Member = $Group->approve($account);
} catch (Exception $e) {
	print $e->getMessage() . br();
}

// List Members
print "---------------- Members -----------------------\n";
$Member = $Factory->new("Member");
$members_data = $Member->dataStore->findAll();
foreach ($members_data as $member_data) {
  $Member = $Factory->new("Member");
  $Member->loadData($member_data);
  $Member->print();
}

// register candidate
$Member = $Factory->new("Member");
$Member->_id = 1;
$Member->read();

try {
	$Member = $Group->registerCandidate($account);
} catch (Exception $e) {
	print $e->getMessage() . br();
}

// List Admin Candidates
print "---------------- Admin Candidates -----------------------\n";
$AdminCandidate = $Factory->new("AdminCandidate");
$admin_candidates_data = $AdminCandidate->dataStore->findAll();
foreach ($admin_candidates_data as $admin_candidate_data) {
  $AdminCandidate = $Factory->new("AdminCandidate");
  $AdminCandidate->loadData($admin_candidate_data);
  $AdminCandidate->print();
}

try {
	$vote_time = time()+(60*60*24*30); // 30 days from now
	$Election = $Group->createElection(5, 3, 8, $vote_time);
} catch (Exception $e) {
	print $e->getMessage() . br();
}

// List Elections
print "---------------- Elections -----------------------\n";
$Election = $Factory->new("Election");
$election_data = $Election->dataStore->findAll();
foreach ($election_data as $election_data) {
  $Election = $Factory->new("Election");
  $Election->loadData($election_data);
  $Election->print();
}

try {
	$Group->vote($account, $account, 1, 10);
} catch (Exception $e) {
	print $e->getMessage() . br();
}

// List Votes
print "---------------- Votes -----------------------\n";
$Vote = $Factory->new("Vote");
$votes_data = $Vote->dataStore->findAll();
foreach ($votes_data as $vote_data) {
  $Vote = $Factory->new("Vote");
  $Vote->loadData($vote_data);
  $Vote->print();
}
