$(document).ready(function(){
  var date_input=$('#vote_date'); //our date input has the name "date"
  var container=$('.bootstrap-iso form').length>0 ? $('.bootstrap-iso form').parent() : "body";
  var options={
    format: 'yyyy-mm-dd',
    container: container,
    todayHighlight: true,
    autoclose: true,
  };
  date_input.datepicker(options);

  $('#apply_to_group_button').on('click', function(e) {
    // TODO: I need a way (from JavaScript) to query server side values to ensure this person hasn't already applied.
    if (!link) {
      alert("Please login using your FIO account and Anchor Wallet by Greymass.");
      return;
    }
    const regex = new RegExp('^(?:(?=.{3,64}$)[a-zA-Z0-9]{1}(?:(?!-{2,}))[a-zA-Z0-9-]*(?:(?<!-))@[a-zA-Z0-9]{1}(?:(?!-{2,}))[a-zA-Z0-9-]*(?:(?<!-))$)');
    domain = $("#domain").val();
    member_name_requested = $("#member_name_requested").val();
    if (!regex.test(member_name_requested+'@'+domain)) {
      alert("Please enter a valid name and domain. Valid characters include: a-z 0-9 -");
      return;
    }
    member_application_fee = $("#member_application_fee").val();
    if (isNaN(member_application_fee)) {
      member_application_fee = 1;
    }
    group_fio_public_key = $("#group_fio_public_key").val();
    group_account = $("#group_account").val();

    applyToGroup(
      member_application_fee,
      member_name_requested,
      domain,
      group_fio_public_key,
      group_account
      );
  });

  $('#create_group_button').on('click', function(e) {
    if (!link) {
      alert("Please login using your FIO account and Anchor Wallet by Greymass.");
      return;
    }
    const regex = new RegExp('^(?:(?=.{3,64}$)[a-zA-Z0-9]{1}(?:(?!-{2,}))[a-zA-Z0-9-]*(?:(?<!-))@[a-zA-Z0-9]{1}(?:(?!-{2,}))[a-zA-Z0-9-]*(?:(?<!-))$)');
    domain = $("#domain").val();
    creator_member_name = $("#creator_member_name").val();
    if (!regex.test(creator_member_name+'@'+domain)) {
      alert("Please enter a valid name and domain. Valid characters include: a-z 0-9 -");
      return;
    }

    privKey = AnchorLink.PrivateKey.generate('K1');
    pubKey = privKey.toPublic();
    privKeyWif = privKey.toWif();
    fio_public_key = pubKey.toLegacyString('FIO');
    keyPair = {
      private:privKeyWif,
      public:fio_public_key
    };

    console.log("Created a new temporary keyPair for " + keyPair.public);

    pubkey = keyPair.public.substring('FIO'.length, keyPair.public.length);
    const decoded58 = b58decode(pubkey);
    const long = shortenKey(decoded58);
    const actor = stringFromUInt64T(long);

    $("#group_fio_public_key").val(keyPair.public);
    $("#group_account").val(actor);

    createGroupOnChain(keyPair, actor, $("#domain").val(), $("#creator_member_name").val());
  });

});

