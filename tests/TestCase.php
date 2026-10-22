<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tymon\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Insère une ligne en contournant les clés étrangères (pour fabriquer des données orphelines).
     * SQLite (tests locaux) : « foreign_keys = OFF » est ignoré dans la transaction de
     * RefreshDatabase, on reporte donc le contrôle à la fin de la transaction, annulée après le test.
     * MySQL (CI, production) : désactivation classique des contraintes le temps de l'insertion.
     */
    protected function insertSansCleEtrangere(string $table, array $row): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA defer_foreign_keys = ON');
            DB::table($table)->insert($row);

            return;
        }

        Schema::withoutForeignKeyConstraints(fn () => DB::table($table)->insert($row));
    }

    protected function actingAsAdmin(?User $user = null): User
    {
        $user ??= User::factory()->admin()->create();
        $token = JWTAuth::fromUser($user);
        $this->withToken($token);
        return $user;
    }

    protected function actingAsVendeur(?User $user = null): User
    {
        $user ??= User::factory()->vendeur()->create();
        $token = JWTAuth::fromUser($user);
        $this->withToken($token);
        return $user;
    }
}
