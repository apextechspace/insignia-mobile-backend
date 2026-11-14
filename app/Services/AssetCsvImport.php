<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AssetCsvImport
{
    public const HEADERS = ['asset_code', 'name', 'category', 'room', 'condition', 'brand', 'model_number', 'serial_number', 'purchase_date', 'notes'];

    public function preview(TemporaryUploadedFile $file): array
    {
        $stream = $file->readStream();
        if (! is_resource($stream)) {
            return ['rows' => [], 'errors' => ['The upload could not be read. Upload the file again.']];
        }
        $rows = [];
        $errors = [];
        $seen = [];
        $existing = Asset::pluck('asset_code')->map(fn ($code) => mb_strtolower(trim($code)))->flip();
        $rooms = Room::pluck('name', 'id')->groupBy(fn ($name) => mb_strtolower(trim($name)), preserveKeys: true);

        try {
            $headers = fgetcsv($stream, 0, ',', '"', '') ?: [];
            $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $headers);
            if ($headers) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            }
            if (array_diff(['asset_code', 'name', 'category'], $headers)
                || array_diff($headers, self::HEADERS) || count($headers) !== count(array_unique($headers))) {
                return ['rows' => [], 'errors' => ['Use the template headers. Asset code, name, and category are required.']];
            }

            $line = 1;
            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $line++;
                if ($line > 501) {
                    $errors[] = 'Upload at most 500 asset rows per file.';
                    break;
                }
                $values = array_map(fn ($value) => trim((string) $value), $values);
                if (! mb_check_encoding($values, 'UTF-8')) {
                    $errors[] = "Row {$line}: Save the spreadsheet as CSV UTF-8 and upload it again.";
                    continue;
                }
                if (! array_filter($values, fn ($value) => $value !== '')) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    $errors[] = "Row {$line}: Column count does not match the header.";
                    continue;
                }

                $row = array_combine($headers, array_map(fn ($value) => $value === '' ? null : $value, $values));
                $row['condition'] ??= 'good';
                $room = $row['room'] ?? null;
                unset($row['room']);
                $matches = $room === null ? null : $rooms->get(mb_strtolower($room));
                if ($room !== null && $matches?->count() !== 1) {
                    $errors[] = "Row {$line}: Suite name must match exactly one registered room.";
                    continue;
                }
                $row['room_id'] = $matches?->keys()->first();
                $validator = Validator::make($row, [
                    'asset_code' => ['required', 'string', 'max:255'],
                    'name' => ['required', 'string', 'max:255'],
                    'category' => ['required', Rule::in(['electronics', 'furniture', 'kitchen_appliance', 'plumbing', 'lighting', 'hvac', 'other'])],
                    'condition' => ['required', Rule::in(['brand_new', 'good', 'fair', 'needs_repair', 'condemned'])],
                    'brand' => ['nullable', 'string', 'max:255'],
                    'model_number' => ['nullable', 'string', 'max:255'],
                    'serial_number' => ['nullable', 'string', 'max:255'],
                    'purchase_date' => ['nullable', 'date_format:Y-m-d'],
                    'notes' => ['nullable', 'string', 'max:4000'],
                ]);
                if ($validator->fails()) {
                    $errors[] = "Row {$line}: ".implode(' ', $validator->errors()->all());
                    continue;
                }
                $code = mb_strtolower($row['asset_code']);
                if ($existing->has($code) || isset($seen[$code])) {
                    $errors[] = "Row {$line}: Duplicate asset tag {$row['asset_code']}.";
                    continue;
                }
                $seen[$code] = true;
                $rows[] = $row;
            }
        } finally {
            fclose($stream);
        }

        if (! $rows && ! $errors) {
            $errors[] = 'The CSV contains no asset rows.';
        }

        return compact('rows', 'errors');
    }

    public function import(TemporaryUploadedFile $file): int
    {
        return DB::transaction(function () use ($file): int {
            $preview = $this->preview($file);
            if ($preview['errors']) {
                throw ValidationException::withMessages(['csv' => implode(' ', array_slice($preview['errors'], 0, 5))]);
            }
            foreach ($preview['rows'] as $row) {
                Asset::create($row);
            }

            return count($preview['rows']);
        });
    }
}
