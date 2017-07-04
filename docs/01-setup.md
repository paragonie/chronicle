# How to Install Chronicle

1. Clone this repository: `git clone git@github.com:paragonie/chronicle.git`
2. Run `composer install`
3. Run `bin/install.php` to generate a keypair and basic configuration file.
4. Edit `local/settings.json` to configure your Chronicle. For example, you
   can choose a MySQL, PostgreSQL, or SQLite backend.
5. Run `bin/make-tables.php` to setup the database tables 
6. Configure a new virtual host for Apache/nginx/etc. to point to the `public`
   directory, **OR** run `composer start` to launch the built-in web server.

If you don't have Composer, [get it here](https://getcomposer.org/download/).

## How to add clients to your Chronicle

First, you'll need the client's Ed25519 public key.

```sh
php bin/create-client.php \
    --publickey=[the base64url-encoded public key] \
    --comment=[any comment you want to use to remember them by]
```

You can also specify `--administrator` if you wiish to allow this client to add/remove
other clients from the API. (It is not possible to add or remove administrators through
the API, only normal clients.)

### Generating Client Keys

First, run `bin/keygen.php`. You should get something like this (the example below contains
a valid keypair, but don't use it! Use your own keys instead):

```json
{
    "secret-key": "ouSEaSX_MvsQk_LJGDP-HHX2uLkBxEhYOFAe6J3_sZKAhA68DFVsXbMt5qchkNB7tLaAGxpt_Eze8_yyMEj_Tw==",
    "public-key": "gIQOvAxVbF2zLeanIZDQe7S2gBsabfxM3vP8sjBI_08="
}
```

You want to keep your secret-key, well, **secret**! Your public key can safely
be given out to Chronicles.
