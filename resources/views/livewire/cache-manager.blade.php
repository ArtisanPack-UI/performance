<div class="performance-cache-manager" data-testid="performance-cache-manager">
    @if($statusMessage !== null)
        <div @class([
            'performance-cache-manager__status',
            'is-error' => $statusIsError,
            'is-success' => ! $statusIsError,
        ]) data-testid="status-banner">
            {{ $statusMessage }}
        </div>
    @endif

    <section class="performance-cache-manager__section">
        <h3 class="performance-cache-manager__heading">{{ __( 'Page Cache' ) }}</h3>

        <dl class="performance-cache-manager__summary">
            <div>
                <dt>{{ __( 'Entries' ) }}</dt>
                <dd>{{ number_format( $pageSummary['entries'] ) }}</dd>
            </div>
            <div>
                <dt>{{ __( 'Size' ) }}</dt>
                <dd>{{ $pageSummary['size_bytes'] !== null ? number_format( $pageSummary['size_bytes'] ) . ' B' : __( 'N/A' ) }}</dd>
            </div>
            <div>
                <dt>{{ __( 'Hit rate' ) }}</dt>
                <dd>{{ $pageSummary['hit_rate'] !== null ? number_format( $pageSummary['hit_rate'] * 100, 2 ) . '%' : __( 'N/A' ) }}</dd>
            </div>
        </dl>

        <form wire:submit.prevent="requestKeyInvalidation" class="performance-cache-manager__inline-form">
            <label for="invalidate-key" class="performance-cache-manager__label">{{ __( 'Invalidate by key' ) }}</label>
            <input
                type="text"
                id="invalidate-key"
                wire:model.live="invalidateKeyInput"
                class="performance-cache-manager__input"
                placeholder="{{ __( 'e.g. products/*' ) }}"
            />
            <button type="submit" class="performance-cache-manager__button">{{ $resolvedLabels['invalidate'] }}</button>
        </form>

        @if($pendingAction === 'key:' . $invalidateKeyInput && $invalidateKeyInput !== '')
            <div class="performance-cache-manager__confirm-bar" data-testid="confirm-key-input">
                <span>{{ __( 'Invalidate page entries matching ":pattern"?', [ 'pattern' => $invalidateKeyInput ] ) }}</span>
                <button type="button" wire:click="invalidate('{{ addslashes( $invalidateKeyInput ) }}')" class="performance-cache-manager__button is-danger">
                    {{ $resolvedLabels['confirm'] }}
                </button>
                <button type="button" wire:click="cancelConfirmation" class="performance-cache-manager__button">
                    {{ $resolvedLabels['cancel'] }}
                </button>
            </div>
        @endif

        @if(! empty($pageEntries))
            <table class="performance-cache-manager__table" data-testid="page-entries">
                <thead>
                    <tr>
                        <th>{{ __( 'Key' ) }}</th>
                        <th>{{ __( 'Path' ) }}</th>
                        <th class="performance-cache-manager__actions-col">{{ __( 'Actions' ) }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pageEntries as $index => $entry)
                        <tr wire:key="page-entry-{{ $index }}">
                            <td><code>{{ $entry['key'] }}</code></td>
                            <td>{{ $entry['path'] }}</td>
                            <td>
                                @if($pendingAction === 'entry:' . $index)
                                    <button type="button" wire:click="invalidateEntry({{ $index }})" class="performance-cache-manager__button is-danger" data-testid="confirm-entry-{{ $index }}">
                                        {{ $resolvedLabels['confirm'] }}
                                    </button>
                                    <button type="button" wire:click="cancelConfirmation" class="performance-cache-manager__button">
                                        {{ $resolvedLabels['cancel'] }}
                                    </button>
                                @else
                                    <button type="button" wire:click="requestConfirmation('entry:{{ $index }}')" class="performance-cache-manager__button">
                                        {{ $resolvedLabels['invalidate'] }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="performance-cache-manager__section">
        <h3 class="performance-cache-manager__heading">{{ __( 'Fragment Cache' ) }}</h3>

        <dl class="performance-cache-manager__summary">
            <div>
                <dt>{{ __( 'Entries' ) }}</dt>
                <dd>{{ number_format( $fragmentSummary['entries'] ) }}</dd>
            </div>
            <div>
                <dt>{{ __( 'Tags' ) }}</dt>
                <dd>{{ number_format( $fragmentSummary['tags'] ) }}</dd>
            </div>
            <div>
                <dt>{{ __( 'Hit rate' ) }}</dt>
                <dd>{{ $fragmentSummary['hit_rate'] !== null ? number_format( $fragmentSummary['hit_rate'] * 100, 2 ) . '%' : __( 'N/A' ) }}</dd>
            </div>
        </dl>

        @if(! empty($fragmentTags))
            <table class="performance-cache-manager__table" data-testid="fragment-tags">
                <thead>
                    <tr>
                        <th>{{ __( 'Tag' ) }}</th>
                        <th>{{ __( 'Entries' ) }}</th>
                        <th class="performance-cache-manager__actions-col">{{ __( 'Actions' ) }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fragmentTags as $index => $tagRow)
                        <tr wire:key="fragment-tag-{{ $index }}">
                            <td><code>{{ $tagRow['tag'] }}</code></td>
                            <td>{{ number_format( $tagRow['entry_count'] ) }}</td>
                            <td>
                                @if($pendingAction === 'fragment-tag:' . $index)
                                    <button type="button" wire:click="invalidateFragmentTagByIndex({{ $index }})" class="performance-cache-manager__button is-danger" data-testid="confirm-tag-{{ $index }}">
                                        {{ $resolvedLabels['confirm'] }}
                                    </button>
                                    <button type="button" wire:click="cancelConfirmation" class="performance-cache-manager__button">
                                        {{ $resolvedLabels['cancel'] }}
                                    </button>
                                @else
                                    <button type="button" wire:click="requestConfirmation('fragment-tag:{{ $index }}')" class="performance-cache-manager__button">
                                        {{ $resolvedLabels['tag'] }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="performance-cache-manager__section performance-cache-manager__actions">
        <button type="button" wire:click="warmCache" class="performance-cache-manager__button is-primary" data-testid="warm-cache">
            {{ $resolvedLabels['warm'] }}
        </button>

        @if($pendingAction === 'flush')
            <button type="button" wire:click="flushAll" class="performance-cache-manager__button is-danger" data-testid="confirm-flush">
                {{ $resolvedLabels['confirm'] }}
            </button>
            <button type="button" wire:click="cancelConfirmation" class="performance-cache-manager__button">
                {{ $resolvedLabels['cancel'] }}
            </button>
        @else
            <button type="button" wire:click="requestConfirmation('flush')" class="performance-cache-manager__button is-danger" data-testid="request-flush">
                {{ $resolvedLabels['purge'] }}
            </button>
        @endif
    </section>
</div>
