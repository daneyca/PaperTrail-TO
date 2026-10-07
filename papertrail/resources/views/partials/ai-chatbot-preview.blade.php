@php
    $hideAssistantWidget = request()->routeIs('assistant.*', '*.print') || request()->boolean('modal');
@endphp

<x-ai.floating-assistant :hidden="$hideAssistantWidget" />
