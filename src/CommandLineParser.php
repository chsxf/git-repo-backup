<?php

namespace chsxf;

enum CommandLineArgumentName: string
{
    case username = 'username';
    case password = 'password';
    case platform = 'platform';
}

class CommandLineParser
{
    private static ?array $argumentsDescription = null;

    private static int $currentArgumentIndex;
    private static array $parsedArguments;

    public static function parse(): bool
    {
        self::initArgumentsDescription();

        self::$currentArgumentIndex = 1;
        self::$parsedArguments = [];

        do {
            $nextArgument = self::handleNextArgument(required: false);
        } while ($nextArgument !== false && $nextArgument !== null);

        foreach (self::$argumentsDescription as $argDescription) {
            if ($argDescription instanceof CommandLineArgumentDescriptor) {
                if ($argDescription->required && !array_key_exists($argDescription->name, self::$parsedArguments)) {
                    Console::error("Required argument '%s' is missing", $argDescription->name);
                    return false;
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

            foreach (self::$argumentsDescription as $argDescription) {
                if ($argDescription instanceof CommandLineArgumentDescriptor && $argDescription->name === $argName) {
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

                        self::$parsedArguments[$argDescription->getTrailingArgumentName($i)] = $conformedValue;
                    }
                }
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
                    longForm: true,
                    trailingArguments: [CommandLineArgumentName::username->value],
                    description: "User name used to authenticate with the platform's API."
                ),
                new CommandLineArgumentDescriptor(
                    name: CommandLineArgumentName::password->value,
                    longForm: true,
                    trailingArguments: [CommandLineArgumentName::password->value],
                    description: "Password, OAuth token, or personal access token used to authenticate with the platform's API."
                ),
                new CommandLineArgumentDescriptor(
                    name: CommandLineArgumentName::platform->value,
                    longForm: true,
                    trailingArguments: [CommandLineArgumentName::platform->value],
                    description: "Platform to authenticate with (only 'GitHub' and 'BitBucket' are supported at the moment)\nThis setting is case-insensitive",
                    acceptedValues: [['github', 'bitbucket']]
                )
            ];
        }
    }

    public static function getArgumentValue(CommandLineArgumentName $argumentName): ?string
    {
        if (array_key_exists($argumentName->value, self::$parsedArguments)) {
            return self::$parsedArguments[$argumentName->value];
        }
        return null;
    }
}
