<?php

namespace chsxf\GitRepoBackup;

class GitRepoBackup
{
    private const VERSION = '1.0.0';

    public static function run()
    {
        Console::logWithColor(ConsoleColor::Bright, 'Git Repo Backup Utility');
        Console::log('Version: %s', self::VERSION);
        Console::log('PHP Version: %s', PHP_VERSION);

        Console::empty();
        if (!CommandLineParser::parse()) {
            Console::empty();
            CommandLineParser::showUsage();
            exit();
        }
        Console::success('Arguments parsed successfully');

        Console::empty();
        if (!GitHandler::checkGitAvailability()) {
            exit();
        }

        Console::empty();
        Console::log("Running with platform: %s", CommandLineParser::getArgumentValue(CommandLineArgumentName::platform));
    }
}
