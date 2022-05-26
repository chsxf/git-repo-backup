<?php

namespace chsxf\GitRepoBackup;

use chsxf\GitRepoBackup\PlatformHandlers\RepositoryInfo;

class GitHandler
{
    public static function checkGitAvailability(): bool
    {
        Console::log('Looking for git...');

        $exitCode = Process::exec('git --version', $result);
        if ($exitCode === 0 && preg_match('/^git version \d+\.\d+\.\d+/', $result)) {
            Console::success('%s', $result);
        } else {
            Console::error('It seems git is not installed on the system or not accessible in the current environment');
            return false;
        }

        if (!CommandLineParser::getArgumentValue(CommandLineArgumentName::noGitLFS, defaultValue: false)) {
            Console::log('Looking for git-lfs...');
            $exitCode = Process::exec('git lfs version', $result);
            if ($exitCode === 0 && preg_match('/^git-lfs\/\d+\.\d+\.\d+/', $result)) {
                Console::success('%s', $result);
            } else {
                Console::error('It seems git-lfs is not installed on the system or not accessible in the current environement');
                return false;
            }
        }

        return true;
    }

    public static function cloneRepository(RepositoryInfo $repositoryInfo, string $repositoryPath): bool
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

    public static function updateSubmodules(string $repositoryPath): bool
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

    public static function fetchRepository(string $repositoryPath): bool
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

    public static function pullRepository(string $repositoryPath): bool
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
