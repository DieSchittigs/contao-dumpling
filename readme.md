# 🥟 Contao Dumpling

## What it is

This is a bundle for Contao 4.4+. Dumpling is a toolkit intended for developers
who use a local development environment and want to pull (`database` and `/files`) from a
live/staging instance of the same installation.

## What it's not

Dumpling is **not** intended for keeping two Contao installations in sync. The target audience
is teams of developers where each individuum wants to be able to *pull* the live-state from the
Installation. This *should* **not** include source code, but only the following data:

- Everything in `/files`
- All database tables – *except* `tl_users`, `tl_members`, etc. which are blacklisted

## Features

- 🗄️ Pull the live `/files` directory into your local installation
- 🥦 Conveniently import live table data to your local installation
- 🍰 Push local database and file changes to your live server

## Installation

Install via Contao Manager or Composer:

    composer require dieschittigs/contao-dumpling-bundle

ℹ️ *Dumpling has to be installed on the live server **and** your local instance.*

### Setup the API-Key

On your live Contao installation you have to generate a Dumpling API key to protect your data.

Login to the Contao Backend. Navigate to **System** -> **Settings** and save the settings once.
The key can be found under **Security Settings**. That's all you have to do for the live server.

ℹ️ *Before saving the settings, no API key is set and Dumpling is unavailable.*

## Basic Usage

Dumpling setups additional commands for your Contao Console. On most systems, you 
may start it via:

    vendor/bin/contao-console

*or*
    
    php vendor/bin/contao-console

⚠️️ *All described commands are strictly intended for your local development environment.
They can and will **purge** tables and **overwrite** files!*

ℹ️ *Commands will ask you for the live URL and the Dumpling API key once. These will be saved to a file called `.dumpling-settings.json`.*

### `dumpling:pull`

Pull all tables from the live instance and **replace** the local tables with them.

### `dumpling:download`

Download all files (in `tl_files`) from the live instance and **replace** the local files with them.

### `dumpling:boostai`

Looks for the current auto-increment on imported tables and increases it by 25%. This is done because the
live table will probably change while you work on your local instance. Think of it as your *safety distance*.

### `dumpling:import`

Execute `dumpling:download`, `dumpling:pull` and `dumpling:boostai` in that order.

### `dumpling:push`

Executes insert statements on the live server that reflect local changes (every INSERT after your last pull).

⚠️️ *Updates on existing data has to be done manually.*

### `dumpling:upload`

Uploads all files that where added to `tl_files` after your last pull.

### Routes

tbd

#### [POST] `dumpling/`

##### Request

##### Response

MIT © [Die Schittigs](https://www.dieschittigs.de)
