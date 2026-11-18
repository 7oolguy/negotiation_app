<?php

namespace App\Filament\Resources\Debts\Pages;

use App\Filament\Resources\Debts\DebtResource;
use App\Models\School;
use App\Services\DebtImportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListDebts extends ListRecords
{
    protected static string $resource = DebtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importDebts')
                ->label('Import Debts (CSV)')
                ->modalDescription('Expected columns: guardian_name, guardian_document, student_name, external_reference, principal_cents, due_date (optional: enrollment_code, interest_cents, fine_cents, correction_cents, guardian_email, guardian_phone).')
                ->schema([
                    Select::make('school_id')
                        ->label('School')
                        ->options(fn () => School::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    FileUpload::make('file')
                        ->label('CSV File')
                        ->disk('private')
                        ->directory('imports/debts')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->live()
                        ->required(),
                    Placeholder::make('preview')
                        ->label('Preview')
                        ->content(function (Get $get): HtmlString|string {
                            $file = $get('file');
                            $uploaded = is_array($file) ? reset($file) : $file;

                            if (! $uploaded instanceof TemporaryUploadedFile) {
                                return 'Upload a CSV file to preview rows before importing.';
                            }

                            $content = $uploaded->get();
                            $parsed = app(DebtImportService::class)->parseCsv($content, limit: 5);

                            if ($parsed['total'] === 0) {
                                return 'No data rows found in CSV.';
                            }

                            $items = array_map(
                                fn (array $row): string => '<li>'.e(implode(' | ', array_slice($row, 0, 5))).'</li>',
                                $parsed['rows'],
                            );

                            return new HtmlString(
                                '<div class="text-xs space-y-1">'
                                .'<div><strong>Total rows:</strong> '.e((string) $parsed['total']).'</div>'
                                .'<ul class="list-disc pl-4">'.implode('', $items).'</ul>'
                                .'</div>'
                            );
                        }),
                ])
                ->action(function (array $data): void {
                    $school = School::findOrFail($data['school_id']);
                    $path = $data['file'];
                    $content = Storage::disk('private')->get($path);

                    $job = app(DebtImportService::class)->import(
                        school: $school,
                        csvContent: (string) $content,
                        fileName: basename($path),
                        filePath: $path,
                    );

                    Notification::make()
                        ->title('Debt import finished')
                        ->body("Imported: {$job->imported_rows} | Skipped duplicates: {$job->skipped_rows} | Failed: {$job->failed_rows}")
                        ->color($job->failed_rows > 0 ? 'warning' : 'success')
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
