<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'event_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('event_name')->unique();
            $table->string('debit_account_code');
            $table->string('credit_account_code');
            $table->string('description_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'event_account_mappings');
    }
};
