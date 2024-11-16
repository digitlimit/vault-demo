<?php

namespace App\Services\Vault;

use Illuminate\Support\Facades\Cache;

class Secret
{
    protected ?string $readPath = null;

    protected ?API $api = null;

    protected array $data = [];

    public function __construct(
        private readonly Env $env
    ){
        $this->setData(
            $this->env->load()->all()
        );
    }

    public function all($path = null): array
    {
        if ($path){
            $this->setReadPath($path);
        }

        $path = $this->readPath();

        $secrets = $this->api()->get($path) ?? [];

        return $secrets['data'] ?? [];
    }

    /**
     * Return the path to the secret (.env file MYSECRET=:vault/secret/data/my_secret)
     * @param  string  $key
     * @param  string|null  $path
     * @return string|null
     */
    public function valueContainsPathToSecret(string $key, string $path = null): ?string
    {
        $value = getenv($key);
        if (str_starts_with($value, ':vault')) {
            // then returns the value as the path to the secret
            return str_ireplace(':vault', '', $value);
        }

        return $path;
    }
    
    public function read(string $key, string $path = null): ?string
    {
        $path = $this->valueContainsPathToSecret($key, $path);
        $secrets = $this->all($path);

        if ($secrets[$key] ?? null) {
            return $this->transformValue($secrets[$key]);
        }

        return null;
    }

    public function write(array $data = [], string $path = null): ?array
    {
        if ($path){
            $this->setReadPath($path);
        }

        $path = $this->readPath();

        return $this->api->post($path, $data);
    }

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function setApi(API $api): static
    {
        $this->api = $api;
        return $this;
    }

    public function setReadPath(string $readPath): static
    {
        $this->readPath = trim($readPath, '/');

        return $this;
    }

    public function api(): API
    {
        if ($this->api) {
            return $this->api;
        }

        $this->api = app(API::class)
            ->setAddress($this->data['VAULT_ADDR'])
            ->setToken($this->data['VAULT_TOKEN']);

        return $this->api;
    }

    public function readPath(): string
    {
        if ($this->readPath) {
            return $this->readPath;
        }

        return $this->defaultReadPath();
    }

    public function cacheable(): bool
    {
        if(! isset($this->data['VAULT_CACHEABLE'])) {
            return false;
        }

        return strtolower($this->data['VAULT_CACHEABLE']) === 'true';
    }

    public function cachePrefix(): string
    {
        return $this->data['VAULT_CACHE_PREFIX'] ?? 'vault:';
    }

    public function cacheKey(string $key): string
    {
        return $this->cachePrefix() . $key;
    }

    public function cacheTtl(): int
    {
        return $this->data['VAULT_TTL'] ?? 60;
    }

    public function cache($key, $default = null)
    {
        $cacheKey = $this->cacheKey($key);

        return Cache::remember(
            $cacheKey,
            $this->cacheTtl(),
            fn() => $this->read($key, $default)
        );
    }

    public function defaultReadPath()
    {
        return $this->data['VAULT_READ_PATH'];
    }

    public function hasOnly(): bool
    {
        return isset($this->data['VAULT_ONLY'])
            && $this->data['VAULT_ONLY'];
    }

    public function only(): array
    {
        if(! $this->hasOnly()) {
            return [];
        }

        $only = explode(',', $this->data['VAULT_ONLY']);

        return array_map('trim', $only);
    }

    public function inOnly(string $key): bool
    {
        $only = $this->only();

        if(empty($only)) {
            return false;
        }

        return in_array($key, $only);
    }

    public function transform(array $data): array
    {
        return array_map(
            fn($value) => $this->transformValue($value),
            $data
        );
    }

    protected function transformValue($value)
    {
        if (is_string($value)) {
            if (strtolower($value) === 'null') {
                return null;
            }

            if (strtolower($value) === 'true') {
                return true;
            }

            if (strtolower($value) === 'false') {
                return false;
            }

            if (is_numeric($value)) {
                return str_contains($value, '.') ? (float) $value : (int) $value;
            }
        }

        return $value;
    }
}
