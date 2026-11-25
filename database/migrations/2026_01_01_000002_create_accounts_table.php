<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase
{
    public function up(): void
    {
        Schema::create($this->prefix . 'accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('type', 32)->index();
            $table->string('classification', 32)->default('posting')->index();
            $table->string('normal_balance', 16);
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_accounts_parent_fk')
                ->nullOnDelete();
            $table->string('currency', 3)->nullable();
            $table->boolean('is_leaf')->default(true)->index();
            $table->boolean('is_reconcilable')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->bigInteger('current_balance')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'accounts');
    }
};
