<?php

namespace App\Filament\Resources\Debts\Schemas;

use App\Models\Debt;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DebtInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $formatBrl = static fn (?int $cents): string => 'R$ '.number_format(((int) $cents) / 100, 2, ',', '.');

        return $schema
            ->components([
                Section::make('Resumo do Caso')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('school.name')
                            ->label('Escola'),
                        TextEntry::make('student.name')
                            ->label('Aluno'),
                        TextEntry::make('guardian.name')
                            ->label('Responsável Financeiro'),
                        TextEntry::make('guardian.document')
                            ->label('CPF / CNPJ')
                            ->placeholder('-'),
                        TextEntry::make('guardian.phone')
                            ->label('Telefone')
                            ->placeholder('-'),
                        TextEntry::make('guardian.email')
                            ->label('E-mail')
                            ->placeholder('-'),
                        TextEntry::make('external_reference')
                            ->label('Referência Externa')
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->label('Status do Ciclo')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => Debt::STATUSES[$state] ?? $state)
                            ->color(fn (string $state): string => match ($state) {
                                Debt::STATUS_ACTIVE_COLLECTION, Debt::STATUS_NEGOTIATING => 'warning',
                                Debt::STATUS_AGREED, Debt::STATUS_SETTLED => 'success',
                                Debt::STATUS_WRITTEN_OFF => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('activeAssignment.collector.name')
                            ->label('Cobrador Responsável')
                            ->placeholder('Não atribuído'),
                    ]),
                Section::make('Valores (R$)')
                    ->columns(5)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('principal_cents')
                            ->label('Principal')
                            ->formatStateUsing($formatBrl),
                        TextEntry::make('interest_cents')
                            ->label('Juros')
                            ->formatStateUsing($formatBrl),
                        TextEntry::make('fine_cents')
                            ->label('Multa')
                            ->formatStateUsing($formatBrl),
                        TextEntry::make('correction_cents')
                            ->label('Correção')
                            ->formatStateUsing($formatBrl),
                        TextEntry::make('total_amount')
                            ->label('Total')
                            ->state(fn (Debt $record): int => $record->totalAmountCents())
                            ->formatStateUsing($formatBrl)
                            ->weight('bold'),
                    ]),
                Section::make('Retenções Operacionais')
                    ->description('Quando qualquer retenção está ativa, contatos externos de cobrança ficam bloqueados.')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        IconEntry::make('is_disputed')
                            ->label('Contestada')
                            ->boolean()
                            ->trueColor('danger')
                            ->falseColor('gray'),
                        IconEntry::make('is_suspended')
                            ->label('Suspensa')
                            ->boolean()
                            ->trueColor('danger')
                            ->falseColor('gray'),
                        IconEntry::make('under_payment_verification')
                            ->label('Pagamento em Verificação')
                            ->boolean()
                            ->trueColor('warning')
                            ->falseColor('gray'),
                    ]),
                Section::make('Observações do Caso')
                    ->columnSpanFull()
                    ->visible(fn (Debt $record): bool => filled($record->notes))
                    ->schema([
                        TextEntry::make('notes')
                            ->hiddenLabel()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
