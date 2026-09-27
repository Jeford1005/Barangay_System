{{-- Shared fields for purok create/edit. Expects $purok (null on create). --}}
<x-form.field name="name" label="Purok Name" required :value="$purok->name ?? null" maxlength="50" placeholder="e.g. Purok 1" />
<x-form.field name="code" label="Code" optional-hint :value="$purok->code ?? null" maxlength="10" placeholder="e.g. P1" />
