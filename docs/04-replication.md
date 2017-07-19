# Chronicle Replication

An individual Chronicle can replicate multiple other Chronicle instances.
The setup process is simpler than cross-signing.

First, run the following command:

```bash
php bin/replicate.php \
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

If you have replication enabled, your Chronicle will periodically write a summary
of all replicated Chronicles onto itself. To change the frequency, change the
`scheduled-attestation` setting in `local/settings.json`. A valid frequency looks
like `"7 days"` or `"1 week + 3 days"`. [Relevant PHP Documentation](http://php.net/manual/en/dateinterval.createfromdatestring.php).
 
To disable scheduled attestation, simply remove the `scheduled-attestation` directive
in `local/settings.json`. Alternatively, set it to `null` or `false`.
