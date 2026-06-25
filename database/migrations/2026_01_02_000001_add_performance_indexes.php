<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->index('is_actif');
            $table->index(['is_actif', 'en_promo']);
            $table->index('created_at');
        });

        Schema::table('variantes', function (Blueprint $table) {
            $table->index('quantite_stock');
        });

        Schema::table('mouvement_stocks', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('caisse_sessions', function (Blueprint $table) {
            $table->index('statut');
            $table->index('ouverture_at');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropIndex(['is_actif']);
            $table->dropIndex(['is_actif', 'en_promo']);
            $table->dropIndex(['created_at']);
        });
        Schema::table('variantes', function (Blueprint $table) {
            $table->dropIndex(['quantite_stock']);
        });
        Schema::table('mouvement_stocks', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
        Schema::table('caisse_sessions', function (Blueprint $table) {
            $table->dropIndex(['statut']);
            $table->dropIndex(['ouverture_at']);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['type']);
        });
    }
};
