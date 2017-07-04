#!/usr/bin/env bash

# Make sure you run `composer start` first!
php tests/cli/start.php
php tests/cli/commands/private-endpoints.php
php tests/cli/commands/public-endpoints.php
