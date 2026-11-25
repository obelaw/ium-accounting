<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase
{
    public function up(): void
    {
        Schema::create($this->prefix . 'fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->string('status', 32)->default('open')->index();
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_by', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create($this->prefix . 'fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')
                ->constrained($this->prefix . 'fiscal_years', indexName: 'ium_acc_fp_fy_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('period_number');
            $table->string('name', 120);
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->string('status', 32)->default('open')->index();
            $table->timestamp('locked_at')->nullable();
            $table->string('locked_by', 128)->nullable();
            $table->string('lock_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['fiscal_year_id', 'period_number'], 'ium_acc_fp_fy_num_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'fiscal_periods');
        Schema::dropIfExists($this->prefix . 'fiscal_years');
    }
};