async function verifyOwners(domain, admins, vote_threshold) {
  const group_account_from_domain = await getDomainOwner(domain);
  const group_account_info = await chainGet('get_account',{account_name: group_account_from_domain});
  active_on_chain_admins = [];
  owner_on_chain_admins = [];
  for (i = 0; i < group_account_info.permissions.length; i++) {
    if (group_account_info.permissions[i].perm_name == "active") {
      for (j = 0; j < group_account_info.permissions[i].required_auth.accounts.length; j++) {
        active_on_chain_admins[active_on_chain_admins.length] = group_account_info.permissions[i].required_auth.accounts[j].permission.actor;
      }
    }
    if (group_account_info.permissions[i].perm_name == "owner") {
      for (j = 0; j < group_account_info.permissions[i].required_auth.accounts.length; j++) {
        owner_on_chain_admins[owner_on_chain_admins.length] = group_account_info.permissions[i].required_auth.accounts[j].permission.actor;
      }
    }
  }
  admins.sort();
  owner_on_chain_admins.sort();
  active_on_chain_admins.sort();

  is_correct = true;
  if (JSON.stringify(admins)==JSON.stringify(owner_on_chain_admins) && JSON.stringify(admins)==JSON.stringify(active_on_chain_admins)) {
    alert("The owner and active permissions for " + group_account_from_domain + " are correct on chain: " + JSON.stringify(owner_on_chain_admins));
  } else {
    is_correct = false;
    onchain_string = "Owner: " + JSON.stringify(owner_on_chain_admins) + " Active: " + JSON.stringify(active_on_chain_admins);
    alert("The account permissions on chain are not correct. Let's propose an msig to fix this so what is on chain: " + onchain_string + " will be updated to match the vote results: " + JSON.stringify(admins));
  }

  if (!is_correct) {
    completeElection(domain, admins, vote_threshold);
  }
}

async function completeElection(domain, new_admins, vote_threshold) {
  alert("This may take a few moments to process. Please keep your browser window open and don't refresh until the process is complete.");
  const fio_public_key = session.publicKey.toLegacyString("FIO");
  const tpid = 'luke@stokes'
  const current_balance = await getFIOBalance(fio_public_key);
  const msig_propose_fee = await getFIOChainFee('msig_propose');

  total_to_charge = msig_propose_fee

  if (total_to_charge > FIOToSUF(current_balance)) {
    alert("You need " + SUFToFIO(total_to_charge) + " FIO to apply for membership but only have " + current_balance);
    return ;
  }

  if (vote_threshold > new_admins.length) {
    alert("The vote threshold set can not be satisifed. Please contact support to fix your election.");
    return ;
  }

  confirm_cost = confirm("Are you ready to spend " + SUFToFIO(total_to_charge) + " FIO to complete this election?");
  confirm_cost = true;
  if (!confirm_cost) {
    return ;
  }

  auth_update_fee = await getFIOChainFee('auth_update');
  //auth_update_fee = auth_update_fee * 5;
  const eosio_abi = await getABI('eosio');
  const group_account_from_domain = await getDomainOwner(domain);
  const upate_owner_action = getCustomizedUpdateAuthAction(group_account_from_domain,"owner","", vote_threshold, new_admins, auth_update_fee, eosio_abi);
  const upate_active_action = getCustomizedUpdateAuthAction(group_account_from_domain,"active","owner", vote_threshold, new_admins, auth_update_fee, eosio_abi);

  const eosiomsig_abi = await getABI('eosio.msig');
  const proposal_name = getProposalName();

  const expiration_days_in_seconds = (60 * 60 * 24 * 7) // seconds, minutes, hours, days

  expiration = new AnchorLink.TimePointSec(AnchorLink.UInt32.from((Date.now()/1000) + expiration_days_in_seconds));

  raw_transaction = {
    expiration: expiration,
    ref_block_num: 0,
    ref_block_prefix: 0,
    max_net_usage_words: 0,
    max_cpu_usage_ms: 0,
    delay_sec: 0,
    context_free_actions: [],
    actions: [upate_active_action,upate_owner_action],
    transaction_extensions: []
  }

  const transaction = AnchorLink.Transaction.from(raw_transaction)

  const group_account_info = await chainGet('get_account',{account_name: group_account_from_domain});
  required_auths = [];
  for (i = 0; i < group_account_info.permissions.length; i++) {
    if (group_account_info.permissions[i].perm_name == "active") {
      for (j = 0; j < group_account_info.permissions[i].required_auth.accounts.length; j++) {
        required_auths[required_auths.length] = {
          actor: group_account_info.permissions[i].required_auth.accounts[j].permission.actor,
          permission: group_account_info.permissions[i].required_auth.accounts[j].permission.permission
        };
      }
    }
  }

  const msig_action = AnchorLink.Action.from({
    account: 'eosio.msig',
    name: 'propose',
    authorization: [session.auth],
    data: {
      proposer: session.auth.actor,
      proposal_name: proposal_name,
      requested: required_auths,
      max_fee: msig_propose_fee,
      trx: transaction
    },
  },eosiomsig_abi);

  try {
    const actions_result = await session.transact(
      {
        actions: [msig_action]
      }
    );
    console.log("done")
    console.log(actions_result.processed.id);

    $("#results_proposal_name").val(proposal_name);
    $("#results_proposer").val(session.auth.actor);

  } catch (err) {
    console.log(err);
    alert("There was an error completing this election. Please make sure you have enough FIO Tokens. Check the console for details.");
    return ;
  }

  if ($('#election')) { // fix this
    $('#election').submit();
  }
}

