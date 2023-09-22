## Saloon Rate Limit Plugin
Handling rate limits can be hard. This first-party plugin provides you with the tools you need to prevent rate-limits
and handle what happens if a rate-limit is exceeded. This plugin allows you to define limits on your connector/request.

### Available Stores
- In-Memory (Array)
- File
- Redis
- Predis
- PSR Cache Store
- Laravel Cache Store

With this plugin, you are be able to define various limits on a per-integration basis. You can also control if Saloon
should throw an exception or sleep if a limit is reached. Saloon will keep track of how many requests are made and
when a rate-limit is hit, Saloon will prevent further requests on the connector/request until the rate limit has
been lifted.

### Installation
You can install the cache plugin through Composer.

```
composer require saloonphp/rate-limit-plugin "^1.0"
```

### Documentation
[Click here to read the documentation](https://docs.saloon.dev/plugins/handling-rate-limits)
