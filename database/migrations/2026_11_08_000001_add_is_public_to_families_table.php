<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A family archive can be opened to visitors: anyone can then browse it read-only
 * without an account. Adding or editing anything still requires membership and a role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('hide_living_from_viewers');
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
