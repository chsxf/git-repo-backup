<?php

namespace chsxf\GitRepoBackup;

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
}