async function applyToGroup(
    member_application_fee,
    member_name_requested,
    domain,
    group_fio_public_key,
    group_account
  ) {
  alert("This may take a few moments to process. Please keep your browser window open and don't refresh until the process is complete.");

  const is_available = await isNameAvailable(member_name_requested + "@" + domain);
  if (!is_available) {
    alert("The name " + member_name_requested + "@" + domain + " has already been taken. Please try a different name.");
    return ;
  }
  const fio_public_key = session.publicKey.toLegacyString("FIO");
  const tpid = 'luke@stokes'
  const current_balance = await getFIOBalance(fio_public_key);
  const register_fio_address_fee = await getFIOChainFee('register_fio_address');
  const transfer_tokens_pub_key_fee = await getFIOChainFee('transfer_tokens_pub_key');
  const msig_propose_fee = await getFIOChainFee('msig_propose');

  total_to_charge = FIOToSUF(member_application_fee);
  total_to_charge += register_fio_address_fee
  total_to_charge += transfer_tokens_pub_key_fee
  total_to_charge += msig_propose_fee

  if (total_to_charge > FIOToSUF(current_balance)) {
    alert("You need " + SUFToFIO(total_to_charge) + " FIO to apply for membership but only have " + current_balance);
    return ;
  }
  confirm_cost = confirm("Are you ready to spend " + SUFToFIO(total_to_charge) + " FIO to apply for membership to this group?");
  confirm_cost = true;
  if (!confirm_cost) {
    return ;
  }

  const group_account_from_domain = await getDomainOwner(domain);

  if (group_account_from_domain != group_account) {
    alert(group_account + " does not match the owner of the group " + group_account_from_domain);
    return ;
  }

  const group_account_info = await chainGet('get_account',{account_name: group_account});
  required_auths = [];
  for (i = 0; i < group_account_info.permissions.length; i++) {
    if (group_account_info.permissions[i].perm_name == "active") {
      for (j = 0; j < group_account_info.permissions[i].required_auth.accounts.length; j++) {
        required_auths[required_auths.length] = {
          actor: group_account_info.permissions[i].required_auth.accounts[j].permission.actor,
          permission: group_account_info.permissions[i].required_auth.accounts[j].permission.permission
        };
      }
    }
  }

  const fioaddress_abi = await getABI('fio.address');
  const address_action = AnchorLink.Action.from({
    authorization: [{actor: group_account, permission: 'active'}],
    account: 'fio.address',
    name: 'regaddress',
    data: {
      fio_address: member_name_requested + "@" + domain,
      owner_fio_public_key: fio_public_key,
      max_fee: register_fio_address_fee,
      actor: group_account,
      tpid: tpid
    }
  },fioaddress_abi);

  const info = await link.client.v1.chain.get_info()
  header = info.getTransactionHeader()

  const expiration_days_in_seconds = (60 * 60 * 24 * 7) // seconds, minutes, hours, days

  header.expiration = new AnchorLink.TimePointSec(AnchorLink.UInt32.from(header.expiration.value.value + expiration_days_in_seconds));

  const transaction = AnchorLink.Transaction.from({
      ...header,
      actions: [address_action],
  })

  const eosiomsig_abi = await getABI('eosio.msig');
  const membership_proposal_name = getProposalName();

  const msig_action = AnchorLink.Action.from({
    account: 'eosio.msig',
    name: 'propose',
    authorization: [session.auth],
    data: {
      proposer: session.auth.actor,
      proposal_name: membership_proposal_name,
      requested: required_auths,
      max_fee: msig_propose_fee,
      trx: transaction
    },
  },eosiomsig_abi);

  const transfer_action = {
    authorization: [session.auth],
    account: 'fio.token',
    name: 'trnsfiopubky',
    data: {
        payee_public_key: group_fio_public_key,
        amount: total_to_charge,
        max_fee: transfer_tokens_pub_key_fee,
        actor: session.auth.actor,
        tpid: tpid
    }
  }

  try {
    const actions_result = await session.transact(
      {
        actions: [transfer_action, msig_action]
      }
    );
    console.log("done")
    console.log(actions_result.processed.id);

    $("#membership_payment_transaction_id").val(actions_result.processed.id);
    $("#membership_proposal_name").val(membership_proposal_name);

  } catch (err) {
    console.log(err);
    alert("There was an error apply to this group. Please make sure you have enough FIO Tokens. Check the console for details.");
    return ;
  }

  $('#apply_to_group').submit();
}


