<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();
        $this->seedCategories();
    }

    private function seedUsers(): void
    {
        $users = [
            ['email' => 'admin@shop.com',   'password' => 'StrongPass123!', 'role' => 'ADMIN'],
            ['email' => 'vendeur@shop.com', 'password' => 'StrongPass123!', 'role' => 'VENDEUR'],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                ['password_hash' => Hash::make($u['password']), 'role' => $u['role']]
            );
        }
    }

    private function seedCategories(): void
    {
        $categories = [
            ['nom' => 'T-Shirts',          'description' => 'T-shirts et hauts décontractés'],
            ['nom' => 'Chemises',           'description' => 'Chemises formelles et décontractées'],
            ['nom' => 'Pantalons',          'description' => 'Pantalons et jeans'],
            ['nom' => 'Shorts',             'description' => 'Shorts et bermudas'],
            ['nom' => 'Robes',              'description' => 'Robes et tuniques'],
            ['nom' => 'Jupes',              'description' => 'Jupes longues et courtes'],
            ['nom' => 'Vestes',             'description' => 'Vestes et blazers'],
            ['nom' => 'Manteaux',           'description' => 'Manteaux et imperméables'],
            ['nom' => 'Pulls',              'description' => 'Pulls et sweatshirts'],
            ['nom' => 'Sous-vêtements',     'description' => 'Sous-vêtements et lingerie'],
            ['nom' => 'Chaussettes',        'description' => 'Chaussettes et collants'],
            ['nom' => 'Chaussures Homme',   'description' => 'Chaussures pour hommes'],
            ['nom' => 'Chaussures Femme',   'description' => 'Chaussures pour femmes'],
            ['nom' => 'Chaussures Enfant',  'description' => 'Chaussures pour enfants'],
            ['nom' => 'Sacs à main',        'description' => 'Sacs à main et pochettes'],
            ['nom' => 'Sacs à dos',         'description' => 'Sacs à dos et cartables'],
            ['nom' => 'Ceintures',          'description' => 'Ceintures et bretelles'],
            ['nom' => 'Écharpes',           'description' => 'Écharpes et foulards'],
            ['nom' => 'Chapeaux',           'description' => 'Chapeaux, casquettes et bonnets'],
            ['nom' => 'Lunettes',           'description' => 'Lunettes de vue et soleil'],
            ['nom' => 'Bijoux',             'description' => 'Bijoux et accessoires'],
            ['nom' => 'Montres',            'description' => 'Montres et bracelets connectés'],
            ['nom' => 'Parfums',            'description' => 'Parfums et déodorants'],
            ['nom' => 'Vêtements Bébé',     'description' => 'Vêtements pour bébés 0-24 mois'],
            ['nom' => 'Vêtements Enfant',   'description' => 'Vêtements pour enfants 2-12 ans'],
            ['nom' => 'Vêtements Ado',      'description' => 'Vêtements pour adolescents'],
            ['nom' => 'Sport & Fitness',    'description' => 'Vêtements et accessoires de sport'],
            ['nom' => 'Maillots de bain',   'description' => 'Maillots de bain et tenues de plage'],
            ['nom' => 'Pyjamas',            'description' => 'Pyjamas et tenues de nuit'],
            ['nom' => 'Costumes',           'description' => 'Costumes et tenues formelles'],
            ['nom' => 'Tenues Traditionnelles', 'description' => 'Boubous, bazins et tenues traditionnelles'],
            ['nom' => 'Tissus',             'description' => 'Tissus au mètre et coupons'],
            ['nom' => 'Accessoires Sport',  'description' => 'Équipements et accessoires sportifs'],
            ['nom' => 'Maroquinerie',       'description' => 'Articles en cuir et similicuir'],
            ['nom' => 'Gants',              'description' => 'Gants et moufles'],
            ['nom' => 'Collants',           'description' => 'Collants et leggings'],
            ['nom' => 'Combinaisons',       'description' => 'Combinaisons et salopettes'],
            ['nom' => 'Gilets',             'description' => 'Gilets et bodys'],
            ['nom' => 'Polos',              'description' => 'Polos et t-shirts col V'],
            ['nom' => 'Lingerie de Luxe',   'description' => 'Lingerie fine et déshabillés'],
        ];

        foreach ($categories as $cat) {
            Categorie::firstOrCreate(
                ['nom' => $cat['nom']],
                ['slug' => Str::slug($cat['nom']), 'description' => $cat['description']]
            );
        }
    }
}
