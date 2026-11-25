<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase
{
    public function up(): void
    {
        Schema::create($this->prefix . 'currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name', 120);
            $table->string('symbol', 16);
            $table->unsignedTinyInteger('precision')->default(2);
            $table->boolean('symbol_first')->default(true);
            $table->string('decimal_mark', 4)->default('.');
            $table->string('thousands_separator', 4)->default(',');
            $table->boolean('is_base')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->decimal('exchange_rate', 18, 8)->default(1.0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create($this->prefix . 'exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')
                ->constrained($this->prefix . 'currencies', indexName: 'ium_acc_fx_curr_fk')
                ->cascadeOnDelete();
            $table->string('currency_code', 3)->index();
            $table->decimal('rate', 18, 8);
            $table->decimal('inverse_rate', 18, 8);
            $table->date('effective_date')->index();
            $table->string('source', 64)->default('manual');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['currency_code', 'effective_date'], 'ium_acc_fx_code_date_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'exchange_rates');
        Schema::dropIfExists($this->prefix . 'currencies');
    }
};
