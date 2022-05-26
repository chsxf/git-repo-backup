<?php

namespace chsxf\GitRepoBackup;

enum CommandLineArgumentName: string
{
    case username = 'username';
    case password = 'password';
    case platform = 'platform';
    case noGitLFS = 'no-git-lfs';
    case destPath = 'destination-path';
    case cloneProtocol = 'clone-protocol';
    case sshKeyPath = 'ssh-key-path';
    case excludedRepositories = 'excluded-repositories';
}

class CommandLineParser
{
    private static ?array $argumentsDescription = null;

    private static int $currentArgumentIndex;
    private static array $parsedArguments;
    private static array $parsedArgumentValues;

    public static function parse(): bool
    {
        self::initArgumentsDescription();

        self::$currentArgumentIndex = 1;
        self::$parsedArguments = [];
        self::$parsedArgumentValues = [];

        do {
            $nextArgument = self::handleNextArgument(required: false);
            if ($nextArgument === false) {
                return false;
            }
        } while ($nextArgument !== null);

        foreach (self::$argumentsDescription as $argDescription) {
            if ($argDescription instanceof CommandLineArgumentDescriptor) {
                $parameterIsPresent = in_array($argDescription->name, self::$parsedArguments);

                if ($parameterIsPresent && !$argDescription->validatesOSFamily()) {
                    Console::error("Argument '%s' is not valid for this OS family", $argDescription->name);
                    return false;
                }

                if ($argDescription->required && !$parameterIsPresent) {
                    Console::error("Required argument '%s' is missing", $argDescription->name);
                    return false;
                }

                if (!empty($argDescription->customValidationCallable)) {
                    $closure = $argDescription->customValidationCallable;
                    if ($closure() === false) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private static function handleNextArgument(bool $required, bool $mustBeTrailingArgument = false): string|bool|null
    {
        global $argv;

        if (self::$currentArgumentIndex == count($argv)) {
            if ($required) {
                Console::error('Expected argument missing');
            }
            return $required ? false : null;
        }

        $arg = $argv[self::$currentArgumentIndex++];

        $isLeadingArgument = preg_match('/^-{1,2}(.+)$/', $arg, $regs);
        if ($isLeadingArgument && $mustBeTrailingArgument) {
            return false;
        }

        if ($isLeadingArgument) {
            $argName = $regs[1];

            $found = false;

            foreach (self::$argumentsDescription as $argDescription) {
                if ($argDescription instanceof CommandLineArgumentDescriptor && $argDescription->name === $argName) {
                    $found = true;
                    if ($argDescription->getTrailingArgumentCount() > 0) {
                        for ($i = 0; $i < $argDescription->getTrailingArgumentCount(); $i++) {
                            $argValue = self::handleNextArgument(required: true, mustBeTrailingArgument: true);
                            if ($argValue === false) {
                                Console::error("Missing parameter value for argument '%s'", $argName);
                                return false;
                            }

                            $conformedValue = $argDescription->conformTrailingArgumentValue($i, $argValue);
                            if ($conformedValue === false) {
                                Console::error("Invalid parameter value for argument '%s'", $argName);
                                return false;
                            }

                            self::$parsedArgumentValues[$argDescription->getTrailingArgumentName($i)] = $conformedValue;
                        }
                        self::$parsedArguments[] = $argName;
                    } else {
                        self::$parsedArgumentValues[$argName] = true;
                        self::$parsedArguments[] = $argName;
                    }
                }
            }

            if (!$found) {
                Console::error("'%s' is not a valid arugment", $regs[0]);
                return false;
            }

            return true;
        } else {
            return $arg;
        }
    }

    public static function showUsage()
    {
        self::initArgumentsDescription();

        Console::log('Usage:');
        Console::increaseIndent();
        $commandLine = 'git-repo-backup';
        foreach (self::$argumentsDescription as $arg) {
            if ($arg instanceof CommandLineArgumentDescriptor) {
                $argEntry = $arg->getPrefixedName();
                foreach ($arg->getTrailingArguments() as $trailingArgument) {
                    $argEntry .= " {$trailingArgument}";
                }
                if (!$arg->required) {
                    $argEntry = "[{$argEntry}]";
                }
                $commandLine .= " {$argEntry}";
            }
        }
        Console::log($commandLine);
        Console::empty();
        foreach (self::$argumentsDescription as $arg) {
            if ($arg instanceof CommandLineArgumentDescriptor) {
                $argEntry = $arg->getPrefixedName();
                foreach ($arg->getTrailingArguments() as $trailingArgument) {
                    $argEntry .= " {$trailingArgument}";
                }

                Console::log($argEntry);

                Console::increaseIndent();
                if (!$arg->required) {
                    Console::log('(optional)');
                    Console::empty();
                }
                Console::log($arg->description);
                Console::decreaseIndent();

                Console::empty();
            }
        }
        Console::decreaseIndent();
    }

    private static function initArgumentsDescription()
    {
        if (self::$argumentsDescription === null) {
            self::$argumentsDescription = [
                new CommandLineArgumentDescriptor(
                    name: CommandLineArgumentName::username->value,
                    trailingArguments: [CommandLineArgumentName::username->value],
                    description: "User name used to authenticate with the platform's API."
                ),
                new CommandLineArgumentDescriptor(
                    name: CommandLineArgumentName::password->value,
                    trailingArguments: [CommandLineArgumentName::password->value],
                    description: "Password, OAuth token, or personal access token used to authenticate with the platform's API."
                ),
                new CommandLineArgumentDescriptor(
                    name: CommandLineArgumentName::platform->value,
                    trailingArguments: [CommandLineArgumentName::platform->value],
                    description: "Platform to authenticate with (only 'GitHub' and 'BitBucket' are supported at the moment)\nThis setting is not case-sensitive",
                    acceptedValues: [['github', 'bitbucket']]
                ),
                new CommandLineArgumentDescriptor(
                    name: CommandLineArgumentName::cloneProtocol->value,
                    trailingArguments: [CommandLineArgumentName::cloneProtocol->value],
                    description: "Use HTTPS or SSH as the clone protocol\nThis setting is not case-sensitive",
                    required: true,
                    acceptedValues: [['https', 'ssh']]
                ),
                new CommandLineArgumentDescriptor(
                    name: CommandLineArgumentName::noGitLFS->value,
                    description: "If present, git-lfs is not checked or explictly used during the execution of the script",
                    required: false
                ),
                new CommandLineArgumentDescriptor(
                    name: 'dest-dir',
                    trailingArguments: [CommandLineArgumentName::destPath->value],
                    description: "Target directory into which cloning or updating the repositories. If not present, the script uses the current directory.",
                    required: false,
                    customValidationCallable: self::validateDestinationPath(...)
                ),
                new CommandLineArgumentDescriptor(
                    name: 'ssh-key',
                    trailingArguments: [CommandLineArgumentName::sshKeyPath->value],
                    description: "Not supported on Windows\nSpecific SSH key to use with repositories, useful if you have several SSH keys for the same domain\nThe specific path will be passed to clone commands thanks to the core.sshCommand git config\nIgnored if --clone-protocal is set to 'https'",
                    required: false,
                    osFamilySupport: new CommandLineArgumentOSFamilySupport(false, ['Windows']),
                    customValidationCallable: self::validateSSHKey(...)
                ),
                new CommandLineArgumentDescriptor(
                    name: 'exclude',
                    trailingArguments: [CommandLineArgumentName::excludedRepositories->value],
                    description: "Comma-separated list of excluded repositories\nEach entry can be either an exact match if containing only alphanumerical characters, hyphens and underscores, or a case-insensitive Perl-Compatible Regular Expression otherwise",
                    required: false,
                    customValidationCallable: self::validateExclusionFilter(...)
                )
            ];
        }
    }

    public static function getArgumentValue(CommandLineArgumentName $argumentName, mixed $defaultValue = null): mixed
    {
        if (array_key_exists($argumentName->value, self::$parsedArgumentValues)) {
            return self::$parsedArgumentValues[$argumentName->value];
        }
        return $defaultValue;
    }

    private static function validateDestinationPath(): bool
    {
        $destPath = self::getArgumentValue(CommandLineArgumentName::destPath);
        if (!empty($destPath) && !is_dir($destPath)) {
            Console::error("'%s' does not exist or is not a directory, and therefore is not a valid destination path", $destPath);
            return false;
        }
        return true;
    }

    private static function validateSSHKey(): bool
    {
        $sshKeyPath = self::getArgumentValue(CommandLineArgumentName::sshKeyPath);
        if (!empty($sshKeyPath) && !file_exists($sshKeyPath)) {
            Console::error("'%s' does not exist, and therefore is not a valid SSH key path", $sshKeyPath);
            return false;
        }
        return true;
    }

    private static function validateExclusionFilter(): bool
    {
        $exclusionFilter = self::getArgumentValue(CommandLineArgumentName::excludedRepositories);
        if (!empty($exclusionFilter)) {
            $chunks = explode(',', $exclusionFilter);
            $invalidChunk = null;
            foreach ($chunks as $chunk) {
                if (empty(trim($chunk))) {
                    $invalidChunk = $chunk;
                    break;
                }

                if (!preg_match('/^[a-z0-9_-]+$/i', $chunk)) {
                    $escapedChunk = str_replace('/', '\/', $chunk);

                    $test = @preg_match("/{$escapedChunk}/i", '');
                    if ($test === false) {
                        $invalidChunk = $chunk;
                        break;
                    }
                }
            }
            if ($invalidChunk !== null) {
                Console::error("Filter '%s' is malformed", $invalidChunk);
                return false;
            }
        }
        return true;
    }
}
