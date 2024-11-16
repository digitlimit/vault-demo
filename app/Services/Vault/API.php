<?php

namespace App\Services\Vault;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Factory;

class API
{
    public function __construct(
        private readonly Factory $http
    ){}

    private string $token = '';

    private string $address = '';

    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function address(): string
    {
        return $this->address;

    }

    public function client(): Factory|PendingRequest
    {
        return $this->http->withToken($this->token());
    }

    public function get(string $path, array $data = []): ?array
    {
        return $this->request('GET', $path, $data);
    }

    public function post(string $path, array $data = []): ?array
    {
        return $this->request('POST', $path, $data);
    }

    public function request(string $method, string $path, array $data = []): ?array
    {
        $method = strtolower($method);
        $path = ltrim($path, '/');
        $address = rtrim($this->address(), '/');

        $response = $this->client()
            ->$method($address . '/' . $path, $data);

        if ($response->successful()) {
            return $response->json('data');
        }

        return null;
    }
}
