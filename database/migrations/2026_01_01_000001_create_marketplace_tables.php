<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone', 30)->nullable();
            $table->string('location', 120)->nullable();
            $table->enum('user_type', ['customer', 'chef', 'admin'])->default('customer')->index();
            $table->boolean('is_active')->default(true);
            $table->string('avatar')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('chefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('specialty', 180)->default('Bengali');
            $table->text('bio')->nullable();
            $table->decimal('price_per_hour', 10, 2)->default(1500);
            $table->unsignedSmallInteger('experience_years')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedInteger('total_reviews')->default(0);
            $table->boolean('is_verified')->default(false)->index();
            $table->json('availability')->nullable();
            $table->string('profile_image')->nullable();
            $table->string('verification_docs')->nullable();
            $table->timestamps();
        });
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description');
            $table->string('icon', 80);
            $table->decimal('base_price', 10, 2);
            $table->unsignedSmallInteger('duration_hours')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_ref', 40)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('chef_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->date('booking_date');
            $table->time('booking_time');
            $table->unsignedSmallInteger('duration_hours');
            $table->unsignedSmallInteger('guests');
            $table->string('cuisine', 120)->nullable();
            $table->text('dietary_notes')->nullable();
            $table->text('special_instructions')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('platform_fee', 12, 2);
            $table->decimal('tax', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->index(['chef_id', 'booking_date', 'status']);
            $table->index(['customer_id', 'status']);
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->enum('method', ['card', 'bkash', 'nagad', 'rocket', 'cash']);
            $table->decimal('amount', 12, 2);
            $table->string('transaction_id', 50)->unique();
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->index(['booking_id', 'status']);
            $table->timestamps();
        });
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('chef_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('subject', 100);
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void {
        foreach ([
            'contacts',
            'reviews',
            'payments',
            'bookings',
            'services',
            'chefs',
            'personal_access_tokens',
            'password_reset_tokens',
            'users'
        ] as $table) Schema::dropIfExists($table);
    }
};