async function createGroupOnChain(keyPair, actor, domain, name) {
  alert("This may take a few moments to process. Please keep your browser window open and don't refresh until the process is complete.");

  // first see if the domain is available
  const is_available = await isNameAvailable(domain);
  if (!is_available) {
    alert("The domain " + domain + " has already been taken. Please try a different domain.");
    return ;
  }
  const fio_public_key = session.publicKey.toLegacyString("FIO");
  const current_balance = await getFIOBalance(fio_public_key);
  const register_fio_domain_fee = await getFIOChainFee('register_fio_domain');
  const register_fio_address_fee = await getFIOChainFee('register_fio_address');
  const transfer_tokens_pub_key_fee = await getFIOChainFee('transfer_tokens_pub_key');
  const transfer_fio_domain_fee = await getFIOChainFee('transfer_fio_domain');

  total_to_charge = FIOToSUF(10);
  total_to_charge += register_fio_domain_fee
  total_to_charge += register_fio_address_fee
  total_to_charge += transfer_tokens_pub_key_fee
  total_to_charge += transfer_fio_domain_fee

  if (total_to_charge > FIOToSUF(current_balance)) {
    alert("You need " + SUFToFIO(total_to_charge) + " FIO to create a group but only have " + current_balance);
    return ;
  }
  confirm_cost = confirm("Are you ready to spend " + SUFToFIO(total_to_charge) + " FIO to create your group?");
  if (!confirm_cost) {
    return ;
  }

  const tpid = 'luke@stokes'
  const domain_action = {
    authorization: [session.auth],
    account: 'fio.address',
    name: 'regdomain',
    data: {
      fio_domain: domain,
      owner_fio_public_key: fio_public_key,
      max_fee: register_fio_domain_fee,
      actor: session.auth.actor,
      tpid: tpid
    }
  }
  const address_action = {
    authorization: [session.auth],
    account: 'fio.address',
    name: 'regaddress',
    data: {
      fio_address: name + "@" + domain,
      owner_fio_public_key: fio_public_key,
      max_fee: register_fio_address_fee,
      actor: session.auth.actor,
      tpid: tpid
    }
  }
  /*
  // this is too much, transaction times out
  const treasury_address_action = {
    authorization: [session.auth],
    account: 'fio.address',
    name: 'regaddress',
    data: {
      fio_address: "treasury@" + domain,
      owner_fio_public_key: fio_public_key,
      max_fee: register_fio_address_fee,
      actor: session.auth.actor,
      tpid: tpid
    }
  }
  */
  const transfer_action = {
    authorization: [session.auth],
    account: 'fio.token',
    name: 'trnsfiopubky',
    data: {
        payee_public_key: keyPair.public,
        amount: FIOToSUF(10), // enough to do msigs and such.
        max_fee: transfer_tokens_pub_key_fee,
        actor: session.auth.actor,
        tpid: tpid
    }
  }
  try {
    const actions_result = await session.transact(
      {
        //actions: [domain_action,address_action,treasury_address_action,transfer_action]
        actions: [domain_action,address_action,transfer_action]
      }
    );
    console.log("Domain created, address created, tokens transfered")
    console.log(actions_result.processed.id);
  } catch (err) {
    console.log(err);
    alert("There was an error setting up your group. Please make sure you have enough FIO Tokens. Check the console for details.");
    return ;
  }

  try {
    const permission_update_result = await updatePermissionsOfNewlyCreatedAcccount(keyPair, actor);
    console.log("Permissions updated for new account at " + keyPair.public)
    console.log(permission_update_result.processed.id);
  } catch (err) {
    console.log("Error updating group permissions... let's try again...");
    console.log(err);
    alert("There was an error updating your permissions, but we're going to try again...");
    try {
      const permission_update_result_second_try = await updatePermissionsOfNewlyCreatedAcccount(keyPair, actor);
      console.log("Permissions updated for new account at " + keyPair.public)
      console.log(permission_update_result_second_try.processed.id);
    } catch (second_error) {
      console.log("We tried twice. Not sure what's going on...");
      console.log(second_error);
      alert("There was an error updating permissions for your group. Please make sure you have enough FIO Tokens. Check the console for details.");
      return ;
    }
  }

  const transfer_domain_action = {
    authorization: [session.auth],
    account: 'fio.address',
    name: 'xferdomain',
    data: {
      fio_domain: domain,
      new_owner_fio_public_key: keyPair.public,
      max_fee: transfer_fio_domain_fee,
      actor: session.auth.actor,
      tpid: tpid
    }
  }
  /*
  const transfer_treasury_action = {
    authorization: [session.auth],
    account: 'fio.address',
    name: 'xferaddress',
    data: {
      fio_address: "treasury@" + domain,
      new_owner_fio_public_key: keyPair.public,
      max_fee: transfer_fio_domain_fee,
      actor: session.auth.actor,
      tpid: tpid
    }
  }
  */

  try {
    const transfer_domain_result = await session.transact(
      {
        //actions: [transfer_domain_action, transfer_treasury_action]
        action: transfer_domain_action
      }
    );
    console.log("Domain transfered to " + keyPair.public)
    console.log(transfer_domain_result.processed.id);
  } catch (err) {
    console.log(err);
    alert("There was an error transferring your domain. Please make sure you have enough FIO Tokens. Check the console for details.");
    return ;
  }

  $('#create_group').submit();
}


