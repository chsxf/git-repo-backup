<?php

namespace chsxf\GitRepoBackup\PlatformHandlers;

use chsxf\GitRepoBackup\AbstractCurlConsumer;

abstract class AbstractPlatformHandler extends AbstractCurlConsumer
{
    abstract public function fetchRepositoryList(): array|false;
}
