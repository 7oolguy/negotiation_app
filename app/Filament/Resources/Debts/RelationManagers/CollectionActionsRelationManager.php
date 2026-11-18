<?php

namespace App\Filament\Resources\Debts\RelationManagers;

use App\Models\CollectionAction;
use App\Models\Debt;
use App\Models\User;
use App\Services\DebtLifecycleService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CollectionActionsRelationManager extends RelationManager
{
    protected static string $relationship = 'collectionActions';

    protected static ?string $title = 'Linha do Tempo de Cobrança';

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $isSchoolScoped = $user instanceof User && $user->isSchoolScoped();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user): Builder {
                if ($user instanceof User) {
                    $query->forUser($user);
                }

                return $query->with('user');
            })
            ->defaultSort('acted_at', 'desc')
            ->columns([
                TextColumn::make('acted_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CollectionAction::CHANNELS[$state] ?? $state),
                TextColumn::make('outcome')
                    ->label('Resultado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CollectionAction::OUTCOMES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'disputed', 'refused' => 'danger',
                        'reported_paid', 'promise_to_pay', 'negotiating' => 'warning',
                        'contacted', 'message_sent' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('notes')
                    ->label('Observações')
                    ->wrap()
                    ->placeholder('-'),
                TextColumn::make('user.name')
                    ->label('Registrado por')
                    ->placeholder('Sistema'),
                IconColumn::make('is_internal')
                    ->label('Interno')
                    ->boolean()
                    ->trueIcon(Heroicon::LockClosed)
                    ->trueColor('warning')
                    ->falseIcon(Heroicon::Eye)
                    ->falseColor('gray')
                    ->visible(! $isSchoolScoped),
                TextColumn::make('evidence_original_name')
                    ->label('Evidência')
                    ->placeholder('-')
                    ->url(fn (CollectionAction $record): ?string => $record->evidence_path
                        ? route('collection-actions.evidence', $record)
                        : null)
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                Action::make('logAction')
                    ->label('Registrar Ação')
                    ->icon(Heroicon::Plus)
                    ->visible(! $isSchoolScoped)
                    ->schema([
                        Select::make('channel')
                            ->label('Canal')
                            ->options(CollectionAction::CHANNELS)
                            ->default(CollectionAction::CHANNEL_WHATSAPP)
                            ->required(),
                        Select::make('outcome')
                            ->label('Resultado')
                            ->options(CollectionAction::OUTCOMES)
                            ->default('contacted')
                            ->required(),
                        Toggle::make('is_internal')
                            ->label('Nota interna (oculta para a escola)')
                            ->default(false),
                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3),
                        FileUpload::make('evidence_path')
                            ->label('Evidência (disco privado)')
                            ->disk('private')
                            ->directory('collection-evidence')
                            ->visibility('private')
                            ->storeFileNamesIn('evidence_original_name'),
                    ])
                    ->action(function (array $data): void {
                        /** @var Debt $debt */
                        $debt = $this->getOwnerRecord();

                        try {
                            app(DebtLifecycleService::class)->logAction($debt, $data);

                            Notification::make()
                                ->title('Ação registrada')
                                ->success()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title('Ação bloqueada')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading('Nenhuma ação registrada')
            ->emptyStateDescription('Use "Registrar Ação" para documentar contatos ou observações deste caso.');
    }
}
