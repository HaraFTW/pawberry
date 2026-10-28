<?php

declare(strict_types=1);

namespace PawBerry\Core;

use Throwable;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): Response
    {
        $content = self::renderTemplate($template, $data);

        if ($layout === null) {
            return Response::html($content);
        }

        $page = self::renderTemplate($layout, array_merge($data, [
            'content' => $content,
        ]));

        return Response::html($page);
    }

    /** @param array<string, mixed> $data */
    public static function renderPartial(string $template, array $data = []): string
    {
        return self::renderTemplate($template, $data);
    }

    public static function error(int $status, string $title, string $message, ?Throwable $exception = null): Response
    {
        $html = self::renderTemplate('errors/generic', [
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'exception' => $exception,
        ]);

        return Response::html($html, $status);
    }

    /** @param array<string, mixed> $data */
    private static function renderTemplate(string $template, array $data): string
    {
        $path = PAWBERRY_ROOT . '/app/Views/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException('View not found: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        return (string) ob_get_clean();
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
