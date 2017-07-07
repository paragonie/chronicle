# Chronicle Replication

An individual Chronicle can replicate multiple other Chronicle instances.
The setup process is simpler than cross-signing.

First, run the following command:

```bash
php bin/cross-sign.php \
    --url=http://target-chronicle \
    --publickey=[public key of target chronicle] \
    --name=[whatever you want to refer to it]
```

Then, make sure you set up a cron job to run `bin/scheduled-tasks.php` at a
regular interval (e.g. every 15 minutes):

```cron
*/15 * * * * /path/to/chronicle/bin/scheduled-tasks.php
```

## How to Access a Replicated Chronicle

Visit `https://your-chronicle-domain/chronicle/replica` to see an index of all
other Chronicles being replicated. Each entry should have a list of URLs that
can be accessed to query the replicated data.

## Scheduled Attestation

To enable scheduled attestation, update your `local/settings.json` file and add
one of the following directives:

* `attest-days` (integer) - Push a message to the local Chronicle that reports
  the current replication status if this many days have transpired.
