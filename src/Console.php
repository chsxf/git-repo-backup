<?php

namespace chsxf;

enum ConsoleColor: string
{
    case Reset = "\x1b[0m";
    case Bright = "\x1b[1m";
    case Dim = "\x1b[2m";
    case Underscore = "\x1b[4m";
    case Blink = "\x1b[5m";
    case Reverse = "\x1b[7m";
    case Hidden = "\x1b[8m";

    case FgBlack = "\x1b[30m";
    case FgRed = "\x1b[31m";
    case FgGreen = "\x1b[32m";
    case FgYellow = "\x1b[33m";
    case FgBlue = "\x1b[34m";
    case FgMagenta = "\x1b[35m";
    case FgCyan = "\x1b[36m";
    case FgWhite = "\x1b[37m";
    case FgDefault = "\x1b[39m";

    case BgBlack = "\x1b[40m";
    case BgRed = "\x1b[41m";
    case BgGreen = "\x1b[42m";
    case BgYellow = "\x1b[43m";
    case BgBlue = "\x1b[44m";
    case BgMagenta = "\x1b[45m";
    case BgCyan = "\x1b[46m";
    case BgWhite = "\x1b[47m";
    case BgDefault = "\x1b[49m";
}

class Console
{
    private static int $indent = 0;

    public static function empty()
    {
        self::log('');
    }

    public static function error(string $format, mixed ...$values)
    {
        self::logWithColor(ConsoleColor::FgRed, $format, ...$values);
    }

    public static function warning(string $format, mixed ...$values)
    {
        self::logWithColor(ConsoleColor::FgYellow, $format, ...$values);
    }

    public static function success(string $format, mixed ...$values)
    {
        self::logWithColor(ConsoleColor::FgGreen, $format, ...$values);
    }

    public static function log(string $format, mixed ...$values)
    {
        $formattedString = sprintf($format, ...$values);
        $lines = explode("\n", $formattedString);
        $prefix = str_pad('', self::$indent, "\t");
        foreach ($lines as $line) {
            print("{$prefix}{$line}\n");
        }
    }

    public static function logWithColor(ConsoleColor $color, string $format, mixed ...$values)
    {
        self::setColor($color);
        self::log($format, ...$values);
        self::resetColor();
    }

    public static function setColor(ConsoleColor $color)
    {
        if (PHP_OS_FAMILY != 'Windows') {
            print($color->value);
        }
    }

    public static function resetColor()
    {
        self::setColor(ConsoleColor::Reset);
    }

    public static function increaseIndent()
    {
        self::$indent++;
    }

    public static function decreaseIndent()
    {
        self::$indent = max(0, self::$indent - 1);
    }

    public static function resetIndent()
    {
        self::$indent = 0;
    }
}
