<?php
declare(strict_types=1);

namespace App\Http;

final class Response
{
    public static function redirect(string $location, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $location);
        exit;
    }

    public static function notFound(string $message = 'Pagina niet gevonden'): never
    {
        http_response_code(404);
        echo $message;
        exit;
    }
}
