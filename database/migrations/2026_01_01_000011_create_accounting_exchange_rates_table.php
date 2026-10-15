<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('currency_id')->index();
            $table->string('currency_code', 3)->index();
            $table->decimal('rate', 18, 6); // 1 Foreign = rate Base (Direct Rate)
            $table->decimal('inverse_rate', 18, 6)->default(1.000000); // 1 Base = inverse_rate Foreign
            $table->date('effective_date')->index();
            $table->string('source')->nullable()->default('manual');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('currency_id')
                ->references('id')
                ->on($this->prefix . 'currencies')
                ->cascadeOnDelete();

            $table->unique(['currency_code', 'effective_date'], 'ium_acc_rates_code_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'exchange_rates');
    }
};
