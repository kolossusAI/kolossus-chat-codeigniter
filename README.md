# KolossusAI Chat for CodeIgniter

Add your KolossusAI chat assistant to a CodeIgniter 4 site with one command. Works with
CodeIgniter 4.4 and newer (PHP 8.1+; CodeIgniter 4.7 needs PHP 8.2).

## Install

Run this in your CodeIgniter project folder (the one with `spark`):

```sh
composer require kolossusai/chat-codeigniter
```

CodeIgniter finds the package by itself (auto-discovery) and it adds the chat bubble to
every page; nothing in your project changes. With no token set, the chat is the
[free FAQ assistant](#free-faq-assistant-no-kolossus-account-needed).

Then add your widget token (from Kolossus: your website widget's **Plugins → CodeIgniter**)
to your environment variables (usually the `.env` file, or your server's settings):

```dotenv
KOLOSSUS_CHAT_WIDGET_TOKEN=wt_YOUR_TOKEN
```

That's it: every page now shows your own chat. Also add your site's domain to the widget's
**allowed domains** in Kolossus, or the chat will not open there.

- No `.env` yet? Copy the `env` file CodeIgniter comes with to `.env`.
- If `.env` already has a `KOLOSSUS_CHAT_WIDGET_TOKEN` line, change it rather than adding a
  second one (CodeIgniter uses the first).
- On your live server, set your token the same way. Without a token there, the live site
  shows the free FAQ assistant.

## Free FAQ assistant (no Kolossus account needed)

No Kolossus account yet? Use the free FAQ assistant. It answers your visitors'
questions about your website: products, prices, policies, opening hours,
contact details and more.

```sh
composer require kolossusai/chat-codeigniter
```

That's it: every page now shows the chat bubble.

**What you get**

* Answers based on your website's public pages.
* **10 free messages per visitor.**
* Visitors chat anonymously.

**Good to know**

* It works on your live website. On `localhost` it can't answer yet.
* It can't answer about orders, accounts or anything behind a login.
* You can change the colour, layout, bubble corner, size and more: see [Look](#look) below.

**Need more?** For more messages or your own trained agent, contact the
KolossusAI team at [kolossusai.in](https://kolossusai.in), then add your widget token to
your environment variables (usually the `.env` file): `KOLOSSUS_CHAT_WIDGET_TOKEN=wt_YOUR_TOKEN`

## Put the chat where you want it

The bubble is added to every HTML page just before `</body>`, by a filter
(`kolossusChat`) the package appends to `Config\Filters::$globals['after']` when the
app starts; your own filter list is kept as it is. To place the chat yourself instead,
load the helper and use it in your views:

```php
helper('kolossus_chat');   // or list 'kolossus_chat' in app/Config/Autoload.php $helpers
```

```php
<?= kolossus_chat() ?>                                  <!-- the chat bubble -->
<?= kolossus_chat_button('Chat with us', 'btn btn-primary') ?>   <!-- a button that opens the chat -->
<?= kolossus_chat_script() ?>                           <!-- only the widget script -->
```

A page that uses one of them never gets a second, automatic bubble. To turn the
automatic bubble off everywhere, set `KOLOSSUS_CHAT_SITEWIDE=false`.

Pages are left untouched when they are not full HTML pages (JSON, files, downloads,
errors, redirects), and for AJAX requests and htmx, Turbo or Inertia partial updates.
To skip more pages, add `app/Config/KolossusChat.php`:

```php
<?php

namespace Config;

class KolossusChat extends \KolossusAI\ChatCodeIgniter\Config\KolossusChat
{
    public array $excludePaths = ['admin', 'admin/*', 'checkout/*'];
}
```

`php spark filter:check` does not list `kolossusChat`: it is added when a page is served,
not in the config file.

## Logged-in users

With [CodeIgniter Shield](https://shield.codeigniter.com), set your widget secret (from
the same widget in Kolossus) to let the chat know who a logged-in user is:

```dotenv
KOLOSSUS_CHAT_WIDGET_SECRET=ws_YOUR_SECRET
```

The secret stays on your server. Only a signature (HMAC-SHA256) of the user's email is
sent to the browser, with their email and username.

## Look

These override the widget's own settings from Kolossus; leave them out to keep those.

| `.env` key | Values |
|---|---|
| `KOLOSSUS_CHAT_THEME` | `light`, `dark` |
| `KOLOSSUS_CHAT_PRIMARY_COLOR` | a hex colour, like `#aaf684` |
| `KOLOSSUS_CHAT_LAYOUT` | `bubble`, `sidebar`, `bottom_bar` |
| `KOLOSSUS_CHAT_OPEN_ON_LOAD` | `remember` (default), `open`, `closed` |
| `KOLOSSUS_CHAT_POSITION` | `right`, `left` (bubble corner) |
| `KOLOSSUS_CHAT_OFFSET_X` / `KOLOSSUS_CHAT_OFFSET_Y` | 0–1000, pixels from the side / bottom edge |
| `KOLOSSUS_CHAT_BUBBLE_SIZE` | `small`, `medium`, `large` |

Invalid values are ignored and logged as a warning in your CodeIgniter log.

If your app turned on CodeIgniter's Content Security Policy (`Config\App::$CSPEnabled`),
the widget script gets the page's nonce. Your policy must also allow your Kolossus Host URL
in `script-src` and `connect-src` (with its `wss://` address, for the live chat connection).

## Remove

```sh
composer remove kolossusai/chat-codeigniter
```

The package changes no file of yours, so that's all. Delete the `KOLOSSUS_CHAT_` lines
from `.env` too if you like.

## Self-hosted Kolossus

On a Kolossus server other than the cloud, also set its address:

```dotenv
KOLOSSUS_CHAT_HOST_URL=https://kolossus.example.com
```

`https://` only (plain `http://` works for `localhost` while testing).

## Tests

```sh
composer install
vendor/bin/phpunit
```

The tests run real requests through CodeIgniter (its own test bootstrap).
