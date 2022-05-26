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

            $filteredRepositories = self::filterRepositories($repositories, $excludedRepositories);
            $diff = count($repositories) - count($filteredRepositories);
            if ($diff > 0) {
                if ($diff == 1) {
                    Console::log('1 repository excluded:');
                } else {
                    Console::log('%d repositories excluded:', $diff);
                }
                Console::increaseIndent();
                foreach ($excludedRepositories as $repo) {
                    Console::log($repo->name);
                }
                Console::decreaseIndent();
                Console::success('Proceeding with %d repositories', count($repositories) - $diff);
            }

            Console::empty();

            foreach ($filteredRepositories as $repoInfo) {
                self::proceedForRepository($repoInfo);
            }
        }
    }

    private static function filterRepositories(array $repositories, ?array &$excludedRepositories): array
    {
        $exclusionFilters = CommandLineParser::getArgumentValue(CommandLineArgumentName::excludedRepositories);
        if (empty($exclusionFilters)) {
            return $repositories;
        }

        $filteredRepositories = [];
        $exclusionFilters = explode(',', $exclusionFilters);
        foreach ($exclusionFilters as &$filter) {
            if (!preg_match('/^[a-z0-9_-]+$/i', $filter)) {
                $filter = str_replace('/', '\/', $filter);
                $filter = "/{$filter}/i";
            }
        }
        unset($filter);

        foreach ($repositories as $repo) {
            if ($repo instanceof RepositoryInfo) {
                $included = true;
                foreach ($exclusionFilters as $filter) {
                    if (preg_match('/^\//', $filter)) {
                        if (preg_match($filter, $repo->name)) {
                            $included = false;
                            break;
                        }
                    } else if ($repo->name === $filter) {
                        $included = false;
                        break;
                    }
                }
                if ($included) {
                    $filteredRepositories[] = $repo;
                } else {
                    $excludedRepositories[] = $repo;
                }
            }
        }
        return $filteredRepositories;
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
                if (!GitHandler::cloneRepository($repositoryInfo, $repositoryPath)) {
                    Console::error('Clone failed');
                    return false;
                }

                if (!GitHandler::updateSubmodules($repositoryPath)) {
                    Console::error('Submodule update failed');
                    return false;
                }
                break;

            case UpdateStrategy::fetchPull:
                if (!GitHandler::fetchRepository($repositoryPath)) {
                    Console::error('Repository fetch failed');
                    return false;
                }

                if (!GitHandler::pullRepository($repositoryPath)) {
                    Console::error('Repository pull failed');
                    return false;
                }

                if (!GitHandler::updateSubmodules($repositoryPath)) {
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
}
