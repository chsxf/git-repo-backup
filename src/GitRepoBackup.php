<?php

namespace chsxf\GitRepoBackup;

use chsxf\GitRepoBackup\PlatformHandlers\BitBucketPlatformHandler;
use chsxf\GitRepoBackup\PlatformHandlers\GitHubPlatformHandler;
use chsxf\GitRepoBackup\PlatformHandlers\AbstractPlatformHandler;
use chsxf\GitRepoBackup\PlatformHandlers\RepositoryInfo;

enum UpdateStrategy: string
{
    case clone = 'Clone';
    case fetchPull = 'Fetch+Pull';
    case skipNotRepository = 'Skip - Local folder is not a git repository';
    case skipDifferentRemote = 'Skip - Different remote URLs';
    case skipDifferentBranch = 'Skip - Different branches';
}

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
        Console::log("in path: %s", self::getBasePath());

        $username = CommandLineParser::getArgumentValue(CommandLineArgumentName::username);
        $password = CommandLineParser::getArgumentValue(CommandLineArgumentName::password);

        $platformHandlerClass = self::$platformHandlerClasses[$platform];
        $platformHandler = new $platformHandlerClass($username, $password);
        self::proceedWithPlatformHandler($platformHandler);
    }

    private static function getBasePath(): string
    {
        return realpath(CommandLineParser::getArgumentValue(CommandLineArgumentName::destPath, defaultValue: getcwd()));
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
            Console::empty();

            foreach ($repositories as $repoInfo) {
                self::proceedForRepository($repoInfo);
            }
        }
    }

    private static function proceedForRepository(RepositoryInfo $repositoryInfo): bool
    {
        Console::empty();
        Console::log(str_pad('', 30, '-'));
        Console::print('Repository: ');
        Console::logWithColor(ConsoleColor::Bright, '%s', $repositoryInfo->name);
        Console::empty();

        $repositoryPath = self::getBasePath() . DIRECTORY_SEPARATOR . $repositoryInfo->name;
        $folderExists = is_dir($repositoryPath);
        if ($folderExists) {
            $strategy = self::selectStrategyForExistingFolder($repositoryInfo, $repositoryPath);
        } else {
            $strategy = UpdateStrategy::clone;
        }

        $cloneMethod = CommandLineParser::getArgumentValue(CommandLineArgumentName::cloneProtocol);
        $url = ($cloneMethod === 'ssh') ? $repositoryInfo->sshURL : $repositoryInfo->httpsURL;

        Console::increaseIndent();
        Console::log('Repository path: %s', $repositoryPath);
        Console::increaseIndent();
        Console::log('Folder exists? %s', $folderExists ? 'true' : 'false');
        Console::decreaseIndent();
        Console::log('Strategy: %s', $strategy->value);
        Console::increaseIndent();
        Console::log('Clone URL: %s', $url);
        Console::log('Default branch: %s', $repositoryInfo->defaultBranch);
        Console::decreaseIndent();

        switch ($strategy) {
            case UpdateStrategy::clone:
                if (!self::cloneRepository($repositoryInfo, $repositoryPath)) {
                    Console::error('Clone failed');
                    return false;
                }

                if (!self::updateSubmodules($repositoryPath)) {
                    Console::error('Submodule update failed');
                    return false;
                }
                break;

            case UpdateStrategy::fetchPull:
                if (!self::fetchRepository($repositoryPath)) {
                    Console::error('Repository fetch failed');
                    return false;
                }

                if (!self::pullRepository($repositoryPath)) {
                    Console::error('Repository pull failed');
                    return false;
                }

                if (!self::updateSubmodules($repositoryPath)) {
                    Console::error('Submodule update failed');
                    return false;
                }
                break;

            default:
                break;
        }
        Console::decreaseIndent();

        return true;
    }

    private static function selectStrategyForExistingFolder(RepositoryInfo $repositoryInfo, string $repositoryPath): UpdateStrategy
    {
        $gitFolder = $repositoryPath . DIRECTORY_SEPARATOR . '.git';
        if (!is_dir($gitFolder)) {
            return UpdateStrategy::skipNotRepository;
        }

        $exitCode = Process::exec('git symbolic-ref --short HEAD', $output, workingDirectory: $repositoryPath);
        if ($exitCode !== 0 || $output !== $repositoryInfo->defaultBranch) {
            return UpdateStrategy::skipDifferentBranch;
        }

        $cloneMethod = CommandLineParser::getArgumentValue(CommandLineArgumentName::cloneProtocol);
        $url = ($cloneMethod === 'ssh') ? $repositoryInfo->sshURL : $repositoryInfo->httpsURL;
        $exitCode = Process::exec('git remote get-url origin', $output, workingDirectory: $repositoryPath);
        if ($exitCode !== 0 || $output !== $url) {
            return UpdateStrategy::skipDifferentRemote;
        }

        return UpdateStrategy::fetchPull;
    }

    private static function cloneRepository(RepositoryInfo $repositoryInfo, string $repositoryPath): bool
    {
        $cloneMethod = CommandLineParser::getArgumentValue(CommandLineArgumentName::cloneProtocol);
        $url = ($cloneMethod === 'ssh') ? $repositoryInfo->sshURL : $repositoryInfo->httpsURL;

        $cmd = "git clone --branch {$repositoryInfo->defaultBranch} {$url} {$repositoryPath}";

        Console::setColor(ConsoleColor::FgCyan);
        Console::empty();
        $result = passthru($cmd, $exitCode);
        Console::resetColor();
        return ($result === null && $exitCode === 0);
    }

    private static function updateSubmodules(string $repositoryPath): bool
    {
        $cwd = getcwd();
        chdir($repositoryPath);

        $cmd = "git submodule update --init --recursive";

        Console::setColor(ConsoleColor::FgCyan);
        Console::empty();
        $result = passthru($cmd, $exitCode);
        Console::resetColor();

        chdir($cwd);
        return ($result === null && $exitCode === 0);
    }

    private static function fetchRepository(string $repositoryPath): bool
    {
        $cwd = getcwd();
        chdir($repositoryPath);

        $cmd = "git fetch --all --tags --prune --prune-tags --recurse-submodules";

        Console::setColor(ConsoleColor::FgCyan);
        Console::empty();
        $result = passthru($cmd, $exitCode);
        Console::resetColor();

        chdir($cwd);
        return ($result === null && $exitCode === 0);
    }

    private static function pullRepository(string $repositoryPath): bool
    {
        $cwd = getcwd();
        chdir($repositoryPath);

        $cmd = "git pull";

        Console::setColor(ConsoleColor::FgCyan);
        Console::empty();
        $result = passthru($cmd, $exitCode);
        Console::resetColor();

        chdir($cwd);
        return ($result === null && $exitCode === 0);
    }
}
