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
return Response::json_response(['ok' => true]);
return Response::json_response(['error' => 'unauthorized'], 401);
```
