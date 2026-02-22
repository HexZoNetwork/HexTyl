<?php

namespace Pterodactyl\Http\Requests\Admin;

use Illuminate\Validation\Validator;
use Pterodactyl\Models\Mount;

class MountFormRequest extends AdminFormRequest
{
    /**
     * Set up the validation rules to use for these requests.
     */
    public function rules(): array
    {
        if ($this->method() === 'PATCH') {
            return Mount::getRulesForUpdate($this->route()->parameter('mount')->id); // @phpstan-ignore property.nonObject
        }

        return Mount::getRules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $source = strtolower((string) $this->input('source', ''));
            if ($source === '') {
                return;
            }

            $normalized = '/' . ltrim($source, '/');
            $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;
            $normalized = rtrim($normalized, '/');
            if ($normalized === '') {
                $normalized = '/';
            }

            foreach (Mount::$invalidSourcePrefixes as $prefix) {
                if (str_starts_with($normalized, $prefix)) {
                    $validator->errors()->add('source', 'Source path is blocked by security policy.');

                    return;
                }
            }
        });
    }
}
