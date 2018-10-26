# Concurrent Instances

Since version 1.1.0, Chronicle allows multiple instances to be
installed in the same database and document root. **This is an
optional feature.**

To set up your instance, add an entry to local/settings.json like so:

```json
{
    "instances": {
        "instance_name": "table_prefix"
    }
}
```

Next, run the following:

```terminal
php bin/make-tables.php -i instance_name
# Equivalent:
# php bin/make-tables.php --instance=instance_name
```

You can then run the other scripts (`create-client.php`, etc.) with the
`-i` flag.

Finally, when making HTTP requests with the REST API, add the `instance` 
query parameter to the request URI.

    /chronicle/publish?instance=instance_name
    /chronicle/export?instance=instance_name
