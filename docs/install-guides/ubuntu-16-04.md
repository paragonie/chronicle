# Installing Chronicle on Ubuntu 16.04

## Operating System Packages

First, you'll need the Ondrej PPA:

```bash
apt-get install software-properties-common
add-apt-repository ppa:ondrej/php
```

Next, choose whether you want PHP 7.0 or 7.1:

```bash
apt-get update
apt-get install php7.1 \
    php7.1-common \
    php7.1-dev \
    php7.1-fpm \
    php7.1-mbstring \
    php7.1-sqlite \
    php7.1-xml \
    php7.1-zip
```

(Feel free to substitute `php7.1` with `php7.0` and `sqlite` with your preferred database driver.)

Next, you'll need to install Caddy, Apache, nginx, or some other webserver.

> Please refer to the documentation for your favorite webserver software for
setting up and configuring the virtualhost for your Chronicle. Make sure you
also setup LetsEncrypt for automatic HTTPS, if you're not using Caddy.

It's highly recommended that you [install libsodium](https://paragonie.com/book/pecl-libsodium/read/00-intro.md#installing-libsodium)
if you're using a version of PHP earlier than 7.2.

## Installing Chronicle

Once you have a working webserver configuration, you can begin to setup Chronicle.

```bash
LATESTVERSION="v0.5.0"
cd /var/www

# If our GPG key is already imported, do nothing
gpg --fingerprint 7F52D5C61D1255C731362E826B97A1C2826404DA
if [ $? -ne 0 ]; then
    # Get our GPG public key
    gpg --keyserver pgp.mit.edu --recv-keys 7F52D5C61D1255C731362E826B97A1C2826404DA
    if [ $? -ne 0 ]; then
        # Failed to download GPG public key, let's pull it from our website.
        wget https://paragonie.com/static/gpg-public-key.txt
        gpg --import gpg-public-key.txt
        rm gpg-public-key.txt
        
        gpg --fingerprint 7F52D5C61D1255C731362E826B97A1C2826404DA
        if [ $? -ne 0 ]; then
            echo "Something went wrong. The GPG public key we downloaded was not Paragon's!"
            exit 1
        fi
    fi
fi

git clone https://github.com/paragonie/chronicle.git
cd chronicle
git tag -v $LATESTVERSION
if [ $? -ne 0 ]; then
    echo "Invalid tag."
    exit 1
fi
git checkout $LATESTVERSION
```

Next, you will need to [get Composer](https://getcomposer.org/download/)
(if you do not already have it), and then run `composer install`.

### First-Run Configuration

First, run `php bin/install.php` to generate your server's keypair and create
a basic configuration file. Then, edit `local/settings.json`.

Once you're ready, run `php bin/make-tables.php` to populate the SQL databases.
If you're using SQLite, make sure `local` (and everything in it) is owned by
`www-data`.

-----

Nothing else is specific to Ubuntu. Refer to the [general setup instructions](../01-setup.md)
for the remaining steps.
