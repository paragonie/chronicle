# Chronicle

[![Build Status](https://travis-ci.org/paragonie/chronicle.svg?branch=master)](https://travis-ci.org/paragonie/chronicle)
[![Latest Stable Version](https://poser.pugx.org/paragonie/chronicle/v/stable)](https://packagist.org/packages/paragonie/chronicle)
[![Latest Unstable Version](https://poser.pugx.org/paragonie/chronicle/v/unstable)](https://packagist.org/packages/paragonie/chronicle)
[![License](https://poser.pugx.org/paragonie/chronicle/license)](https://packagist.org/packages/paragonie/chronicle)

**Chronicle** is a self-hostable microservice, built with [Slim Framework](https://www.slimframework.com),
which enables authorized users to commit arbitrary data to an immutable,
append-only public ledger.

Chronicle is superior to "blockchain" solutions for most real-world
technical problems that don't involve proofs-of-work or Byzantine fault
tolerance.

More precisely, Chronicle is a self-hostable microservice exposing an append-only,
cryptographically-secure hash chain data structure that accepts arbitrary
data from authorized clients through an HTTP API, secured by [Sapient](https://github.com/paragonie/sapient),
that can be used as a building block for building a cryptographic audit trail
similar to [Certificate Transparency](https://www.certificate-transparency.org/).

> [Chronicle will make you question the need for blockchain technology](https://paragonie.com/blog/2017/07/chronicle-will-make-you-question-need-for-blockchain-technology).

Chronicle was developed by [Paragon Initiative Enterprises](https://paragonie.com)
as part of our continued efforts to make the Internet more secure.

## What does Chronicle do?

Chronicle allows trusted clients to send data to be included in an immutable,
auditable, cryptographic permanent record.

## What problems do Chronicle solve?

### Chain of Custody

If you have sensitive information, you can write metadata about client access
times to a private Chronicle in order to have verifiable, tamper-resistant
proof that specific records were accessed by specific user accounts at a
specific time.

### Proof of Knowledge

By inserting an encrypted message and then revealing the key at a later date,
you can provide strong evidence of prior knowledge.

### Userbase Consistency Verification

For building a [secure code delivery](https://defuse.ca/triangle-of-secure-code-delivery.htm) system,
committing some metadata and a SHA256 or BLAKE2 hash of each update file to
a publicly verifiable Chronicle allows users to compile a whitelist of known
update files to help block trojan horse malware (in the event of a compromised
update server).

For best results, combine with cryptographic signatures (which may also be
registered in the Chronicle) and reproducible builds.

## How does it work?

All communications are secured with [Sapient](https://github.com/paragonie/sapient).
All messages are committed to a hash chain data structure backed by BLAKE2b, which
we call [Blakechain](https://github.com/paragonie/blakechain) for short.

There are two hashes for each message:

1. The hash of the current message, whose BLAKE2b key is the previous message's
   block. This is just called `currhash` internally.
2. The summary hash, which is a BLAKE2b hash of all message hashes to date,
   concatenated together in order. This is called `summaryhash` internally.

The rationale for using the previous message's hash was to add a degree of domain
separation in the event that a BLAKE2b collision attack is ever discovered. The
keying should reduce the likelihood of any practical attacks, especially if the
chain is updated rapdily.

## How to Install Chronicle

1. Clone this repository: `git clone git@github.com:paragonie/chronicle.git`
2. Run `composer install`
3. Run `bin/install.php` to generate a keypair and basic configuration file.
4. Edit `local/settings.json` to configure your Chronicle. For example, you
   can choose a MySQL, PostgreSQL, or SQLite backend.
5. Run `bin/make-tables.php` to setup the database tables 
6. Configure a new virtual host for Apache/nginx/etc. to point to the `public`
   directory, **OR** run `composer start` to launch the built-in web server.

### How to add clients

First, you'll need the client's Ed25519 public key.

```sh
php bin/create-client.php \
    --publickey=[the base64url-encoded public key] \
    --comment=[any comment you want to use to remember them by]
```

You can also specify `--administrator` if you wiish to allow this client to add/remove
other clients from the API. (It is not possible to add or remove administrators through
the API, only normal clients.)