function FIOToSUF(amount) {
  return (amount * 1000000000);
}
function SUFToFIO(amount) {
  return (amount / 1000000000);
}

async function getDomainOwner(domain) {
  const encoded = new TextEncoder().encode(domain)
  // get a sha-1 hash of the value
  hashHex = ''
  hash = await crypto.subtle.digest("SHA-1", encoded)
  const hashArray = Array.from(new Uint8Array(hash)).slice(0,16).reverse()
  // convert to a string with '0x' prefix
  hashHex = '0x' + hashArray.map(b => b.toString(16).padStart(2, '0')).join('')

  const params = {
    code: 'fio.address',
    scope: 'fio.address',
    table: 'domains',
    lower_bound: hashHex,
    upper_bound: hashHex,
    key_type: 'i128',
    index_position: 4,
    json: true
  }
  const result = await chainGet('get_table_rows',params);
  owner = "";
  if (result && result.rows[0] && result.rows[0].account) {
    owner = result.rows[0].account;
  }
  return owner;
}

function getProposalName() {
  proposal_name = ""
  for (var i = 0; i < 7; i++) {
    proposal_name = proposal_name + "" + (Math.floor(Math.random() * 5) + 1);
  }
  return "apply" + proposal_name;
}

async function chainGet(endpoint,params) {
  const response = await fetch(link.chains[0].client.provider.url + '/v1/chain/' + endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(params)
  })
  const data = await response.json()
  return data;
}

