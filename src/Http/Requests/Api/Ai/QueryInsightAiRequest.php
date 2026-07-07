<?php

/**
 * Form request for the query insight AI endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Http\Requests\Api\Ai;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the JSON payload submitted to the query-insight endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.1.0
 */
class QueryInsightAiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The endpoint is already gated by the route middleware stack; this
     * request-level check exists so overrides in host apps have a hook.
     *
     * @since 1.1.0
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @since 1.1.0
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'query'      => [ 'required', 'string', 'max:16000' ],
            'explain'    => [ 'nullable' ],
            'schema'     => [ 'nullable' ],
            'time_ms'    => [ 'nullable', 'numeric', 'min:0' ],
            'connection' => [ 'nullable', 'string', 'max:64' ],
        ];
    }
}
