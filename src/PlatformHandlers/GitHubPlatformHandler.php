<?php

namespace chsxf\GitRepoBackup\PlatformHandlers;

class GitHubPlatformHandler extends AbstractPlatformHandler
{
    public function __construct(string $username, string $password)
    {
        parent::__construct('https://api.github.com', $username, $password);
    }

    public function fetchRepositoryList(): array|false
    {
        $repositories = [];

        $nextRequest = '/user/repos?affiliation=owner';

        do {
            $requestResult = $this->get($nextRequest);
            if ($requestResult === false || $requestResult->responseCode !== 200) {
                return false;
            }

            foreach ($requestResult->response as $repo) {
                $repositories[] = new RepositoryInfo($repo->name, $repo->clone_url, $repo->ssh_url, $repo->default_branch);
            }

            $nextRequest = null;
            if (!empty($requestResult->headers['link'])) {
                $nextRequest = $this->getLinkFromHeader($requestResult->headers['link'], relation: 'next');
            }
        } while ($nextRequest !== null);

        return $repositories;
    }

    protected function getHeaders(): array
    {
        $array = parent::getHeaders();
        $array['Accept'] = 'application/vnd.github.v3+json';
        return $array;
    }

    protected function userAgent(): ?string
    {
        return $this->username;
    }

    private function getLinkFromHeader(string $linkHeaderContent, string $relation): ?string
    {
        $links = explode(', ', $linkHeaderContent);
        foreach ($links as $link) {
            list($url, $rel) = explode('; ', $link);
            $url = trim($url, '<>');
            if (preg_match('/^rel="(.+)"$/', $rel, $regs)) {
                $rel = $regs[1];
            }
            if ($relation === $rel) {
                return $url;
            }
        }
        return null;
    }
}
