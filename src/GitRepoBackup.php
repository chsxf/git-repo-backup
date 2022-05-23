<?php

namespace chsxf\GitRepoBackup;

use chsxf\GitRepoBackup\PlatformHandlers\BitBucketPlatformHandler;
use chsxf\GitRepoBackup\PlatformHandlers\GitHubPlatformHandler;
use chsxf\GitRepoBackup\PlatformHandlers\AbstractPlatformHandler;

class GitRepoBackup
{
    private const VERSION = '1.0.0';

    private static array $platformHandlerClasses = [
        'github' => GitHubPlatformHandler::class,
        'bitbucket' => BitBucketPlatformHandler::class
    ];

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
        $platform = CommandLineParser::getArgumentValue(CommandLineArgumentName::platform);
        Console::log("Running with platform: %s", $platform);

        $username = CommandLineParser::getArgumentValue(CommandLineArgumentName::username);
        $password = CommandLineParser::getArgumentValue(CommandLineArgumentName::password);

        $platformHandlerClass = self::$platformHandlerClasses[$platform];
        $platformHandler = new $platformHandlerClass($username, $password);
        self::proceedWithPlatformHandler($platformHandler);
    }

    private static function proceedWithPlatformHandler(AbstractPlatformHandler $platformHandler)
    {
        Console::empty();
        Console::log('Fetching repository list...');

        $repositories = $platformHandler->fetchRepositoryList();
        if ($repositories === false) {
            Console::error('Fetching repository list failed');
        } else {
            Console::success('Found %d repositories', count($repositories));
        }
    }
}
