<?php

namespace Database\Seeders;

use App\Models\LegalEntity;
use App\Models\School;
use Illuminate\Database\Seeder;

class InstitutionalSeeder extends Seeder
{
    /**
     * Seed sample legal entities (mantenedoras) and their schools idempotently.
     */
    public function run(): void
    {
        $entities = [
            [
                'legal_name' => 'Rede Educacional Horizonte Ltda',
                'trade_name' => 'Grupo Horizonte',
                'cnpj' => '12345678000190',
                'email' => 'financeiro@horizonte.edu.br',
                'phone' => '(11) 3000-1000',
                'address' => 'Av. Paulista, 1000',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01310100',
                'country' => 'Brasil',
                'is_active' => true,
                'schools' => [
                    [
                        'name' => 'Colégio Horizonte Centro',
                        'legal_name' => 'Colégio Horizonte Centro Ltda',
                        'cnpj' => '12345678000271',
                        'email' => 'centro@horizonte.edu.br',
                        'phone' => '(11) 3000-1001',
                        'address' => 'Rua Augusta, 450',
                        'city' => 'São Paulo',
                        'state' => 'SP',
                        'zip_code' => '01305000',
                        'country' => 'Brasil',
                        'website' => 'https://horizonte.edu.br',
                        'status' => 'active',
                    ],
                    [
                        'name' => 'Colégio Horizonte Sul',
                        'legal_name' => 'Colégio Horizonte Sul Ltda',
                        'cnpj' => '12345678000352',
                        'email' => 'sul@horizonte.edu.br',
                        'phone' => '(11) 3000-1002',
                        'address' => 'Av. Santo Amaro, 2100',
                        'city' => 'São Paulo',
                        'state' => 'SP',
                        'zip_code' => '04556100',
                        'country' => 'Brasil',
                        'website' => 'https://horizonte.edu.br',
                        'status' => 'active',
                    ],
                ],
            ],
            [
                'legal_name' => 'Instituto Saber Educacional S/A',
                'trade_name' => 'Instituto Saber',
                'cnpj' => '98765432000110',
                'email' => 'contato@institutosaber.edu.br',
                'phone' => '(21) 3500-2000',
                'address' => 'Av. Rio Branco, 500',
                'city' => 'Rio de Janeiro',
                'state' => 'RJ',
                'zip_code' => '20040002',
                'country' => 'Brasil',
                'is_active' => true,
                'schools' => [
                    [
                        'name' => 'Escola Saber Botafogo',
                        'legal_name' => 'Escola Saber Botafogo Ltda',
                        'cnpj' => '98765432000291',
                        'email' => 'botafogo@institutosaber.edu.br',
                        'phone' => '(21) 3500-2001',
                        'address' => 'Rua Voluntários da Pátria, 320',
                        'city' => 'Rio de Janeiro',
                        'state' => 'RJ',
                        'zip_code' => '22270010',
                        'country' => 'Brasil',
                        'website' => 'https://institutosaber.edu.br',
                        'status' => 'active',
                    ],
                ],
            ],
        ];

        foreach ($entities as $entityData) {
            $schools = $entityData['schools'];
            unset($entityData['schools']);

            $legalEntity = LegalEntity::firstOrCreate(
                ['cnpj' => $entityData['cnpj']],
                $entityData,
            );

            foreach ($schools as $schoolData) {
                School::firstOrCreate(
                    ['cnpj' => $schoolData['cnpj']],
                    [
                        ...$schoolData,
                        'legal_entity_id' => $legalEntity->id,
                    ],
                );
            }
        }
    }
}
