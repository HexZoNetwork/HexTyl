<?php

namespace Pterodactyl\Http\Requests\Api\Remote;

use Illuminate\Foundation\Http\FormRequest;

class ReportBackupCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'successful' => ['required', 'boolean'],
            'checksum' => ['nullable', 'string', 'max:255', 'regex:/^[A-Fa-f0-9]{16,128}$/', 'required_if:successful,true'],
            'checksum_type' => ['nullable', 'string', 'in:md5,sha1,sha224,sha256,sha384,sha512,blake2b', 'required_if:successful,true'],
            'size' => ['nullable', 'integer', 'min:1', 'max:549755813888', 'required_if:successful,true'],
            'parts' => ['nullable', 'array', 'max:10000'],
            'parts.*.etag' => ['required', 'string', 'max:255', 'regex:/^\"?[A-Fa-f0-9]{16,128}\"?$/'],
            'parts.*.part_number' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
