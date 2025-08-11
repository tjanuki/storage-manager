<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->uuid('share_uuid')->nullable()->unique()->after('upload_id');
            $table->boolean('is_public')->default(false)->after('share_uuid');
            $table->timestamp('shared_at')->nullable()->after('is_public');
            
            $table->index('share_uuid');
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['share_uuid']);
            $table->dropIndex(['is_public']);
            $table->dropColumn(['share_uuid', 'is_public', 'shared_at']);
        });
    }
};
