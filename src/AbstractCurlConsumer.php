<?php

namespace chsxf\GitRepoBackup;

enum RequestMethod: string
{
    case DELETE = 'DELETE';
    case GET = 'GET';
    case HEAD = 'HEAD';
    case POST = 'POST';
    case PUT = 'PUT';
}

class CurlRequestResult
{
    public function __construct(
        public readonly int $responseCode,
        public readonly string|object|array $response,
        public readonly array $headers
    ) {
    }
}

abstract class AbstractCurlConsumer
{
    public function __construct(
        private readonly string $apiBaseURL,
        protected readonly string $username,
        private readonly string $password
    ) {
    }

    public function request(RequestMethod $requestMethod, string $path, string|array|null $body = null, array $specificHeaders = []): CurlRequestResult|false
    {
        $ch = curl_init($this->makePath($path));
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestMethod->value);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $headers = array_merge($specificHeaders, $this->getHeaders());
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $userAgent = $this->userAgent();
        if (!empty($userAgent)) {
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        }

        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
        }

        $result = curl_exec($ch);
        curl_close($ch);
        if ($result === false) {
            return false;
        }

        list($responseHeaders, $response) = explode("\r\n\r\n", $result, 2);

        $responseHeaders = explode("\r\n", trim($responseHeaders));
        $filteredHeaders = [];
        $responseCode = null;
        for ($i = 0; $i < count($responseHeaders); $i++) {
            $rh = $responseHeaders[$i];

            if ($i === 0) {
                if (preg_match('/^HTTP\/\d+\s+(\d+)/', $rh, $regs)) {
                    $responseCode = intval($regs[1]);
                } else {
                    Console::error('Unable to parse response code (%s)', $rh);
                    return false;
                }
            } else {
                if (preg_match('/^([^:]+):\s+(.*)$/', $rh, $regs)) {
                    $filteredHeaders[$regs[1]] = $regs[2];
                } else {
                    Console::error('Unable to parse header (%s)', $rh);
                    return false;
                }
            }
        }

        if ($responseCode === 200 && !empty($filteredHeaders['content-type']) && preg_match('/^application\/json/', $filteredHeaders['content-type'])) {
            $response = json_decode($response);
        }

        return new CurlRequestResult($responseCode, $response, $filteredHeaders);
    }

    public function get(string $path, array $specificHeaders = []): CurlRequestResult|false
    {
        return $this->request(RequestMethod::GET, $path, specificHeaders: $specificHeaders);
    }

    public function post(string $path, string|array $body, array $specificHeaders = []): CurlRequestResult|false
    {
        return $this->request(RequestMethod::POST, $path, $body, $specificHeaders);
    }

    protected function userAgent(): ?string
    {
        return null;
    }

    protected function makePath(string $path): string
    {
        $filteredPath = str_replace(array(':USERNAME'), array($this->username), $path);
        if (strpos($filteredPath, $this->apiBaseURL) === 0) {
            return $filteredPath;
        }
        return "{$this->apiBaseURL}{$filteredPath}";
    }

    protected function getHeaders(): array
    {
        return [];
    }
}
