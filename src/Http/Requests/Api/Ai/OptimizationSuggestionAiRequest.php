<?php

/**
 * Form request for the optimization suggestion AI endpoint.
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
 * Validates the JSON payload submitted to the optimization-suggestion endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.1.0
 */
class OptimizationSuggestionAiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            'range'                       => [ 'required', 'array' ],
            'range.from'                  => [ 'required', 'string', 'date' ],
            'range.to'                    => [ 'required', 'string', 'date', 'after_or_equal:range.from' ],
            'metrics'                     => [ 'required', 'array', 'max:1000' ],
            'metrics.*'                   => [ 'array' ],
            'context'                     => [ 'nullable', 'array' ],
            'context.traffic_mix'         => [ 'nullable', 'string', 'max:255' ],
            'context.recent_changes'      => [ 'nullable', 'string', 'max:2000' ],
            'context.business_priority'   => [ 'nullable', 'string', 'max:255' ],
        ];
    }
}
