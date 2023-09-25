# Platform.sh and Silverstripe config

This module is to take every environment variable set in platform.sh,
and make it accessible to the Silverstripe configuration/environment.

## Installation

`composer require pikselin/platform-sh`

Copy (manually, sorry), `vendor/pikselin/platform-sh/.environment` to your project root.

This bash script will set the environment variable based on what's set in the Platform console for
`SS_ENVIRONMENT_TYPE`

## Usage

In your `_config.php`, init the platform-sh:

```php
<?php

use Pikselin\Platform\PlatformService;

PlatformService::init();
```

## Environment hardening.

To prevent environment variables from leaking, the allowed environment
variables are listed and checked.

Note that explicitly the environment type is excluded in the list of allowed variables.

This is to avoid doubling up after it's already been set by the shell script.

To add your own list of allowed keys, create a YML like so:

```yaml
---
name: 'my-allowed-envs'
after: 'platformsh-config'
---
Pikselin\Platform\PlatformService:
  env_variables:
    - MY_ADDITIONAL_KEY
```

If you're adding the `SS_MFA_SECRET_KEY`, you'll have to add this the following line to the .environment file too:
```yaml
export SS_MFA_SECRET_KEY=$(echo $PLATFORM_VARIABLES | base64 --decode | jq -r ".SS_MFA_SECRET_KEY")
```

It's possible other .env variables work that way too.

## Troubleshooting

If you're getting a server error or you find something like this in the logs
`Uncaught Exception TypeError: "in_array(): Argument #2 ($haystack) must be of type array, null given"`

SSH into server
```
platform ssh
```
And remove the cache manually
```
rm -rf silverstripe-cache/web/*
```
Even though it is in the build/deploy hooks, it seems like they need to be run manually.
