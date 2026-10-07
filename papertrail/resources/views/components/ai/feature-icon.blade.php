@props([
    'type' => 'completeness',
])

@switch($type)
    @case('routing')
        <svg class="ai-feature-icon ai-feature-icon--routing" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
            <path class="ai-feature-icon__path" d="M14 15c6 0 7.5 4 10 8s4 7 10 7" />
            <path class="ai-feature-icon__path ai-feature-icon__path--soft" d="M17 31h12" />
            <path class="ai-feature-icon__pin" d="M14 8a6.5 6.5 0 0 0-6.5 6.5c0 5 6.5 11.5 6.5 11.5s6.5-6.5 6.5-11.5A6.5 6.5 0 0 0 14 8Z" />
            <circle class="ai-feature-icon__pin-hole" cx="14" cy="14.5" r="2" />
            <path class="ai-feature-icon__pin" d="M34 22a6.5 6.5 0 0 0-6.5 6.5C27.5 33.5 34 40 34 40s6.5-6.5 6.5-11.5A6.5 6.5 0 0 0 34 22Z" />
            <circle class="ai-feature-icon__pin-hole" cx="34" cy="28.5" r="2" />
            <circle class="ai-feature-icon__ai-badge" cx="34.5" cy="13.5" r="8.5" />
            <text class="ai-feature-icon__ai-text" x="34.5" y="16.6" text-anchor="middle">AI</text>
            <path class="ai-feature-icon__spark" d="M41 5.5v4M39 7.5h4" />
        </svg>
        @break

    @case('risk')
        <svg class="ai-feature-icon ai-feature-icon--risk" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
            <path class="ai-feature-icon__bar" d="M33 33V23" />
            <path class="ai-feature-icon__bar ai-feature-icon__bar--mid" d="M39 33V17" />
            <path class="ai-feature-icon__bar ai-feature-icon__bar--low" d="M27 33v-6" />
            <path class="ai-feature-icon__warning" d="M18.2 8.5 5.5 32.4a3 3 0 0 0 2.65 4.4h25.7a3 3 0 0 0 2.65-4.4L23.8 8.5a3.16 3.16 0 0 0-5.6 0Z" />
            <path class="ai-feature-icon__bang" d="M21 17.5v9" />
            <circle class="ai-feature-icon__dot" cx="21" cy="31" r="2.1" />
            <circle class="ai-feature-icon__ai-badge" cx="34.5" cy="12.5" r="8.5" />
            <text class="ai-feature-icon__ai-text" x="34.5" y="15.6" text-anchor="middle">AI</text>
            <path class="ai-feature-icon__spark" d="M42 4.5v4M40 6.5h4" />
        </svg>
        @break

    @default
        <svg class="ai-feature-icon ai-feature-icon--completeness" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
            <path class="ai-feature-icon__paper" d="M14 6.5h15.5L38 15v25.5H14z" />
            <path class="ai-feature-icon__fold" d="M29.5 6.5V15H38" />
            <path class="ai-feature-icon__check" d="m18.2 19.2 2.4 2.4 4.5-4.9" />
            <path class="ai-feature-icon__line" d="M27.6 20h5.2" />
            <path class="ai-feature-icon__check" d="m18.2 27 2.4 2.4 4.5-4.9" />
            <path class="ai-feature-icon__line" d="M27.6 27.8h5.2" />
            <path class="ai-feature-icon__check" d="m18.2 34.8 2.4 2.4 4.5-4.9" />
            <circle class="ai-feature-icon__ai-badge" cx="34.5" cy="33.5" r="8.5" />
            <text class="ai-feature-icon__ai-text" x="34.5" y="36.6" text-anchor="middle">AI</text>
            <path class="ai-feature-icon__spark" d="M42 25.5v4M40 27.5h4" />
        </svg>
@endswitch
