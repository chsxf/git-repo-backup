<?php

namespace chsxf;

class GitRepoBackup
{
    private const VERSION = '1.0.0';

    public static function run()
    {
        Console::log('Git Repo Backup utility');
        Console::log('Version: %s', self::VERSION);
        Console::log('PHP Version: %s', PHP_VERSION);
        Console::empty();

        if (!GitHandler::checkGitAvailability()) {
            exit();
        }
    }
}
