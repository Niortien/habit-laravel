<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La règle « email » de Laravel 10 accepte un retour à la ligne dans une partie
 * locale entre guillemets (GHSA-5vg9-5847-vvmq, corrigé seulement en 12.60) :
 * les adresses qui partent vers l'envoi de mails doivent le refuser.
 */
class EmailControlCharsTest extends TestCase
{
    use RefreshDatabase;

    private const PIEGE = "\"victime\r\n Bcc:pirate@exemple.org\"@exemple.com";

    public function test_mot_de_passe_oublie_refuse_un_retour_a_la_ligne(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => self::PIEGE])
            ->assertStatus(422);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'jean.dupont@exemple.com'])
            ->assertStatus(200);
    }

    public function test_creation_utilisateur_refuse_un_retour_a_la_ligne(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/users', ['email' => self::PIEGE, 'password' => 'motdepasse123'])
            ->assertStatus(422);

        $this->postJson('/api/v1/users', ['email' => 'jean.dupont@exemple.com', 'password' => 'motdepasse123'])
            ->assertStatus(201);
    }
}