async function getABI(account_name) {
  const result = await chainGet('get_abi',{account_name: account_name});
  return result.abi;
}
async function getFIOChainFee(endpoint) {
  const result = await chainGet('get_fee',{end_point: endpoint,fio_address: '',});
  fee = result.fee + (result.fee * .1);
  return fee;
}
async function isNameAvailable(name) {
  const result = await chainGet('avail_check',{fio_name: name});
  return (result.is_registered == 0);
}
async function getFIOBalance(fio_public_key) {
  const result = await chainGet('get_fio_balance',{fio_public_key: fio_public_key});
  return SUFToFIO(result.balance);
}

async function updatePermissionsOfNewlyCreatedAcccount(keyPair, actor) {
  const auth_update_fee = await getFIOChainFee('auth_update');
  const abi = await getABI('eosio');
  const info = await link.client.v1.chain.get_info()
  const header = info.getTransactionHeader()
  const upate_owner_action = getUpdateAuthAction(actor,"owner","", auth_update_fee, abi);
  const upate_active_action = getUpdateAuthAction(actor,"active","owner", auth_update_fee, abi);
  const signedTransaction = getSignedTransaction(
    header,
    keyPair,
    [upate_active_action,upate_owner_action],
    info);
  const result = await link.client.v1.chain.push_transaction(signedTransaction)
  console.log(result);
  return result;
}

function getSignedTransaction(header, keyPair, actions, info) {
  const transaction = AnchorLink.Transaction.from({
      ...header,
      actions: actions,
  })
  const privateKey = AnchorLink.PrivateKey.from(keyPair.private)
  const signature = privateKey.signDigest(transaction.signingDigest(info.chain_id))
  const signedTransaction = AnchorLink.SignedTransaction.from({
      ...transaction,
      signatures: [signature],
  })
  return signedTransaction
}

function getCustomizedUpdateAuthAction(actor, permission, parent, threshold, accounts, auth_update_fee, abi) {
  auth_accounts = [];
  for (var i = 0; i < accounts.length; i++) {
    auth_account = {
      "permission": {
        "actor": accounts[i],
        "permission": "active"
      },
      "weight": 1
    }
    auth_accounts[auth_accounts.length] = auth_account;
  }
  const action = AnchorLink.Action.from({
  "authorization": [
      {
          "actor": actor,
          "permission": 'owner',
      },
  ],
  "account": 'eosio',
  "name": 'updateauth',
  "data": {
    "account": actor,
    "permission": permission,
    "parent": parent,
    "auth": {
      "threshold": threshold,
      "keys": [],
      "accounts": auth_accounts,
      "waits": []
    },
    "max_fee": auth_update_fee
  }
  },abi);
  return action;
}


function getUpdateAuthAction(actor, permission, parent, auth_update_fee, abi) {
  const action = AnchorLink.Action.from({
  "authorization": [
      {
          "actor": actor,
          "permission": 'owner',
      },
  ],
  "account": 'eosio',
  "name": 'updateauth',
  "data": {
    "account": actor,
    "permission": permission,
    "parent": parent,
    "auth": {
      "threshold": 1,
      "keys": [],
      "accounts": [
        {
          "permission": {
            "actor": session.auth.actor,
            "permission": "active"
          },
          "weight": 1
        }
      ],
      "waits": []
    },
    "max_fee": auth_update_fee
  }
  },abi);
  return action;
}

async function createKeyPair() {
  privKey = AnchorLink.PrivateKey.generate('K1');
  pubKey = privKey.toPublic();
  privKeyWif = privKey.toWif();
  fio_public_key = pubKey.toLegacyString('FIO');
  keyPair = {
    private:privKeyWif,
    public:fio_public_key
  };
  //console.log(keyPair);
  return keyPair;
}

/* BEGIN BORROWED CODE */

// via https://gist.github.com/bellbind/1f07f94e5ce31557ef23dc2a9b3cc2e1
// Bitcoin Base58 encoder/decoder algorithm
const btcTable = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
console.assert(btcTable.length === 58);

