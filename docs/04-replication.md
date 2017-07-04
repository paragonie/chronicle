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
