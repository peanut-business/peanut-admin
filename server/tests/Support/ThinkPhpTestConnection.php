<?php
declare(strict_types=1);

use think\DbManager;
use think\db\PDOConnection;
use think\db\builder\Mysql as MysqlBuilder;
use think\db\builder\Sqlite as SqliteBuilder;
use think\db\connector\Mysql;
use think\db\connector\Sqlite;

/** Adapts an existing fixture PDO into the exact ThinkPHP connection used by production services. */
final class ThinkPhpTestConnection
{
    private function __construct()
    {
    }

    public static function fromPdo(PDO $pdo): PDOConnection
    {
        return match ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'mysql' => new SharedPdoMysqlConnection($pdo),
            'sqlite' => new SharedPdoSqliteConnection($pdo),
            default => throw new RuntimeException('TEST_DATABASE_DRIVER_UNSUPPORTED'),
        };
    }

    public static function moduleCatalogs(PDO $pdo): \app\platform\service\plugin\ModuleCatalogApplier
    {
        $connection = self::fromPdo($pdo);
        return new \app\platform\service\plugin\ModuleCatalogApplier(
            $connection,
            new \PeanutAdmin\Settings\Persistence\SettingStore(
                $connection,
                \PeanutAdmin\Kernel\Persistence\Tenancy\TenantPersistenceMode::TenantScoped,
                null,
            ),
        );
    }
}

final class SharedPdoMysqlConnection extends Mysql
{
    public function __construct(private readonly PDO $sharedPdo)
    {
        parent::__construct([
            'type' => 'mysql',
            'builder' => MysqlBuilder::class,
            'prefix' => 'pa_',
        ]);
        $this->setDb(new DbManager());
    }

    protected function createPdo($dsn, $username, $password, $params): PDO
    {
        return $this->sharedPdo;
    }
}

final class SharedPdoSqliteConnection extends Sqlite
{
    public function __construct(private readonly PDO $sharedPdo)
    {
        parent::__construct([
            'type' => 'sqlite',
            'builder' => SqliteBuilder::class,
            'prefix' => 'pa_',
        ]);
        $this->setDb(new DbManager());
    }

    protected function createPdo($dsn, $username, $password, $params): PDO
    {
        return $this->sharedPdo;
    }
}