// Base58 decoder/encoder for BigInt
function b58ToBi(chars, table = btcTable) {
  const carry = BigInt(table.length);
  let total = 0n, base = 1n;
  for (let i = chars.length - 1; i >= 0; i--) {
    const n = table.indexOf(chars[i]);
    if (n < 0) throw TypeError(`invalid letter contained: '${chars[i]}'`);
    total += base * BigInt(n);
    base *= carry;
  }
  return total;
}
function biToB58(num, table = btcTable) {
  const carry = BigInt(table.length);
  let r = [];
  while (num > 0n) {
    r.unshift(table[num % carry]);
    num /= carry;
  }
  return r;
}

// Base58 decoder/encoder for bytes
function b58decode(str, table = btcTable) {
  const chars = [...str];
  const trails = chars.findIndex(c => c !== table[0]);
  const head0s = Array(trails).fill(0);
  if (trails === chars.length) return Uint8Array.from(head0s);
  const beBytes = [];
  let num = b58ToBi(chars.slice(trails), table);
  while (num > 0n) {
    beBytes.unshift(Number(num % 256n));
    num /= 256n;
  }
  return Uint8Array.from(head0s.concat(beBytes));
}

function b58encode(beBytes, table = btcTable) {
  if (!(beBytes instanceof Uint8Array)) throw TypeError(`must be Uint8Array`);
  const trails = beBytes.findIndex(n => n !== 0);
  const head0s = table[0].repeat(trails);
  if (trails === beBytes.length) return head0s;
  const num = beBytes.slice(trails).reduce((r, n) => r * 256n + BigInt(n), 0n);
  return head0s + biToB58(num, table).join("");
}

// via https://github.com/fioprotocol/fiojs/blob/649368f5540aec35914082eb929399401456ab91/src/accountname.ts#L16

function stringFromUInt64T(temp) {
  var charmap = ".12345abcdefghijklmnopqrstuvwxyz".split('');

  var str = new Array(13);
  str[12] = charmap[Long.fromValue(temp, true).and(0x0f)];

  temp = Long.fromValue(temp, true).shiftRight(4);
  for (var i = 1; i <= 12; i++) {
      var c = charmap[Long.fromValue(temp, true).and(0x1f)];
      str[12 - i] = c;
      temp = Long.fromValue(temp, true).shiftRight(5);
  }
  var result = str.join('');
  if (result.length > 12) {
      result = result.substring(0, 12);
  }
  return result;
}

function shortenKey(key) {
  var res = Long.fromValue(0, true);
  var temp = Long.fromValue(0, true);
  var toShift = 0;
  var i = 1;
  var len = 0;

  while (len <= 12) {
      //assert(i < 33, "Means the key has > 20 bytes with trailing zeroes...")
      temp = Long.fromValue(key[i], true).and(len == 12 ? 0x0f : 0x1f);
      if (temp == 0) {
          i+=1
          continue
      }
      if (len == 12){
        toShift = 0;
      }
      else{
        toShift = (5 * (12 - len) - 1);
      }
      temp = Long.fromValue(temp, true).shiftLeft(toShift);

      res = Long.fromValue(res, true).or(temp);
      len+=1
      i+=1
  }

  return res;
}

/* END BORROWED CODE */


/* BEGIN ANCHOR METHODS */

// tries to restore session, called when document is loaded
function restoreSession() {
    link.restoreSession(identifier).then((result) => {
        session = result;
        if (session) {
            didLogin();
        }
    })
}

// login and store session if sucessful
function login() {
    link.login(identifier).then((identity) => {
      //console.log(JSON.stringify(identity.proof));
      jQuery("#identity_proof").val(JSON.stringify(identity.proof));
      session = identity.session;
      didLogin();
      jQuery("#login").submit();
    });
}

// logout and remove session from storage
function logout() {
    jQuery("#actor").val("");
    session.remove();
}

// called when session was restored or created
function didLogin() {
    jQuery("#actor").val(session.auth.actor);
}

/* END ANCHOR METHODS */
