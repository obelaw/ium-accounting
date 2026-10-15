<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type'); // asset, liability, equity, revenue, expense
            $table->string('classification'); // class, main_group, sub_group, posting
            $table->string('normal_balance'); // debit, credit
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_leaf')->default(true);
            $table->boolean('is_reconciliation')->default(false);
            $table->boolean('is_active')->default(true);
            $table->bigInteger('current_balance')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')
                ->references('id')
                ->on($this->prefix . 'accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'accounts');
    }
};
