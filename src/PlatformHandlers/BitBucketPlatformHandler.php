<?php

namespace chsxf\GitRepoBackup\PlatformHandlers;

use chsxf\GitRepoBackup\Console;

class BitBucketPlatformHandler extends AbstractPlatformHandler
{
    public function __construct(string $username, string $password)
    {
        parent::__construct('https://api.bitbucket.org/2.0', $username, $password);
    }

    public function fetchRepositoryList(): array|false
    {
        $repositories = [];

        $nextRequest = '/repositories/:USERNAME?role=owner';

        do {
            $requestResult = $this->get($nextRequest);
            if ($requestResult === false || $requestResult->responseCode !== 200) {
                return false;
            }

            if (!empty($requestResult->response->values)) {
                foreach ($requestResult->response->values as $repo) {
                    if ($repo->scm !== 'git') {
                        continue;
                    }

                    $httpsURL = null;
                    $sshURL = null;
                    foreach ($repo->links->clone as $cloneLink) {
                        if ($cloneLink->name === 'https') {
                            $httpsURL = $cloneLink->href;
                        } else if ($cloneLink->name === 'ssh') {
                            $sshURL = $cloneLink->href;
                        }
                    }

                    if (empty($httpsURL) || empty($sshURL)) {
                        Console::error('Missing clone URL or SSH URL for repository %s', $repo->name);
                        return false;
                    }

                    $repositories[] = new RepositoryInfo($repo->name, $httpsURL, $sshURL, $repo->mainbranch->name, $repo->size);
                }
            }

            $nextRequest = null;
            if (!empty($requestResult->response->next)) {
                $nextRequest = $requestResult->response->next;
            }
        } while ($nextRequest !== null);

        return $repositories;
    }
}
