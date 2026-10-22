<?php

namespace Tests\Feature;

use App\Models\Sortie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class RapportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_excel_produit_un_xlsx_avec_les_ventes(): void
    {
        $admin = $this->actingAsAdmin();
        Sortie::create([
            'reference'     => 'VTE-EXPORT-1',
            'type'          => 'VENTE',
            'total_montant' => 15000,
            'user_id'       => $admin->id,
        ]);

        $response = $this->get('/api/v1/rapports/export/excel');

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('rapport-ventes.xlsx', $response->headers->get('Content-Disposition'));

        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('Référence', $sheet->getCell('A1')->getValue());
        $this->assertSame('VTE-EXPORT-1', $sheet->getCell('A2')->getValue());
        $this->assertSame($admin->email, $sheet->getCell('E2')->getValue());
    }
}
