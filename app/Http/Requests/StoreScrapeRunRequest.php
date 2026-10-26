<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreScrapeRunRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url:http,https', 'max:2000', function (string $attribute, mixed $value, \Closure $fail): void {
                $host = trim((string) parse_url($value, PHP_URL_HOST), '[]');
                $port = parse_url($value, PHP_URL_PORT);
                if (parse_url($value, PHP_URL_USER) !== null || parse_url($value, PHP_URL_PASS) !== null
                    || ($port && ! in_array($port, [80, 443], true))
                    || in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true)
                    || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
                    $fail('Usá una URL pública HTTP(S), sin credenciales y con puerto 80 o 443.');
                }
            }],
            'item_selector' => ['required', 'string', 'max:500'],
            'next_selector' => ['nullable', 'string', 'max:500'],
            'max_pages' => ['required', 'integer', 'between:1,5'],
            'fields' => ['required', 'array', 'list', 'min:1', 'max:10'],
            'fields.*' => ['required', 'array:name,selector,attribute'],
            'fields.*.name' => ['required', 'string', 'regex:/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/', 'distinct:strict'],
            'fields.*.selector' => ['required', 'string', 'max:500'],
            'fields.*.attribute' => ['nullable', 'string', 'regex:/^[a-zA-Z][a-zA-Z0-9_-]{0,49}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('fields_json'))) {
            $this->merge(['fields' => json_decode($this->input('fields_json'), true)]);
        } elseif (is_string($this->input('fields'))) {
            $this->merge(['fields' => json_decode($this->input('fields'), true)]);
        }
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'url.url' => 'Ingresá una URL HTTP(S) válida.',
            'fields.array' => 'Los campos deben ser un arreglo JSON válido.',
            'fields.*.name.distinct' => 'Cada campo debe tener un nombre diferente.',
            'fields.*.name.regex' => 'El nombre debe comenzar con una letra y usar letras, números o guiones bajos.',
            'max_pages.between' => 'Podés extraer entre 1 y 5 páginas por ejecución.',
        ];
    }
}
