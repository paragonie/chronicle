# Design Philosophy

## Contents

* [Chronicle Explained](#chronicle-explained)
  * [What Chronicle Is](#what-chronicle-is)
  * [What Chronicle Isn't](#what-chronicle-isnt)
* [Mostly Centralized](#mostly-centralized)
* [Cryptographic Design](#cryptographic-design)
  * [Why Implement a Hash Chain Instead of a Merkle Tree?](#why-implement-a-hash-chain-instead-of-a-merkle-tree)

## Chronicle Explained

The main purpose of Chronicle's design was to enable technologists
to propose a non-blockchain solution when [blockchainiacs](https://tonyarcieri.com/on-the-dangers-of-a-blockchain-monoculture)
sink their talons into an otherwise well-intended project.

Specifically, Chronicle is narrowly focused on systems with a limited
number of writers in a mostly-centralized topology where it makes
good engineering sense to implement decentralized verification of
the central hub.

Any attempt to turn Chronicle into a cryptocurrency will be rejected.

In a similar vein, any proposals to mak Chronicle "trust-less" or have
multiple branching paths will most likely not be considered.

### What Chronicle Is

* Chronicle is a microservice that exposes a REST API
* Chronicle is an append-only, immutable data storage
  * You cannot delete or modify records after they are committed
* Chronicle's publishing model is centralized
* Chronicle's verification model is decentralized

### What Chronicle Isn't

* Anonymous (towards publishers)
* Cryptocurrency
* Blockchain

## Mostly Centralized

Although Chronicle favors centralized systems (i.e. single writer, or
very few writers all publishing to the same server), its trust model
favors decentralized verification.

How this works is simple:

* Anyone can run a **replica** of another Chronicle, which mirrors all
  of its contents locally (verifying each entry as it copies them over).
* Chronicles can be configured to **cross-sign** their latest hashes
  onto another Chronicle.

If you combine these two features (replication and cross-signing), it's
very easy to compose a system that is resilient to targeted attacks:

* **Replication** is inherently anonymous from the server's perspective,
  and any clients that would consume the canon Chronicle's contents can
  easily be pointed instead towards a local replica instance.
* **Cross-signing** inserts copies of the latest metadata into another
  server's Chronicle (which may also be replicated any number of times).

These two features make it extremely easy for **anyone** to conclusively
verify that a given Chronicle's contents were not changed after-the-fact.

That's it. There's nothing more to our security model. It allows you to
digitally sign and timestamp messages, which can in turn be used to build
various security features.

## Cryptographic Design

Chronicle uses a hash-chain data structure built with the BLAKE2b hash
function. Specifically, two hashes are calculated for each record:

1. The `current hash`, which is the BLAKE2b hash of the current record,
   keyed with the *previous* record's `current hash`.
2. The `summary hash`, which is a BLAKE2b hash of all the messages
   received in order. 
   * For optimization purposes, the `hash state` of each record is also
     stored in the database.

In effect, this ties each record not only to the immediate previous entry
(since the previous hash is used as the BLAKE2b key of the current hash,
recursively), but also to the entire immutable history since the first
block (since the `summary hash` is a hash of all the Chronicle's contents
and metadata, concatenated together).

### Why Implement a Hash Chain Instead of a Merkle Tree?

While we don't discount the usefulness of Merkle Trees, properly implemented
Merkle trees (i.e. domain separation for leaf nodes to prevent duplicate
record injection, or double-spending attacks in cryptocurrency) forces writers
to rehash up to half the entire history in order to calculate the Merkle root.

()This assumes that the child node of the first half of the history is cached
for performance purposes. If it's not, you have to rehash the entire history
every time you append a record.)

When you have many gigabytes of data stored in the Chronicle, the performance
implications of rehashing half of recent history becomes clear.

A hash chain data structure consisting of two hashes (`current hash` and 
`summary hash`) allows us to optimize writes without slowing down reads:
 
You only need the previous record's `current hash` and the cached
`hash state` in order to calculate the hashes for a new record.

And yet, because of how each hash is constructed, so long as BLAKE2 remains
unbroken, you can guarantee the append-only nature of this data structure. 

(For anyone not familiar, BLAKE2 is at least as secure as SHA3 but faster
than MD5 on 64-bit platforms.)

Furthermore, since Chronicle's publishing model is centralized (while
verification is decentralized), we don't need the other advantages of
Merkle trees. A simple hash-chain suffices.

In short: Our hash-chain data structure affords us the same relevant
security properties you'd expect of an equivalent system implemented
with Merkle trees, while being **significantly faster** with large
histories.
