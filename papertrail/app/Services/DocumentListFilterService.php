<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

class DocumentListFilterService
{
    /** Read the existing, rendered filter controls without changing their query contract. */
    public function describe(string $html, array $query, string $action): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $labels = [];
        foreach ($xpath->query('//label[@for]') as $label) {
            $labels[$label->getAttribute('for')] = $this->text($label->textContent);
        }

        $fields = $values = $context = [];
        $searchName = null;
        foreach ($xpath->query('//input[@name] | //select[@name] | //textarea[@name]') as $control) {
            if (! $control instanceof DOMElement || $control->hasAttribute('disabled')) {
                continue;
            }
            $name = $control->getAttribute('name');
            $type = $control->tagName === 'select' ? 'select' : ($control->getAttribute('type') ?: 'text');
            if (in_array($type, ['submit', 'button', 'reset', 'file'], true)) {
                continue;
            }
            $value = $control->getAttribute('value');
            $label = $labels[$control->getAttribute('id')] ?? '';
            if ($label === '') {
                for ($parent = $control->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
                    if ($parent->tagName === 'label') {
                        $copy = $parent->cloneNode(true);
                        foreach (iterator_to_array($copy->getElementsByTagName($control->tagName)) as $child) {
                            $child->parentNode->removeChild($child);
                        }
                        $label = $this->text($copy->textContent);
                        break;
                    }
                }
            }
            $field = ['label' => $label ?: Str::headline($name), 'type' => $type];
            if ($type === 'select') {
                $options = $disabled = [];
                $selected = null;
                $default = null;
                foreach ($control->getElementsByTagName('option') as $option) {
                    $optionValue = $option->hasAttribute('value') ? $option->getAttribute('value') : $this->text($option->textContent);
                    $options[$optionValue] = $this->text($option->textContent);
                    if ($option->hasAttribute('disabled')) {
                        $disabled[] = $optionValue;
                    } else {
                        $default ??= $optionValue;
                    }
                    if ($option->hasAttribute('selected')) {
                        $selected = $optionValue;
                    }
                }
                $value = $selected ?? $default ?? '';
                $field += ['options' => $options, 'empty' => null, 'neutral' => (string) (array_key_first($options) ?? ''), 'disabled_options' => $disabled];
            }
            foreach (['min', 'max', 'step', 'placeholder', 'maxlength'] as $attribute) {
                if ($control->hasAttribute($attribute)) {
                    $field[$attribute] = $control->getAttribute($attribute);
                }
            }
            $field['neutral'] ??= '';
            if (isset($query[$name]) && is_scalar($query[$name])) {
                $value = (string) $query[$name];
            }
            if ($type === 'hidden') {
                if (! $this->isPagination($name)) {
                    $context[$name] = $value;
                }

                continue;
            }
            if ($type === 'search' || in_array($name, ['search', 'q'], true)) {
                $searchName ??= $name;
                $values[$name] = $value;

                continue;
            }
            if ($type === 'select' && $value !== '' && ! array_key_exists($value, $field['options'])) {
                // Summary cards may use grouped statuses that are absent from the dropdown.
                $field['options'] = [$value => Str::headline($value)] + $field['options'];
            }
            $fields[$name] = $field;
            $values[$name] = $value;
        }

        // Preserve action parameters and fixed views, including the Returned tab.
        parse_str(parse_url($action, PHP_URL_QUERY) ?? '', $actionQuery);
        foreach ($actionQuery as $name => $value) {
            if (is_scalar($value) && ! $this->isPagination($name)) {
                $context[$name] = (string) $value;
            }
        }
        foreach ($query as $name => $value) {
            if (! is_scalar($value) || $this->isPagination($name) || isset($values[$name]) || isset($context[$name])) {
                continue;
            }
            if (in_array($name, ['tab', 'view', 'per_page'], true)) {
                $context[$name] = (string) $value;
            } elseif (trim((string) $value) !== '') {
                $fields[$name] = ['label' => Str::headline($name), 'type' => 'hidden'];
                $values[$name] = (string) $value;
            }
        }
        $clearUrl = $action;
        foreach ($xpath->query('//a[@href]') as $link) {
            if (preg_match('/^(clear|reset)(\s|$)/i', $this->text($link->textContent))) {
                $clearUrl = $link->getAttribute('href');
                break;
            }
        }

        return compact('fields', 'values', 'context', 'searchName', 'clearUrl');
    }

    public function withQuery(string $url, array $parameters): string
    {
        [$url, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        [$base, $query] = array_pad(explode('?', $url, 2), 2, '');
        parse_str($query, $existing);
        $query = http_build_query(array_merge($existing, $parameters));

        return $base.($query !== '' ? '?'.$query : '').($fragment !== null ? '#'.$fragment : '');
    }

    private function text(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function isPagination(string $name): bool
    {
        return $name === 'page' || ($name !== 'per_page' && str_ends_with($name, '_page'));
    }
}
