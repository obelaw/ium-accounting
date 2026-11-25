<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase
{
    public function up(): void
    {
        Schema::create($this->prefix . 'journals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('type', 32)->index();
            $table->string('sequence_prefix', 16);
            $table->foreignId('default_debit_account_id')
                ->nullable()
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_jrn_def_dr_fk')
                ->nullOnDelete();
            $table->foreignId('default_credit_account_id')
                ->nullable()
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_jrn_def_cr_fk')
                ->nullOnDelete();
            $table->string('currency', 3)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'journals');
    }
};
