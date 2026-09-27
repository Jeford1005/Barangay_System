<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The health and reports tables were scaffolded in the initial batch but
     * never implemented: no models, controllers, routes, or views reference
     * them, and they held zero rows. Dropped to keep the schema honest.
     * (The unrelated Mail Health feature does not use the `health` table.)
     */
    public function up(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('health');
    }

    public function down(): void
    {
        // Recreating these scaffolds is out of scope for the rollback path;
        // the original definitions live in git history
        // (2026_09_04_000007_create_health_table / 2026_09_04_000008_create_reports_table).
    }
};
