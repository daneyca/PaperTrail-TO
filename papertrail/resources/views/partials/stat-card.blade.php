<x-dashboard.stat-card
    :href="$stat['href'] ?? null"
    :value="$stat['value'] ?? '0'"
    :label="$stat['label'] ?? ''"
    :sublabel="$stat['sublabel'] ?? null"
    :accent="$stat['accent'] ?? ($stat['tone'] ?? 'navy')"
    :icon-color="$stat['iconColor'] ?? null"
    :aria-label="$stat['ariaLabel'] ?? null"
/>
