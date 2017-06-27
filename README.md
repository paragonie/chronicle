# Chronicle

Chronicle is superior to "blockchain" solutions for most real-world
technical problems that don't involve proofs-of-work or Byzantine fault
tolerance.

More precisely, Chronicle is a self-hostable microservice exposing an append-only,
cryptographically-secure hash chain data structure that accepts arbitrary
data from authorized clients through an HTTP API, secured by [Sapient](https://github.com/paragonie/sapient),
that can be used as a building block for building a cryptographic audit trail
similar to [Certificate Transparency](https://www.certificate-transparency.org/).

## What does Chronicle do?

Chronicle allows trusted clients to send data to be included in an immutable,
auditable, cryptographic permanent record.

## What problem does Chronicle solve?

### Chain of Custody

If you have sensitive information, you can write metadata about client access
times to a private Chronicle in order to have verifiable, tamper-resistant
proof that specific records were accessed by specific user accounts at a
specific time.

### Userbase Consistency Verification

For building a [secure code delivery](https://defuse.ca/triangle-of-secure-code-delivery.htm) system,
committing some metadata and a SHA256 or BLAKE2 hash of each update file to
a publicly verifiable Chronicle allows users to compile a whitelist of known
update files to help block trojan horse malware (in the event of a compromised
update server).

For best results, combine with cryptographic signatures (which may also be
registered in the Chronicle) and reproducible builds.
