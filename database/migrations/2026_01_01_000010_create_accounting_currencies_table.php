<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique(); // ISO 4217, e.g. USD, EUR, SAR
            $table->string('name');
            $table->string('symbol', 16);
            $table->unsignedTinyInteger('precision')->default(2);
            $table->boolean('symbol_first')->default(true);
            $table->string('decimal_mark', 1)->default('.');
            $table->string('thousands_separator', 1)->default(',');
            $table->boolean('is_base')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->decimal('exchange_rate', 18, 6)->default(1.000000);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'currencies');
    }
};
