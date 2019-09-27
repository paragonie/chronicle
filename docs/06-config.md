# Chronicle Configuration

## Pagination

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
