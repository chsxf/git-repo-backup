# About This Project

`git-repo-backup` is a command-line tool to manage git repository backups.

At the moment, it works only with GitHub or BitBucket Cloud.

## Conventions

This project uses [gitmoji](https://gitmoji.dev) for its commit messages.

# Installation

## Requirements

- [PHP](https://www.php.net/) 8.1+ with cURL extension enabled
- [Composer](https://getcomposer.org)

## Installing with Composer

### Installing Globally

We recommend installing this package globally.

```
composer global require chsxf/git-repo-backup
```

If not already, you need to add the global composer `bin` directory to your `PATH` environment variable.

### Installing Locally

But you can also install it locally if it better fits your setup.

```
composer require chsxf/git-repo-backup
```

The tool will be installed in the local `vendor` folder and the executable script can be called with the `vendor/bin/git-repo-backup` command.

## Updating with Composer

If installed globally, you can run `composer global update chsxf/git-repo-backup`.

If installed locally, simply run `composer update chsxf/git-repo-backup` in the folder where the tool was previously installed.

# Usage

```
git-repo-backup
    --username <username>
    --password <password>
    --platform (github|bitbucket)
    --clone-protocol (https|ssh)
    [--no-git-lfs]
    [--dest-dir <destination-path>]
    [--ssh-key <ssh-key-path>]
    [--exclude <excluded-repositories>]
    [--dry-run]
    [--sort-by (size|name) (asc|desc)]
```

## Required Parameters

| Parameter        | Description                                                                                                                                               |
| ---------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `username`       | **GitHub**: User name used to authenticate with the platform's API<br>**BitBucket Cloud**: Workspace (user or organization) for which to get repositories |
| `password`       | See [Passwords](#passwords) section below                                                                                                                 |
| `platform`       | Platform on which the repositories are hosted<br><br>Accepted values:<br><ul><li>`github`</li><li>`bitbucket` (BitBucket Cloud)</li></ul>                 |
| `clone-protocol` | Protocol to use when cloning/fetching the repositories<br><br>Accepted values:<br><ul><li>`ssh` (recommended)</li><li>`https`</li></ul>                   |

## Optional Parameters

| Parameter    | Description                                                                                                                                                                                                                                                                                                     |
| ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `no-git-lfs` | Skip availability test for Git LFS. Use this setting if you don't use Git LFS with any of your repositories.                                                                                                                                                                                                    |
| `dest-dir`   | Destination path to store the repository backups. If not set, the script stores backups in current working directory.                                                                                                                                                                                           |
| `ssh-key`    | **Not supported on Windows**<br>Specific SSH key to use with repositories, useful if you have several SSH keys for the same domain.<br>The specific path will be passed to git commands thanks to the `core.sshCommand` config.<br>Ignored if `--clone-protocol` is set to `https`                              |
| `exclude`    | Comma-separated list of excluded repositories<br>Each entry can be either an exact match if containing only alphanumerical characters, hyphens and underscores, or a case-insensitive Perl-Compatible Regular Expression otherwise                                                                              |
| `dry-run`    | If present, no clone or fetch/pull operation is done, and only repositories information are reported                                                                                                                                                                                                            |
| `sort-by`    | Specify how repositories are sorted before being processed.<br>Repositories can be sorted by `name` (default) or by `size`.<br>Order can ascending (`asc` - default) or descending (`desc`).<br><br>Accepted values:<br><ul><li>`name asc`</li><li>`name desc`</li><li>`size asc`</li><li>`size desc`</li></ul> |

## Passwords

The `password` value differs from one platform to another.

On GitHub, the password must be a [personal access token](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/creating-a-personal-access-token).

On BitBucket Cloud, the password must be an [app password](https://bitbucket.org/account/settings/app-passwords/).

# Planned Improvements

- [ ] Support GitHub organizations
- [ ] Allow the use of a configuration file

# License

This repository is distributed under the [MIT License](LICENSE).
