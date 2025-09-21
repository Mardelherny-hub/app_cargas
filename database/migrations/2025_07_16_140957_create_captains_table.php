<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captains', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('full_name', 255);
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other', 'not_specified'])->nullable();
            $table->string('nationality', 3)->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mobile_phone', 30)->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('emergency_contact_relationship', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state_province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('license_country_id')->nullable();
            $table->unsignedBigInteger('primary_company_id')->nullable();
            $table->string('document_type', 20)->nullable();
            $table->string('document_number', 50)->nullable();
            $table->date('document_expires')->nullable();
            $table->unsignedBigInteger('document_country_id')->nullable()->comment('País emisor documento capitán (FK countries - optativo)');
            $table->string('license_number', 100)->unique();
            $table->enum('license_class', ['master', 'chief_officer', 'officer', 'pilot'])->default('officer');
            $table->enum('license_status', ['valid', 'expired', 'suspended', 'revoked', 'pending_renewal'])->default('valid');
            $table->date('license_issued_at')->nullable();
            $table->date('license_expires_at')->nullable();
            $table->string('medical_certificate_number', 100)->nullable();
            $table->date('medical_certificate_expires_at')->nullable();
            $table->string('safety_training_certificate', 100)->nullable();
            $table->date('safety_training_expires_at')->nullable();
            $table->integer('years_of_experience')->default(0);
            $table->date('first_voyage_date')->nullable();
            $table->date('last_voyage_date')->nullable();
            $table->integer('total_voyages_completed')->default(0);
            $table->enum('employment_status', ['employed', 'freelance', 'unemployed', 'retired'])->default('employed');
            $table->boolean('available_for_hire')->default(true);
            $table->decimal('daily_rate', 8, 2)->nullable();
            $table->string('rate_currency', 3)->default('USD');
            $table->decimal('performance_rating', 3, 2)->nullable();
            $table->integer('safety_incidents')->default(0);
            $table->text('performance_notes')->nullable();
            $table->text('specializations')->nullable();
            $table->json('vessel_type_competencies')->nullable();
            $table->json('cargo_type_competencies')->nullable();
            $table->json('route_restrictions')->nullable();
            $table->json('additional_certifications')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('verified')->default(false);
            $table->text('verification_notes')->nullable();
            $table->timestamp('created_date')->useCurrent();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('last_updated_date')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('last_updated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('license_country_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('primary_company_id')->references('id')->on('companies')->onDelete('set null');
            $table->foreign('document_country_id', 'fk_captains_document_country')
                ->references('id')->on('countries')
                ->onDelete('set null')
                ->onUpdate('cascade');

            // Índice para búsquedas por documento
            $table->index(['document_type', 'document_country_id'], 'idx_captains_document_info');
      
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captains');
    }
};