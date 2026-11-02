<?php
declare(strict_types=1);

/**
 * Tiny GLPI test doubles for isolated plugin tests.
 * These are loaded only by the development test bootstrap, never by GLPI.
 */

namespace Glpi {
    if (!class_exists(Event::class, false)) {
        final class Event
        {
            public static array $entries = [];

            public static function log(mixed ...$arguments): void
            {
                self::$entries[] = $arguments;
            }
        }
    }
}

namespace {
    if (!function_exists('__')) {
        function __(string $message, ?string $domain = null): string
        {
            return $message;
        }
    }

    if (!class_exists(CommonDBTM::class, false)) {
        class CommonDBTM
        {
            public static array $records = [];
            public array $fields = [];
            private bool $testViewable = false;

            public function getFromDB(int $id): bool
            {
                if (!isset(static::$records[$id])) {
                    return false;
                }

                $record = static::$records[$id];
                $this->fields = $record['fields'] ?? [];
                $this->testViewable = (bool)($record['viewable'] ?? false);

                return true;
            }

            public function canView(): bool
            {
                return $this->testViewable;
            }
        }
    }

    if (!class_exists(Entity::class, false)) {
        class Entity extends CommonDBTM
        {
            public static array $records = [];
        }
    }

    if (!class_exists(Session::class, false)) {
        final class Session
        {
            public static int $activeEntity = 1;
            public static array $allowedEntities = [1];
            public static array $messages = [];

            public static function addMessageAfterRedirect(string $message, bool $force = false, int $type = 0): void
            {
                self::$messages[] = [$message, $force, $type];
            }

            public static function getActiveEntity(): int
            {
                return self::$activeEntity;
            }

            public static function haveAccessToEntity(int $entityId): bool
            {
                return in_array($entityId, self::$allowedEntities, true);
            }
        }
    }

    if (!defined('INFO')) {
        define('INFO', 1);
    }
    if (!defined('ERROR')) {
        define('ERROR', 2);
    }

    if (!class_exists(Config::class, false)) {
        final class Config
        {
            public static array $values = [];

            public static function getConfigurationValues(string $context): array
            {
                return self::$values[$context] ?? [];
            }

            public static function setConfigurationValues(string $context, array $values): void
            {
                self::$values[$context] = array_merge(
                    self::$values[$context] ?? [],
                    $values
                );
            }
        }
    }
}


namespace Symfony\Component\HttpFoundation {
    if (!class_exists(Response::class, false)) {
        class Response
        {
            public function __construct(
                public string $content = '',
                public int $statusCode = 200,
                public array $headers = []
            ) {
            }
        }
    }

    if (!class_exists(RedirectResponse::class, false)) {
        class RedirectResponse extends Response
        {
            public function __construct(string $url, int $status = 302)
            {
                parent::__construct('', $status, ['Location' => $url]);
            }
        }
    }
}
