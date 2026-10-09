<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id')->index();
            $table->unsignedSmallInteger('period_number');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('open'); // open, soft_closed, locked
            $table->timestamp('locked_at')->nullable();
            $table->string('locked_by')->nullable();
            $table->text('lock_reason')->nullable();
            $table->timestamps();

            $table->foreign('fiscal_year_id')
                ->references('id')
                ->on($this->prefix . 'fiscal_years')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'fiscal_periods');
    }
};
