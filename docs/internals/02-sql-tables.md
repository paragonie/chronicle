# SQL Tables

## Contents

* [Overview](#overview)
* [Main Tables](#main-tables)
  * [chronicle_clients](#chronicle_clients)
  * [chronicle_chain](#chronicle_chain)
* [Remote Tables](#remote-tables)
  * [chronicle_xsign_targets](#chronicle_xsign_targets)
  * [chronicle_replication_sources](#chronicle_replication_sources)
  * [chronicle_replication_chain](#chronicle_replication_chain)

## Overview

Chronicle allows multiple database backends. Since some backends do not support
some of the features that others do, the table definitions might look slightly
different. However, the end result should be functionally similar on any RDBMS.

## Main Tables

### chronicle_clients

This contains information about the various clients that are authorized to
publish data through Chronicle.

#### Fields

* **`id`**: Primary key. Should be an unsigned big integer (or equivalent).
* **`publicid`**: This is sort of like a "username", although is tied to a
  specific Ed25519 keypair rather than an individual user. When a new client
  is created, Chronicle generates a random Public ID and returns it.
* **`publickey`**: The actual Ed25519 public key.
* **`isAdmin`**: (boolean) Can this client perform the following operations?
  * Create a new client from the REST API
  * Revoke an existing non-admin client's access from the REST API
* **`comment`**: A human-readable memo field, not required to be populated.
* **`created`**: Timestamp of record creation.
* **`modified`**: Timestamp of last record modification. 

### chronicle_chain

This contains the actual Chronicle hash-chain data structure contents.

#### Fields

* **`id`**: Primary key. Should be an unsigned big integer (or equivalent).
* **`data`**: The actual text contents of this record. It should be a TEXT
  or BLOB field (whichever is more appropriate for your RDBMS).
* **`prevhash`**: The previous record's `currhash`. Nullable.
* **`currhash`**: The [current hash](01-design-philosophy.md#cryptographic-design).
* **`hashstate`**: The current BLAKE2b hash state (we cache this so the summary hash
  can be calculated quicker).
* **`summaryhash`**: The [summary hash](01-design-philosophy.md#cryptographic-design).
* **`publickey`**: The public key of the client used to publish this record.
  We keep a copy of this here in case the client is erased.
* **`signature`**: The Ed25519 signature of `data` that can be validated against
  `publickey`.
* **`created`**: Timestamp of record creation.

## Replication Tables

### chronicle_xsign_targets

This table contains the configuration for cross-signing.

#### Fields

* **`id`**: Primary key. Should be an unsigned big integer (or equivalent).
* **`name`**: A human-readable name for this target server.
* **`url`**: The base URL for the target Chronicle.
* **`clientid`**: The client ID we should use to write to this server.
* **`publickey`**: The remote Chronicle's public key.
* **`policy`**: A JSON blob containing the cross-signing policy.
* **`lastrun`**: When the last cross-sign was run.

### chronicle_replication_sources

* **`id`**: Primary key. Should be an unsigned big integer (or equivalent).
* **`uniqueid`**: Random string. Used as an API parameter for the REST API.
* **`name`**: A human-readable name for this target server.
* **`url`**: The base URL for the target Chronicle.
* **`publickey`**: The remote Chronicle's public key.

### chronicle_replication_chain

* **`id`**: Primary key. Should be an unsigned big integer (or equivalent).
* **`source`**: See `chronicle_replication_sources.id`
* **`data`**: The actual text contents of this record. It should be a TEXT
  or BLOB field (whichever is more appropriate for your RDBMS).
* **`prevhash`**: The previous record's `currhash`. Nullable.
* **`currhash`**: The [current hash](01-design-philosophy.md#cryptographic-design).
* **`hashstate`**: The current BLAKE2b hash state (we cache this so the summary hash
  can be calculated quicker).
* **`summaryhash`**: The [summary hash](01-design-philosophy.md#cryptographic-design).
* **`publickey`**: The public key of the client used to publish this record.
  We keep a copy of this here in case the client is erased.
* **`signature`**: The Ed25519 signature of `data` that can be validated against
  `publickey`.
* **`created`**: Timestamp of record creation (upstream).
* **`replicated`**: Timestamp of record creation (local).
