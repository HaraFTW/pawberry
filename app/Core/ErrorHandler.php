<?php

declare(strict_types=1);

namespace PawBerry\Core;

use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', Config::isDevelopment() ? '1' : '0');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e): void {
            self::handleException($e);
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error === null) {
                return;
            }
            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
            if (!in_array($error['type'], $fatalTypes, true)) {
                return;
            }
            self::handleException(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        });
    }

    public static function handleException(Throwable $e): void
    {
        Logger::error($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'class' => $e::class,
        ]);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
            if (Config::isDevelopment()) {
                fwrite(STDERR, $e->getFile() . ':' . $e->getLine() . PHP_EOL);
                fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
            }
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

        if ($wantsJson) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            $payload = ['error' => 'Something went wrong.'];
            if (Config::isDevelopment()) {
                $payload['detail'] = $e->getMessage();
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $response = View::error(
                500,
                'Something went wrong',
                'Our paws slipped. Please try again in a moment.',
                Config::isDevelopment() ? $e : null
            );
            $response->send();
        } catch (Throwable) {
            if (!headers_sent()) {
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo Config::isDevelopment()
                ? 'Error: ' . $e->getMessage()
                : 'Something went wrong.';
        }
    }
}
