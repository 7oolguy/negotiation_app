<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\Guardian;
use App\Models\ImportJob;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Parses, validates, deduplicates, and imports overdue tuition CSVs into
 * guardians, students, debts, and debt_installments while recording an ImportJob.
 */
class DebtImportService
{
    /**
     * Parse CSV content into headers and associative row arrays for preview or import.
     *
     * @return array{headers: list<string>, rows: list<array<string, string>>, total: int}
     */
    public function parseCsv(string $csvContent, ?int $limit = null): array
    {
        $normalized = ltrim($csvContent, "\xEF\xBB\xBF");
        $lines = preg_split('/\r\n|\r|\n/', trim($normalized)) ?: [];
        $lines = array_values(array_filter($lines, fn (string $line): bool => trim($line) !== ''));

        if ($lines === []) {
            return ['headers' => [], 'rows' => [], 'total' => 0];
        }

        // Brazilian Excel exports frequently use ';' instead of ','.
        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $headers = array_map(
            fn (?string $h): string => strtolower(trim((string) $h)),
            str_getcsv($lines[0], $delimiter, '"', '\\'),
        );

        $dataLines = array_slice($lines, 1);
        $total = count($dataLines);

        if ($limit !== null) {
            $dataLines = array_slice($dataLines, 0, $limit);
        }

        $rows = [];
        foreach ($dataLines as $line) {
            $values = str_getcsv($line, $delimiter, '"', '\\');
            $padded = array_pad($values, count($headers), '');
            $rows[] = array_combine($headers, array_map(fn (?string $v): string => trim((string) $v), array_slice($padded, 0, count($headers))));
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'total' => $total,
        ];
    }

    /**
     * Import validated debt rows for a school and persist an ImportJob audit record.
     */
    public function import(
        School $school,
        string $csvContent,
        string $fileName = 'import.csv',
        ?string $filePath = null,
        ?User $actor = null,
    ): ImportJob {
        $parsed = $this->parseCsv($csvContent);
        $rows = $parsed['rows'];

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // 1-based line number including header row

            $validationError = $this->validateRow($row);
            if ($validationError !== null) {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'type' => 'validation', 'message' => $validationError];

                continue;
            }

            $externalRef = $row['external_reference'];

            // Prevent duplicate debt creation for the same school + external_reference.
            if (Debt::where('school_id', $school->id)->where('external_reference', $externalRef)->exists()) {
                $skipped++;
                $errors[] = [
                    'row' => $rowNumber,
                    'type' => 'duplicate',
                    'message' => "Skipped duplicate debt with external_reference '{$externalRef}'.",
                ];

                continue;
            }

            try {
                DB::transaction(function () use ($school, $row, $externalRef): void {
                    $document = preg_replace('/\D/', '', $row['guardian_document']);
                    $documentType = strlen($document) === 14 ? 'cnpj' : 'cpf';

                    $guardian = Guardian::firstOrCreate(
                        [
                            'school_id' => $school->id,
                            'document' => $document,
                        ],
                        [
                            'name' => $row['guardian_name'],
                            'document_type' => $documentType,
                            'email' => $row['guardian_email'] ?? null ?: null,
                            'phone' => $row['guardian_phone'] ?? null ?: null,
                        ],
                    );

                    $enrollmentCode = $row['enrollment_code'] ?? null ?: null;
                    $studentQuery = Student::where('school_id', $school->id);

                    $student = $enrollmentCode
                        ? $studentQuery->where('enrollment_code', $enrollmentCode)->first()
                        : null;

                    if (! $student) {
                        $student = Student::create([
                            'school_id' => $school->id,
                            'name' => $row['student_name'],
                            'enrollment_code' => $enrollmentCode,
                            'cpf' => isset($row['student_cpf']) ? (preg_replace('/\D/', '', $row['student_cpf']) ?: null) : null,
                            'grade_level' => $row['grade_level'] ?? null ?: null,
                            'status' => 'active',
                        ]);
                    }

                    $student->guardians()->syncWithoutDetaching([
                        $guardian->id => [
                            'responsibility_type' => $row['responsibility_type'] ?? null ?: 'financial',
                            'is_primary' => true,
                        ],
                    ]);

                    $principalCents = (int) $row['principal_cents'];
                    $interestCents = (int) ($row['interest_cents'] ?? 0);
                    $fineCents = (int) ($row['fine_cents'] ?? 0);
                    $correctionCents = (int) ($row['correction_cents'] ?? 0);

                    $debt = Debt::create([
                        'school_id' => $school->id,
                        'student_id' => $student->id,
                        'guardian_id' => $guardian->id,
                        'external_reference' => $externalRef,
                        'principal_cents' => $principalCents,
                        'interest_cents' => $interestCents,
                        'fine_cents' => $fineCents,
                        'correction_cents' => $correctionCents,
                        'status' => 'imported',
                    ]);

                    $debt->installments()->create([
                        'installment_number' => (int) ($row['installment_number'] ?? 1 ?: 1),
                        'external_reference' => $externalRef,
                        'due_date' => Carbon::parse($row['due_date'])->toDateString(),
                        'principal_cents' => $principalCents,
                        'interest_cents' => $interestCents,
                        'fine_cents' => $fineCents,
                        'correction_cents' => $correctionCents,
                        'paid_cents' => 0,
                        'status' => 'open',
                    ]);
                });

                $imported++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'type' => 'error', 'message' => $e->getMessage()];
            }
        }

        $job = ImportJob::create([
            'school_id' => $school->id,
            'user_id' => $actor?->getKey() ?? Auth::id(),
            'file_name' => $fileName,
            'file_path' => $filePath,
            'status' => $failed > 0 && $imported === 0 ? 'failed' : 'completed',
            'total_rows' => count($rows),
            'imported_rows' => $imported,
            'skipped_rows' => $skipped,
            'failed_rows' => $failed,
            'errors' => $errors ?: null,
            'completed_at' => now(),
        ]);

        AuditLogger::log(
            event: 'debt_import_completed',
            auditable: $job,
            newValues: $job->only(['school_id', 'file_name', 'total_rows', 'imported_rows', 'skipped_rows', 'failed_rows']),
        );

        return $job;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function validateRow(array $row): ?string
    {
        foreach (['guardian_name', 'guardian_document', 'student_name', 'external_reference', 'principal_cents', 'due_date'] as $required) {
            if (! isset($row[$required]) || $row[$required] === '') {
                return "Missing required column '{$required}'.";
            }
        }

        $digits = preg_replace('/\D/', '', $row['guardian_document']);
        if (! in_array(strlen($digits), [11, 14], true)) {
            return 'guardian_document must be an 11-digit CPF or 14-digit CNPJ.';
        }

        if (! ctype_digit($row['principal_cents']) || (int) $row['principal_cents'] < 0) {
            return 'principal_cents must be a non-negative integer.';
        }

        try {
            Carbon::parse($row['due_date']);
        } catch (Throwable) {
            return "Invalid due_date '{$row['due_date']}'.";
        }

        return null;
    }
}
