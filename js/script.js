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

  $('#create_group_button').on('click', function(e) {
    if (!link) {
      alert("Please login using your FIO account and Anchor Wallet by Greymass.");
      return;
    }

    // TOOD: do validation on the form data

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

async function createGroupOnChain(keyPair, actor, domain, name) {
  const register_fio_domain_fee = await getFIOChainFee('register_fio_domain');
  const register_fio_address_fee = await getFIOChainFee('register_fio_address');
  const transfer_tokens_pub_key_fee = await getFIOChainFee('transfer_tokens_pub_key');
  const transfer_fio_domain_fee = await getFIOChainFee('transfer_fio_domain');
  const tpid = 'luke@stokes'
  const domain_action = {
    authorization: [session.auth],
    account: 'fio.address',
    name: 'regdomain',
    data: {
      fio_domain: domain,
      owner_fio_public_key: session.publicKey.toLegacyString("FIO"),
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
      owner_fio_public_key: session.publicKey.toLegacyString("FIO"),
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
      owner_fio_public_key: session.publicKey.toLegacyString("FIO"),
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
  const actions_result = await session.transact(
    {
      //actions: [domain_action,address_action,treasury_address_action,transfer_action]
      actions: [domain_action,address_action,transfer_action]
    }
  );
  /*
  if (!actions_result.transaction_id) {
    console.log(actions_result);
    return ;
  }
  */
  console.log("Domain created, address created, tokens transfered")
  console.log(actions_result.transaction_id);

  const permission_update_result = await updatePermissionsOfNewlyCreatedAcccount(keyPair, actor);
  /*
  if (!permission_update_result.transaction_id) {
    console.log(permission_update_result);
    alert("There was an error setting up your group. Please make sure you have enough FIO Tokens. Check the console for details.")
    return ;
  }
  */
  console.log("Permissions updated for new account at " + keyPair.public)
  console.log(permission_update_result.transaction_id);

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
  const transfer_domain_result = await session.transact(
    {
      //actions: [transfer_domain_action, transfer_treasury_action]
      action: transfer_domain_action
    }
  );
  /*
  if (!transfer_domain_result.transaction_id) {
    alert("There was an error transferring your domain. Please make sure you have enough FIO Tokens. Check the console for details.")
    console.log(transfer_domain_result);
    return ;
  }
  */
  console.log("Domain transfered to " + keyPair.public)
  console.log(transfer_domain_result.transaction_id);
  $('#create_group').submit();
}


function FIOToSUF(amount) {
  return (amount * 1000000000);
}
function SUFToFIO(amount) {
  return (amount / 1000000000);
}

/*
async function updateFeeDisplay() {
  const register_fio_domain_fee = await getFIOChainFee('register_fio_domain');
  const transfer_tokens_pub_key_fee = await getFIOChainFee('transfer_tokens_pub_key');
*/

async function getABI(account_name) {
  const response = await fetch(link.chains[0].client.provider.url + '/v1/chain/get_abi', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      account_name: account_name
    })
  })
  const data = await response.json()
  console.log(data.abi);
  return data.abi;
}

async function getFIOChainFee(endpoint) {
  const response = await fetch(link.chains[0].client.provider.url + '/v1/chain/get_fee', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      end_point: endpoint,
      fio_address: '',
    })
  })
  const data = await response.json()
  fee = data.fee + (data.fee * .1);
  return fee;
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
