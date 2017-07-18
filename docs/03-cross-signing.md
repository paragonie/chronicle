# Cross-Signing onto other Chronicles

The process is relatively simple:

1. Obtain your server's public key. This can be obtained by visiting 
   `/chronicle`.
2. Send your public key to the person who operates the other Chronicle and request
   access as a client.
3. Configure cross-signing on your client.
4. (Optional) set up a cronjob that runs `bin/scheduled-tasks.php` regularly.

To perform step three, you need only run the following command:

```bash
php bin/cross-sign.php \
    --url=http://target-chronicle \
    --publickey=[public key of target chronicle] \
    --name=[whatever you want to refer to it] \
    # One or both of the options below:
```

You must also specify one or both of the following options:

* `--push-after` (integer) - Push the latest hash to this Chronicle if you've
  performed this many hashes since the last push.
* `--push-days` (integer) - Push the latest hash to this Chronicle if this many
  days have passed since the last push.

We recommend setting up a cron job to ensure cross-signing is happening
regularly if your cross-signing policy is `--push-days` and not `--push-after`.
For example, this will run the scheduled tasks every 15 minutes:

```cron
*/15 * * * * /path/to/chronicle/bin/scheduled-tasks.php
```
