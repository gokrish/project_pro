<?php
namespace ProConsultancy\Core;

use Exception;

/**
 * Permission – Hybrid RBAC + Legacy System
 *
 * Supports:
 * - Legacy users.level permissions
 * - New RBAC (roles, permissions, overrides)
 * - .all / .own ownership logic
 * - SQL access filters
 *
 * @version 5.0 FINAL
 */
class Permission
{
    private static array $cache = [];
    private static ?array $userPermissions = null;

    /* =====================================================
     * PUBLIC API
     * ===================================================== */

    /**
     * Core permission check
     */
    public static function can(
        string $module,
        string $action,
        int|string|null $resourceOwnerId = null
    ): bool {
        try {
            $user = Auth::user();
            if (!$user) {
                return false;
            }

            // Admin bypass
            if (in_array($user['level'] ?? null, ['super_admin', 'admin'], true)) {
                return true;
            }

            $action = self::normalize($action);
            $cacheKey = $user['id'] . ":$module:$action:" . ($resourceOwnerId ?? 'all');

            if (isset(self::$cache[$cacheKey])) {
                return self::$cache[$cacheKey];
            }

            $result = !empty($user['role_id'])
                ? self::checkRBAC($user, $module, $action, $resourceOwnerId)
                : self::checkLegacy($user, $module, $action, $resourceOwnerId);

            return self::$cache[$cacheKey] = $result;

        } catch (Exception $e) {
            Logger::getInstance()->error('Permission check failed', [
                'module' => $module,
                'action' => $action,
                'error'  => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * edit → checks edit.all OR edit.own automatically
     */
    public static function canAction(
        string $module,
        string $action,
        int|string|null $resourceOwnerId = null
    ): bool {
        return self::can($module, "$action.all")
            || ($resourceOwnerId !== null && self::can($module, "$action.own", $resourceOwnerId));
    }

    /**
     * Throw exception if not allowed
     */
    public static function require(
        string $module,
        string $action,
        int|string|null $resourceOwnerId = null,
        ?string $message = null
    ): void {
        if (!self::canAction($module, $action, $resourceOwnerId)) {
            throw new PermissionException(
                $message ?? "You do not have permission to $action $module."
            );
        }
    }

    /**
     * SQL filter for candidate access
     */
    public static function getAccessibleCandidates(): array
    {
        $user = Auth::user();

        if (!$user) {
            return ['sql' => '1=0', 'params' => [], 'types' => ''];
        }

        if (
            in_array($user['level'], ['super_admin', 'admin'], true) ||
            self::can('candidates', 'view.all')
        ) {
            return ['sql' => '1=1', 'params' => [], 'types' => ''];
        }

        if (self::can('candidates', 'view.own')) {
            return [
                'sql' => 'c.created_by = ?',
                'params' => [$user['id']],
                'types' => 'i'
            ];
        }

        return ['sql' => '1=0', 'params' => [], 'types' => ''];
    }

    /**
     * Clear permission cache
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::$userPermissions = null;
    }

    /* =====================================================
     * INTERNAL – RBAC
     * ===================================================== */

    private static function checkRBAC(
        array $user,
        string $module,
        string $action,
        int|string|null $owner
    ): bool {
        self::loadUserPermissions($user['id']);

        // exact
        if (in_array("$module.$action", self::$userPermissions, true)) {
            return true;
        }

        // .all
        if (in_array("$module.$action.all", self::$userPermissions, true)) {
            return true;
        }

        // .own
        if (
            $owner !== null &&
            in_array("$module.$action.own", self::$userPermissions, true)
        ) {
            return self::owns($user['id'], $owner);
        }

        return false;
    }

    private static function loadUserPermissions(int $userId): void
    {
        if (self::$userPermissions !== null) {
            return;
        }

        self::$userPermissions = [];

        try {
            $db = Database::getInstance()->getConnection();

            $stmt = $db->prepare("
                SELECT p.permission_code
                FROM users u
                JOIN role_permissions rp ON rp.role_id = u.role_id
                JOIN permissions p ON p.id = rp.permission_id
                WHERE u.id = ?
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();

            while ($row = $res->fetch_assoc()) {
                self::$userPermissions[] = $row['permission_code'];
            }

        } catch (Exception $e) {
            Logger::getInstance()->error('RBAC load failed', ['error' => $e->getMessage()]);
        }
    }

    /* =====================================================
     * INTERNAL – LEGACY
     * ===================================================== */

    private static function checkLegacy(
        array $user,
        string $module,
        string $action,
        int|string|null $owner
    ): bool {
        $map = self::legacyMap();
        $level = $user['level'] ?? null;

        if (!isset($map[$level][$module])) {
            return false;
        }

        $allowed = array_map([self::class, 'normalize'], $map[$level][$module]);

        if (in_array("$action.all", $allowed, true) || in_array($action, $allowed, true)) {
            return true;
        }

        if ($owner !== null && in_array("$action.own", $allowed, true)) {
            return self::owns($user['id'], $owner);
        }

        return false;
    }

    private static function legacyMap(): array
    {
        return [
            'recruiter' => [
                'contacts' => ['view.own', 'create', 'edit.own'],
                'candidates' => ['view.own', 'create', 'edit.own'],
            ],
            'user' => [
                'contacts' => ['view.own', 'create', 'edit.own'],
                'candidates' => ['view.own', 'create', 'edit.own'],
            ],
            'manager' => [
                'contacts' => ['view.all', 'edit.all'],
                'candidates' => ['view.all', 'edit.all'],
            ],
        ];
    }

    /* =====================================================
     * HELPERS
     * ===================================================== */

    private static function owns(int $userId, int|string $owner): bool
    {
        if (is_numeric($owner)) {
            return $userId === (int)$owner;
        }

        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE user_code = ?");
            $stmt->bind_param('s', $owner);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            return ($row['id'] ?? null) === $userId;
        } catch (Exception) {
            return false;
        }
    }

    private static function normalize(string $value): string
    {
        return str_replace('_', '.', strtolower($value));
    }
}

/**
 * Permission Exception
 */
class PermissionException extends Exception
{
    public function __construct(string $message = 'Access denied', int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
