<?php

namespace chsxf\GitRepoBackup;

use Closure;

class CommandLineArgumentDescriptor
{
    public function __construct(
        public readonly string $name,
        public readonly bool $longForm = true,
        private readonly array $trailingArguments = [],
        public readonly bool $required = true,
        public readonly string $description = '',
        private readonly ?array $acceptedValues = null,
        public readonly ?Closure $customValidationCallable = null
    ) {
    }

    public function getPrefixedName(): string
    {
        return $this->longForm ? "--{$this->name}" : "-{$this->name}";
    }

    public function getTrailingArguments()
    {
        for ($i = 0; $i < count($this->trailingArguments); $i++) {
            if (empty($this->acceptedValues[$i]) || !is_array($this->acceptedValues[$i])) {
                yield "<{$this->trailingArguments[$i]}>";
            } else {
                $values = implode('|', $this->acceptedValues[$i]);
                yield "({$values})";
            }
        }
    }

    public function getTrailingArgumentCount(): int
    {
        return count($this->trailingArguments);
    }

    public function getTrailingArgumentName(int $index): string
    {
        return $this->trailingArguments[$index];
    }

    public function conformTrailingArgumentValue(int $index, string $value): string|false
    {
        if (!empty($this->acceptedValues[$index]) && is_array($this->acceptedValues[$index])) {
            foreach ($this->acceptedValues[$index] as $acceptableValue) {
                if (strcasecmp($acceptableValue, $value) === 0) {
                    return $acceptableValue;
                }
            }
            return false;
        }
        return $value;
    }
}
