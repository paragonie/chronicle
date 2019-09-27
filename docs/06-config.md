# Chronicle Configuration

## HTTP Response Caching

Caching was implemented since version 1.2.0.

If your Chronicle instance is receiving a lot of traffic, it may be
beneficial to cache HTTP responses for a few seconds.

To enable caching, first you will need to install the **Memcached**
extension from PECL. 

(There are [instructions available online for installing Memcached on Debian](https://serverpilot.io/docs/how-to-install-the-php-memcache-extension).
If you need instructions for your operating system, please inquire with their support team.)

Next, edit `local/settings.json` and set `"cache"` to an integer greater than 0
to cache responses for a given number of seconds.

It's recommended to set this to a value greater than `1` but less than `60`.
Some applications may dislike responses that are more than 1 minute old.

```json5
{
    // ...
    "cache": 15,
    // ...
}
```

## Pagination

Pagination was implemented since version 1.2.0.

To enable pagination, edit `local/settings.json` and set `"paginate-export"`
to an integer value greater than 0.

```json5
{
    // ...
    "paginate-export": 15,
    // ...
}
```

If you set it to `false`, `null`, or `0`, it will treat it as disabled.
If you erase the key from the JSON file, it will also disable pagination.
