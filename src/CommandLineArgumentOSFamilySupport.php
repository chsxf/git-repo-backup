<?php

namespace chsxf\GitRepoBackup;

class CommandLineArgumentOSFamilySupport
{
    public function __construct(
        public bool $included,
        public array $osFamilies
    ) {
    }
}
