<?php

namespace chsxf\GitRepoBackup\PlatformHandlers;

class RepositoryInfo
{
    public function __construct(
        public readonly string $name,
        public readonly string $cloneURL,
        public readonly string $sshURL,
        public readonly string $defaultBranch
    ) {
    }
}
