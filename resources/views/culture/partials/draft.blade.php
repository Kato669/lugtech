{{-- "Draft" label for culture content a native speaker hasn't checked yet (Phase 15) --}}
@unless ($item->is_reviewed)
    <span class="badge bg-gold-300/50 text-xs text-bark-800" title="Not yet checked by a native speaker">Draft</span>
@endunless
