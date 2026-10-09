<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_id')->nullable()->index();
            $table->string('action')->index();
            $table->string('origin_context')->nullable();
            $table->morphs('auditable');
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'audit_logs');
    }
};
