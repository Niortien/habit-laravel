<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sorties MODIFY COLUMN type ENUM('VENTE', 'PERTE', 'DON', 'RETOUR_FOURNISSEUR', 'DEPENSE') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE sorties MODIFY COLUMN type ENUM('VENTE', 'PERTE', 'DON', 'RETOUR_FOURNISSEUR') NOT NULL");
    }
};
