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

    console.log(keyPair);

    pubkey = keyPair.public.substring('FIO'.length, keyPair.public.length);
    const decoded58 = b58decode(pubkey);
    const long = shortenKey(decoded58);
    const actor = stringFromUInt64T(long);

    $("#group_fio_public_key").val(keyPair.public);
    $("#group_account").val(actor);

    createAccountAndTransferStartupFunds(keyPair, actor);
  });

});

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
  const response = await fetch(link.chains[0].client.provider.url + '/v1/chain/get_raw_abi', {
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
async function transferFunds(fio_public_key, amount, max_fee, ) {
    const action = {
        account: 'fio.token',
        name: 'trnsfiopubky',
        authorization: [session.auth],
        data: {
            payee_public_key: fio_public_key,
            amount: amount,
            max_fee: max_fee,
            tpid: 'luke@stokes',
            actor: session.auth.actor
        }
    }
    const result = await session.transact({action});
    console.log(result);
    console.log(result.processed.id);
    console.log(fio_public_key);
    return result.processed.id;
}

async function createAccountAndTransferStartupFunds(keyPair, actor) {
  const register_fio_address_fee = await getFIOChainFee('register_fio_address');
  const register_fio_domain_fee = await getFIOChainFee('register_fio_domain');
  //register_fio_domain_fee = await getFIOChainFee('register_fio_domain');
  // TESTING
  //register_fio_domain_fee = 1581368308;
  const transfer_tokens_pub_key_fee = await getFIOChainFee('transfer_tokens_pub_key');
  const transaction_id = await transferFunds(
    keyPair.public,
    (register_fio_domain_fee + register_fio_address_fee),
    transfer_tokens_pub_key_fee
    );
  console.log(transaction_id);

  const register_result = await registerDomainAndAddress(
    keyPair,
    actor,
    $("#domain").val(),
    $("#creator_member_name").val(),
    register_fio_address_fee,
    register_fio_domain_fee);

  // QUESTION? Should we register the domain and user BEFORE updating account permissions? Maybe update owner first?

  const permission_update_result = await updatePermissionsOfNewlyCreatedAcccount(keyPair, actor);

  $('#create_group').submit();

}

async function registerDomainAndAddress(keyPair, actor, domain, name, register_fio_address_fee, register_fio_domain_fee) {
  const info = await link.client.v1.chain.get_info()
  const header = info.getTransactionHeader()
  const domain_action = getRegisterDomainAction(
    actor,
    domain,
    keyPair.public,
    register_fio_domain_fee);
  const address_action = getRegisterAddressAction(
    actor,
    name + "@" + domain,
    session.publicKey.toLegacyString("FIO"),
    register_fio_address_fee);
  const signedTransaction = getSignedTransaction(
    header,
    keyPair,
    [domain_action, address_action],
    info);
  const result = await link.client.v1.chain.push_transaction(signedTransaction)
  console.log(result);

  return result;
}

async function updatePermissionsOfNewlyCreatedAcccount(keyPair, actor) {
  const auth_update_fee = await getFIOChainFee('auth_update');
  //const abi = await getABI('eosio');
  const info = await link.client.v1.chain.get_info()
  const header = info.getTransactionHeader()
  const upate_owner_action = getUpdateAuthAction(actor,"owner","", auth_update_fee);
  const upate_active_action = getUpdateAuthAction(actor,"active","owner", auth_update_fee);
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

function getRegisterDomainAction(actor, domain, owner_fio_public_key, max_fee) {
  const action = AnchorLink.Action.from({
  "authorization": [
      {
          "actor": actor,
          "permission": 'active',
      },
  ],
  "account": 'fio.address',
  "name": 'regdomain',
  "data": {
    "fio_domain": domain,
    "owner_fio_public_key": owner_fio_public_key,
    "max_fee": max_fee,
    "actor": actor,
    "tpid": ""
  }
  },fioaddress_abi);
  return action;
}
function getRegisterAddressAction(actor, fio_address, owner_fio_public_key, max_fee) {
  const action = AnchorLink.Action.from({
  "authorization": [
      {
          "actor": actor,
          "permission": 'active',
      },
  ],
  "account": 'fio.address',
  "name": 'regaddress',
  "data": {
    "fio_address": fio_address,
    "owner_fio_public_key": owner_fio_public_key,
    "max_fee": max_fee,
    "actor": actor,
    "tpid": ""
  }
  },fioaddress_abi);
  return action;
}
function getUpdateAuthAction(actor, permission, parent, auth_update_fee) {
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
  },eosio_abi);
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

// TODO: pull this from the chain.

eosio_abi = {
  "version": "eosio::abi/1.1",
  "types": [],
  "structs": [{
      "name": "abi_hash",
      "base": "",
      "fields": [{
          "name": "owner",
          "type": "name"
        },{
          "name": "hash",
          "type": "checksum256"
        }
      ]
    },{
      "name": "addaction",
      "base": "",
      "fields": [{
          "name": "action",
          "type": "name"
        },{
          "name": "contract",
          "type": "string"
        },{
          "name": "actor",
          "type": "name"
        }
      ]
    },{
      "name": "addgenlocked",
      "base": "",
      "fields": [{
          "name": "owner",
          "type": "name"
        },{
          "name": "periods",
          "type": "lockperiods[]"
        },{
          "name": "canvote",
          "type": "bool"
        },{
          "name": "amount",
          "type": "int64"
        }
      ]
    },{
      "name": "addlocked",
      "base": "",
      "fields": [{
          "name": "owner",
          "type": "name"
        },{
          "name": "amount",
          "type": "int64"
        },{
          "name": "locktype",
          "type": "int16"
        }
      ]
    },{
      "name": "authority",
      "base": "",
      "fields": [{
          "name": "threshold",
          "type": "uint32"
        },{
          "name": "keys",
          "type": "key_weight[]"
        },{
          "name": "accounts",
          "type": "permission_level_weight[]"
        },{
          "name": "waits",
          "type": "wait_weight[]"
        }
      ]
    },{
      "name": "block_header",
      "base": "",
      "fields": [{
          "name": "timestamp",
          "type": "uint32"
        },{
          "name": "producer",
          "type": "name"
        },{
          "name": "confirmed",
          "type": "uint16"
        },{
          "name": "previous",
          "type": "checksum256"
        },{
          "name": "transaction_mroot",
          "type": "checksum256"
        },{
          "name": "action_mroot",
          "type": "checksum256"
        },{
          "name": "schedule_version",
          "type": "uint32"
        },{
          "name": "new_producers",
          "type": "producer_schedule?"
        }
      ]
    },{
      "name": "blockchain_parameters",
      "base": "",
      "fields": [{
          "name": "max_block_net_usage",
          "type": "uint64"
        },{
          "name": "target_block_net_usage_pct",
          "type": "uint32"
        },{
          "name": "max_transaction_net_usage",
          "type": "uint32"
        },{
          "name": "base_per_transaction_net_usage",
          "type": "uint32"
        },{
          "name": "net_usage_leeway",
          "type": "uint32"
        },{
          "name": "context_free_discount_net_usage_num",
          "type": "uint32"
        },{
          "name": "context_free_discount_net_usage_den",
          "type": "uint32"
        },{
          "name": "max_block_cpu_usage",
          "type": "uint32"
        },{
          "name": "target_block_cpu_usage_pct",
          "type": "uint32"
        },{
          "name": "max_transaction_cpu_usage",
          "type": "uint32"
        },{
          "name": "min_transaction_cpu_usage",
          "type": "uint32"
        },{
          "name": "max_transaction_lifetime",
          "type": "uint32"
        },{
          "name": "deferred_trx_expiration_window",
          "type": "uint32"
        },{
          "name": "max_transaction_delay",
          "type": "uint32"
        },{
          "name": "max_inline_action_size",
          "type": "uint32"
        },{
          "name": "max_inline_action_depth",
          "type": "uint16"
        },{
          "name": "max_authority_depth",
          "type": "uint16"
        }
      ]
    },{
      "name": "burnaction",
      "base": "",
      "fields": [{
          "name": "fioaddrhash",
          "type": "uint128"
        }
      ]
    },{
      "name": "canceldelay",
      "base": "",
      "fields": [{
          "name": "canceling_auth",
          "type": "permission_level"
        },{
          "name": "trx_id",
          "type": "checksum256"
        }
      ]
    },{
      "name": "crautoproxy",
      "base": "",
      "fields": [{
          "name": "proxy",
          "type": "name"
        },{
          "name": "owner",
          "type": "name"
        }
      ]
    },{
      "name": "deleteauth",
      "base": "",
      "fields": [{
          "name": "account",
          "type": "name"
        },{
          "name": "permission",
          "type": "name"
        },{
          "name": "max_fee",
          "type": "uint64"
        }
      ]
    },{
      "name": "eosio_global_state",
      "base": "blockchain_parameters",
      "fields": [{
          "name": "last_producer_schedule_update",
          "type": "block_timestamp_type"
        },{
          "name": "last_pervote_bucket_fill",
          "type": "time_point"
        },{
          "name": "pervote_bucket",
          "type": "int64"
        },{
          "name": "perblock_bucket",
          "type": "int64"
        },{
          "name": "total_unpaid_blocks",
          "type": "uint32"
        },{
          "name": "total_voted_fio",
          "type": "int64"
        },{
          "name": "thresh_voted_fio_time",
          "type": "time_point"
        },{
          "name": "last_producer_schedule_size",
          "type": "uint16"
        },{
          "name": "total_producer_vote_weight",
          "type": "float64"
        },{
          "name": "last_name_close",
          "type": "block_timestamp_type"
        },{
          "name": "last_fee_update",
          "type": "block_timestamp_type"
        }
      ]
    },{
      "name": "eosio_global_state2",
      "base": "",
      "fields": [{
          "name": "last_block_num",
          "type": "block_timestamp_type"
        },{
          "name": "total_producer_votepay_share",
          "type": "float64"
        },{
          "name": "revision",
          "type": "uint8"
        }
      ]
    },{
      "name": "eosio_global_state3",
      "base": "",
      "fields": [{
          "name": "last_vpay_state_update",
          "type": "time_point"
        },{
          "name": "total_vpay_share_change_rate",
          "type": "float64"
        }
      ]
    },{
      "name": "incram",
      "base": "",
      "fields": [{
          "name": "accountmn",
          "type": "name"
        },{
          "name": "amount",
          "type": "int64"
        }
      ]
    },{
      "name": "inhibitunlck",
      "base": "",
      "fields": [{
          "name": "owner",
          "type": "name"
        },{
          "name": "value",
          "type": "uint32"
        }
      ]
    },{
      "name": "init",
      "base": "",
      "fields": [{
          "name": "version",
          "type": "varuint32"
        },{
          "name": "core",
          "type": "symbol"
        }
      ]
    },{
      "name": "key_weight",
      "base": "",
      "fields": [{
          "name": "key",
          "type": "public_key"
        },{
          "name": "weight",
          "type": "uint16"
        }
      ]
    },{
      "name": "linkauth",
      "base": "",
      "fields": [{
          "name": "account",
          "type": "name"
        },{
          "name": "code",
          "type": "name"
        },{
          "name": "type",
          "type": "name"
        },{
          "name": "requirement",
          "type": "name"
        },{
          "name": "max_fee",
          "type": "uint64"
        }
      ]
    },{
      "name": "locked_token_holder_info",
      "base": "",
      "fields": [{
          "name": "owner",
          "type": "name"
        },{
          "name": "total_grant_amount",
          "type": "uint64"
        },{
          "name": "unlocked_period_count",
          "type": "uint32"
        },{
          "name": "grant_type",
          "type": "uint32"
        },{
          "name": "inhibit_unlocking",
          "type": "uint32"
        },{
          "name": "remaining_locked_amount",
          "type": "uint64"
        },{
          "name": "timestamp",
          "type": "uint32"
        }
      ]
    },{
      "name": "locked_tokens_info",
      "base": "",
      "fields": [{
          "name": "id",
          "type": "int64"
        },{
          "name": "owner_account",
          "type": "name"
        },{
          "name": "lock_amount",
          "type": "int64"
        },{
          "name": "payouts_performed",
          "type": "int32"
        },{
          "name": "can_vote",
          "type": "int32"
        },{
          "name": "periods",
          "type": "lockperiods[]"
        },{
          "name": "remaining_lock_amount",
          "type": "int64"
        },{
          "name": "timestamp",
          "type": "uint32"
        }
      ]
    },{
      "name": "lockperiods",
      "base": "",
      "fields": [{
          "name": "duration",
          "type": "int64"
        },{
          "name": "percent",
          "type": "float64"
        }
      ]
    },{
      "name": "newaccount",
      "base": "",
      "fields": [{
          "name": "creator",
          "type": "name"
        },{
          "name": "name",
          "type": "name"
        },{
          "name": "owner",
          "type": "authority"
        },{
          "name": "active",
          "type": "authority"
        }
      ]
    },{
      "name": "onblock",
      "base": "",
      "fields": [{
          "name": "header",
          "type": "block_header"
        }
      ]
    },{
      "name": "onerror",
      "base": "",
      "fields": [{
          "name": "sender_id",
          "type": "uint128"
        },{
          "name": "sent_trx",
          "type": "bytes"
        }
      ]
    },{
      "name": "permission_level",
      "base": "",
      "fields": [{
          "name": "actor",
          "type": "name"
        },{
          "name": "permission",
          "type": "name"
        }
      ]
    },{
      "name": "permission_level_weight",
      "base": "",
      "fields": [{
          "name": "permission",
          "type": "permission_level"
        },{
          "name": "weight",
          "type": "uint16"
        }
      ]
    },{
      "name": "producer_info",
      "base": "",
      "fields": [{
          "name": "id",
          "type": "uint64"
        },{
          "name": "owner",
          "type": "name"
        },{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "addresshash",
          "type": "uint128"
        },{
          "name": "total_votes",
          "type": "float64"
        },{
          "name": "producer_public_key",
          "type": "public_key"
        },{
          "name": "is_active",
          "type": "bool"
        },{
          "name": "url",
          "type": "string"
        },{
          "name": "unpaid_blocks",
          "type": "uint32"
        },{
          "name": "last_claim_time",
          "type": "time_point"
        },{
          "name": "last_bpclaim",
          "type": "uint32"
        },{
          "name": "location",
          "type": "uint16"
        }
      ]
    },{
      "name": "producer_key",
      "base": "",
      "fields": [{
          "name": "producer_name",
          "type": "name"
        },{
          "name": "block_signing_key",
          "type": "public_key"
        }
      ]
    },{
      "name": "producer_schedule",
      "base": "",
      "fields": [{
          "name": "version",
          "type": "uint32"
        },{
          "name": "producers",
          "type": "producer_key[]"
        }
      ]
    },{
      "name": "regproducer",
      "base": "",
      "fields": [{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "fio_pub_key",
          "type": "string"
        },{
          "name": "url",
          "type": "string"
        },{
          "name": "location",
          "type": "uint16"
        },{
          "name": "actor",
          "type": "name"
        },{
          "name": "max_fee",
          "type": "int64"
        }
      ]
    },{
      "name": "regproxy",
      "base": "",
      "fields": [{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "actor",
          "type": "name"
        },{
          "name": "max_fee",
          "type": "int64"
        }
      ]
    },{
      "name": "remaction",
      "base": "",
      "fields": [{
          "name": "action",
          "type": "name"
        },{
          "name": "actor",
          "type": "name"
        }
      ]
    },{
      "name": "resetclaim",
      "base": "",
      "fields": [{
          "name": "producer",
          "type": "name"
        }
      ]
    },{
      "name": "rmvproducer",
      "base": "",
      "fields": [{
          "name": "producer",
          "type": "name"
        }
      ]
    },{
      "name": "setabi",
      "base": "",
      "fields": [{
          "name": "account",
          "type": "name"
        },{
          "name": "abi",
          "type": "bytes"
        }
      ]
    },{
      "name": "setautoproxy",
      "base": "",
      "fields": [{
          "name": "proxy",
          "type": "name"
        },{
          "name": "owner",
          "type": "name"
        }
      ]
    },{
      "name": "setcode",
      "base": "",
      "fields": [{
          "name": "account",
          "type": "name"
        },{
          "name": "vmtype",
          "type": "uint8"
        },{
          "name": "vmversion",
          "type": "uint8"
        },{
          "name": "code",
          "type": "bytes"
        }
      ]
    },{
      "name": "setparams",
      "base": "",
      "fields": [{
          "name": "params",
          "type": "blockchain_parameters"
        }
      ]
    },{
      "name": "setpriv",
      "base": "",
      "fields": [{
          "name": "account",
          "type": "name"
        },{
          "name": "is_priv",
          "type": "uint8"
        }
      ]
    },{
      "name": "top_prod_info",
      "base": "",
      "fields": [{
          "name": "producer",
          "type": "name"
        }
      ]
    },{
      "name": "unlinkauth",
      "base": "",
      "fields": [{
          "name": "account",
          "type": "name"
        },{
          "name": "code",
          "type": "name"
        },{
          "name": "type",
          "type": "name"
        }
      ]
    },{
      "name": "unlocktokens",
      "base": "",
      "fields": [{
          "name": "actor",
          "type": "name"
        }
      ]
    },{
      "name": "unregprod",
      "base": "",
      "fields": [{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "actor",
          "type": "name"
        },{
          "name": "max_fee",
          "type": "int64"
        }
      ]
    },{
      "name": "unregproxy",
      "base": "",
      "fields": [{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "actor",
          "type": "name"
        },{
          "name": "max_fee",
          "type": "int64"
        }
      ]
    },{
      "name": "updateauth",
      "base": "",
      "fields": [{
          "name": "account",
          "type": "name"
        },{
          "name": "permission",
          "type": "name"
        },{
          "name": "parent",
          "type": "name"
        },{
          "name": "auth",
          "type": "authority"
        },{
          "name": "max_fee",
          "type": "uint64"
        }
      ]
    },{
      "name": "updatepower",
      "base": "",
      "fields": [{
          "name": "voter",
          "type": "name"
        },{
          "name": "updateonly",
          "type": "bool"
        }
      ]
    },{
      "name": "updlbpclaim",
      "base": "",
      "fields": [{
          "name": "producer",
          "type": "name"
        }
      ]
    },{
      "name": "updlocked",
      "base": "",
      "fields": [{
          "name": "owner",
          "type": "name"
        },{
          "name": "amountremaining",
          "type": "uint64"
        }
      ]
    },{
      "name": "updtrevision",
      "base": "",
      "fields": [{
          "name": "revision",
          "type": "uint8"
        }
      ]
    },{
      "name": "user_resources",
      "base": "",
      "fields": [{
          "name": "owner",
          "type": "name"
        },{
          "name": "net_weight",
          "type": "asset"
        },{
          "name": "cpu_weight",
          "type": "asset"
        },{
          "name": "ram_bytes",
          "type": "int64"
        }
      ]
    },{
      "name": "voteproducer",
      "base": "",
      "fields": [{
          "name": "producers",
          "type": "string[]"
        },{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "actor",
          "type": "name"
        },{
          "name": "max_fee",
          "type": "int64"
        }
      ]
    },{
      "name": "voteproxy",
      "base": "",
      "fields": [{
          "name": "proxy",
          "type": "string"
        },{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "actor",
          "type": "name"
        },{
          "name": "max_fee",
          "type": "int64"
        }
      ]
    },{
      "name": "voter_info",
      "base": "",
      "fields": [{
          "name": "id",
          "type": "uint64"
        },{
          "name": "fioaddress",
          "type": "string"
        },{
          "name": "addresshash",
          "type": "uint128"
        },{
          "name": "owner",
          "type": "name"
        },{
          "name": "proxy",
          "type": "name"
        },{
          "name": "producers",
          "type": "name[]"
        },{
          "name": "last_vote_weight",
          "type": "float64"
        },{
          "name": "proxied_vote_weight",
          "type": "float64"
        },{
          "name": "is_proxy",
          "type": "bool"
        },{
          "name": "is_auto_proxy",
          "type": "bool"
        },{
          "name": "reserved2",
          "type": "uint32"
        },{
          "name": "reserved3",
          "type": "asset"
        }
      ]
    },{
      "name": "wait_weight",
      "base": "",
      "fields": [{
          "name": "wait_sec",
          "type": "uint32"
        },{
          "name": "weight",
          "type": "uint16"
        }
      ]
    }
  ],
  "actions": [{
      "name": "addaction",
      "type": "addaction",
      "ricardian_contract": ""
    },{
      "name": "addgenlocked",
      "type": "addgenlocked",
      "ricardian_contract": ""
    },{
      "name": "addlocked",
      "type": "addlocked",
      "ricardian_contract": ""
    },{
      "name": "burnaction",
      "type": "burnaction",
      "ricardian_contract": ""
    },{
      "name": "canceldelay",
      "type": "canceldelay",
      "ricardian_contract": ""
    },{
      "name": "crautoproxy",
      "type": "crautoproxy",
      "ricardian_contract": ""
    },{
      "name": "deleteauth",
      "type": "deleteauth",
      "ricardian_contract": ""
    },{
      "name": "incram",
      "type": "incram",
      "ricardian_contract": ""
    },{
      "name": "inhibitunlck",
      "type": "inhibitunlck",
      "ricardian_contract": ""
    },{
      "name": "init",
      "type": "init",
      "ricardian_contract": ""
    },{
      "name": "linkauth",
      "type": "linkauth",
      "ricardian_contract": ""
    },{
      "name": "newaccount",
      "type": "newaccount",
      "ricardian_contract": ""
    },{
      "name": "onblock",
      "type": "onblock",
      "ricardian_contract": ""
    },{
      "name": "onerror",
      "type": "onerror",
      "ricardian_contract": ""
    },{
      "name": "regproducer",
      "type": "regproducer",
      "ricardian_contract": ""
    },{
      "name": "regproxy",
      "type": "regproxy",
      "ricardian_contract": ""
    },{
      "name": "remaction",
      "type": "remaction",
      "ricardian_contract": ""
    },{
      "name": "resetclaim",
      "type": "resetclaim",
      "ricardian_contract": ""
    },{
      "name": "rmvproducer",
      "type": "rmvproducer",
      "ricardian_contract": ""
    },{
      "name": "setabi",
      "type": "setabi",
      "ricardian_contract": ""
    },{
      "name": "setautoproxy",
      "type": "setautoproxy",
      "ricardian_contract": ""
    },{
      "name": "setcode",
      "type": "setcode",
      "ricardian_contract": ""
    },{
      "name": "setparams",
      "type": "setparams",
      "ricardian_contract": ""
    },{
      "name": "setpriv",
      "type": "setpriv",
      "ricardian_contract": ""
    },{
      "name": "unlinkauth",
      "type": "unlinkauth",
      "ricardian_contract": ""
    },{
      "name": "unlocktokens",
      "type": "unlocktokens",
      "ricardian_contract": ""
    },{
      "name": "unregprod",
      "type": "unregprod",
      "ricardian_contract": ""
    },{
      "name": "unregproxy",
      "type": "unregproxy",
      "ricardian_contract": ""
    },{
      "name": "updateauth",
      "type": "updateauth",
      "ricardian_contract": ""
    },{
      "name": "updatepower",
      "type": "updatepower",
      "ricardian_contract": ""
    },{
      "name": "updlbpclaim",
      "type": "updlbpclaim",
      "ricardian_contract": ""
    },{
      "name": "updlocked",
      "type": "updlocked",
      "ricardian_contract": ""
    },{
      "name": "updtrevision",
      "type": "updtrevision",
      "ricardian_contract": ""
    },{
      "name": "voteproducer",
      "type": "voteproducer",
      "ricardian_contract": ""
    },{
      "name": "voteproxy",
      "type": "voteproxy",
      "ricardian_contract": ""
    }
  ],
  "tables": [{
      "name": "abihash",
      "index_type": "i64",
      "key_names": [],
      "key_types": [],
      "type": "abi_hash"
    },{
      "name": "global",
      "index_type": "i64",
      "key_names": [],
      "key_types": [],
      "type": "eosio_global_state"
    },{
      "name": "global2",
      "index_type": "i64",
      "key_names": [],
      "key_types": [],
      "type": "eosio_global_state2"
    },{
      "name": "global3",
      "index_type": "i64",
      "key_names": [],
      "key_types": [],
      "type": "eosio_global_state3"
    },{
      "name": "lockedtokens",
      "index_type": "i64",
      "key_names": [],
      "key_types": [],
      "type": "locked_token_holder_info"
    },{
      "name": "locktokens",
      "index_type": "i64",
      "key_names": [],
      "key_types": [],
      "type": "locked_tokens_info"
    },{
      "name": "producers",
      "index_type": "i64",
      "key_names": [],
      "key_types": [],
      "type": "producer_info"
    },{
      "name": "topprods",
      "index_type": "i64",
      "key_names": [],
      "key_types": [],
      "type": "top_prod_info"
    },{
      "name": "userres",
      "index_type": "i64",
      "key_names": [],
      "key_types": [],
      "type": "user_resources"
    },{
      "name": "voters",
      "index_type": "i64",
      "key_names": [],
      "key_types": [],
      "type": "voter_info"
    }
  ],
  "ricardian_clauses": [],
  "error_messages": [],
  "abi_extensions": [],
  "variants": []
}

fioaddress_abi = {
  "version": "eosio::abi/1.0",
  "types": [],
  "structs": [{
      "name": "fioname",
      "base": "",
      "fields": [{
          "name": "id",
          "type": "uint64"
        },{
          "name": "name",
          "type": "string"
        },{
          "name": "namehash",
          "type": "uint128"
        },{
          "name": "domain",
          "type": "string"
        },{
          "name": "domainhash",
          "type": "uint128"
        },{
          "name": "expiration",
          "type": "uint64"
        },{
          "name": "owner_account",
          "type": "name"
        },{
          "name": "addresses",
          "type": "tokenpubaddr[]"
        },{
          "name": "bundleeligiblecountdown",
          "type": "uint64"
        }
      ]
    },{
      "name": "domain",
      "base": "",
      "fields": [{
          "name": "id",
          "type": "uint64"
        },{
          "name": "name",
          "type": "string"
        },{
          "name": "domainhash",
          "type": "uint128"
        },{
          "name": "account",
          "type": "name"
        },{
          "name": "is_public",
          "type": "uint8"
        },{
          "name": "expiration",
          "type": "uint64"
        }
      ]
    },{
      "name": "eosio_name",
      "base": "",
      "fields": [{
          "name": "account",
          "type": "name"
        },{
          "name": "clientkey",
          "type": "string"
        },{
          "name": "keyhash",
          "type": "uint128"
        }
      ]
    },{
      "name": "regaddress",
      "base": "",
      "fields": [{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "owner_fio_public_key",
          "type": "string"
        },{
          "name": "max_fee",
          "type": "int64"
        },{
          "name": "actor",
          "type": "name"
        },{
          "name": "tpid",
          "type": "string"
        }
      ]
    },{
      "name": "tokenpubaddr",
      "base": "",
      "fields": [{
          "name": "token_code",
          "type": "string"
        },{
          "name": "chain_code",
          "type": "string"
        },{
          "name": "public_address",
          "type": "string"
        }
      ]
    },{
      "name": "addaddress",
      "base": "",
      "fields": [{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "public_addresses",
          "type": "tokenpubaddr[]"
        },{
          "name": "max_fee",
          "type": "int64"
        },{
          "name": "actor",
          "type": "name"
        },{
          "name": "tpid",
          "type": "string"
        }
      ]
    },{
      "name": "remaddress",
      "base": "",
      "fields": [{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "public_addresses",
          "type": "tokenpubaddr[]"
        },{
          "name": "max_fee",
          "type": "int64"
        },{
          "name": "actor",
          "type": "name"
        },{
          "name": "tpid",
          "type": "string"
        }
      ]
    },{
      "name": "remalladdr",
      "base": "",
      "fields": [{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "max_fee",
          "type": "int64"
        },{
          "name": "actor",
          "type": "name"
        },{
          "name": "tpid",
          "type": "string"
        }
      ]
    },{
      "name": "regdomain",
      "base": "",
      "fields": [{
          "name": "fio_domain",
          "type": "string"
        },{
          "name": "owner_fio_public_key",
          "type": "string"
        },{
          "name": "max_fee",
          "type": "int64"
        },{
          "name": "actor",
          "type": "name"
        },{
          "name": "tpid",
          "type": "string"
        }
      ]
    },{
      "name": "renewdomain",
      "base": "",
      "fields": [{
          "name": "fio_domain",
          "type": "string"
        },{
          "name": "max_fee",
          "type": "int64"
        },{
          "name": "tpid",
          "type": "string"
        },{
          "name": "actor",
          "type": "name"
        }
      ]
    },{
      "name": "renewaddress",
      "base": "",
      "fields": [{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "max_fee",
          "type": "int64"
        },{
          "name": "tpid",
          "type": "string"
        },{
          "name": "actor",
          "type": "name"
        }
      ]
    },{
      "name": "setdomainpub",
      "base": "",
      "fields": [{
          "name": "fio_domain",
          "type": "string"
        },{
          "name": "is_public",
          "type": "int8"
        },{
          "name": "max_fee",
          "type": "int64"
        },{
          "name": "actor",
          "type": "name"
        },{
          "name": "tpid",
          "type": "string"
        }
      ]
    },{
      "name": "burnexpired",
      "base": "",
      "fields": []
    },{
      "name": "decrcounter",
      "base": "",
      "fields": [{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "step",
          "type": "int32"
        }
      ]
    },{
      "name": "bind2eosio",
      "base": "",
      "fields": [{
          "name": "account",
          "type": "name"
        },{
          "name": "client_key",
          "type": "string"
        },{
          "name": "existing",
          "type": "bool"
        }
      ]
    },{
      "name": "burnaddress",
      "base": "",
      "fields": [{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "max_fee",
          "type": "int64"
        },{
          "name": "tpid",
          "type": "string"
        },{
          "name": "actor",
          "type": "name"
        }
      ]
    },{
      "name": "xferdomain",
      "base": "",
      "fields": [{
          "name": "fio_domain",
          "type": "string"
        },{
          "name": "new_owner_fio_public_key",
          "type": "string"
        },{
          "name": "max_fee",
          "type": "int64"
        },{
          "name": "actor",
          "type": "name"
        },{
          "name": "tpid",
          "type": "string"
        }
      ]
    },{
      "name": "xferaddress",
      "base": "",
      "fields": [{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "new_owner_fio_public_key",
          "type": "string"
        },{
          "name": "max_fee",
          "type": "int64"
        },{
          "name": "actor",
          "type": "name"
        },{
          "name": "tpid",
          "type": "string"
        }
      ]
    },{
      "name": "addbundles",
      "base": "",
      "fields": [{
          "name": "fio_address",
          "type": "string"
        },{
          "name": "bundle_sets",
          "type": "int64"
        },{
          "name": "max_fee",
          "type": "int64"
        },{
          "name": "tpid",
          "type": "string"
        },{
          "name": "actor",
          "type": "name"
        }
      ]
    }
  ],
  "actions": [{
      "name": "decrcounter",
      "type": "decrcounter",
      "ricardian_contract": ""
    },{
      "name": "regaddress",
      "type": "regaddress",
      "ricardian_contract": ""
    },{
      "name": "addaddress",
      "type": "addaddress",
      "ricardian_contract": ""
    },{
      "name": "remaddress",
      "type": "remaddress",
      "ricardian_contract": ""
    },{
      "name": "remalladdr",
      "type": "remalladdr",
      "ricardian_contract": ""
    },{
      "name": "regdomain",
      "type": "regdomain",
      "ricardian_contract": ""
    },{
      "name": "renewdomain",
      "type": "renewdomain",
      "ricardian_contract": ""
    },{
      "name": "renewaddress",
      "type": "renewaddress",
      "ricardian_contract": ""
    },{
      "name": "burnexpired",
      "type": "burnexpired",
      "ricardian_contract": ""
    },{
      "name": "setdomainpub",
      "type": "setdomainpub",
      "ricardian_contract": ""
    },{
      "name": "bind2eosio",
      "type": "bind2eosio",
      "ricardian_contract": ""
    },{
      "name": "burnaddress",
      "type": "burnaddress",
      "ricardian_contract": ""
    },{
      "name": "xferdomain",
      "type": "xferdomain",
      "ricardian_contract": ""
    },{
      "name": "xferaddress",
      "type": "xferaddress",
      "ricardian_contract": ""
    },{
      "name": "addbundles",
      "type": "addbundles",
      "ricardian_contract": ""
    }
  ],
  "tables": [{
      "name": "fionames",
      "index_type": "i64",
      "key_names": [
        "id"
      ],
      "key_types": [
        "string"
      ],
      "type": "fioname"
    },{
      "name": "domains",
      "index_type": "i64",
      "key_names": [
        "id"
      ],
      "key_types": [
        "string"
      ],
      "type": "domain"
    },{
      "name": "accountmap",
      "index_type": "i64",
      "key_names": [
        "account"
      ],
      "key_types": [
        "uint64"
      ],
      "type": "eosio_name"
    }
  ],
  "ricardian_clauses": [],
  "error_messages": [],
  "abi_extensions": [],
  "variants": []
}

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
