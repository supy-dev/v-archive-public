@props(['icon', 'label', 'value', 'unit'])
<div class="stat-card"><x-icon :name="$icon" /><span><small>{{ $label }}</small><b>{{ $value }}<em>{{ $unit }}</em></b></span></div>
