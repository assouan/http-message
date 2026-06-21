# Assouan HTTP Message

HTTP response, headers and base message objects for the A PHP libraries.

```bash
composer require assouan/http-message
```

Requires PHP 8.5 or later.

## Helpers

```php
use A\Http\Response;

return Response::redirect('/dashboard');
return Response::html('<h1>Not found</h1>', 404);
return Response::json_body(['ok' => true]);
return Response::json_body(['error' => 'unauthorized'], 401);
```
