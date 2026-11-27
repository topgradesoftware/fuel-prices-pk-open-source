<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->string('code', 8)->unique();
                $table->string('name');
                $table->string('currency', 8);
                $table->string('currency_symbol', 16);
                $table->string('unit_volume', 16)->default('L');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(100);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('cities', 'country_id')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->foreignId('country_id')->nullable()->after('id')->constrained('countries')->nullOnDelete();
            });
        }

        // Slugs are unique per country (e.g. hyderabad in PK and IN).
        if ($this->hasIndex('cities', 'cities_slug_unique')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->dropUnique(['slug']);
            });
        }
        if (! $this->hasIndex('cities', 'cities_country_id_slug_unique')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->unique(['country_id', 'slug']);
            });
        }

        if (! Schema::hasColumn('fuel_companies', 'country_id')) {
            Schema::table('fuel_companies', function (Blueprint $table) {
                $table->foreignId('country_id')->nullable()->after('id')->constrained('countries')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('fuel_prices', 'country_id')) {
            Schema::table('fuel_prices', function (Blueprint $table) {
                $table->foreignId('country_id')->nullable()->after('id')->constrained('countries')->nullOnDelete();
            });
        }

        // MySQL FKs on company_id/city_id may be backed only by the old unique
        // index — add standalone indexes before dropping it.
        if (! $this->hasIndex('fuel_prices', 'fuel_prices_company_id_index')) {
            Schema::table('fuel_prices', function (Blueprint $table) {
                $table->index('company_id', 'fuel_prices_company_id_index');
            });
        }
        if (! $this->hasIndex('fuel_prices', 'fuel_prices_city_id_index')) {
            Schema::table('fuel_prices', function (Blueprint $table) {
                $table->index('city_id', 'fuel_prices_city_id_index');
            });
        }

        if ($this->hasIndex('fuel_prices', 'fuel_prices_unique_lookup')) {
            // Detect whether the unique already includes country_id.
            $includesCountry = $this->indexIncludesColumn(
                'fuel_prices',
                'fuel_prices_unique_lookup',
                'country_id',
            );
            if (! $includesCountry) {
                Schema::table('fuel_prices', function (Blueprint $table) {
                    $table->dropUnique('fuel_prices_unique_lookup');
                });
                Schema::table('fuel_prices', function (Blueprint $table) {
                    $table->unique(
                        ['country_id', 'company_id', 'city_id', 'fuel_type_id', 'source'],
                        'fuel_prices_unique_lookup',
                    );
                });
            }
        } else {
            Schema::table('fuel_prices', function (Blueprint $table) {
                $table->unique(
                    ['country_id', 'company_id', 'city_id', 'fuel_type_id', 'source'],
                    'fuel_prices_unique_lookup',
                );
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('fuel_prices', 'fuel_prices_unique_lookup')) {
            Schema::table('fuel_prices', function (Blueprint $table) {
                $table->dropUnique('fuel_prices_unique_lookup');
            });
        }

        if (Schema::hasColumn('fuel_prices', 'country_id')) {
            Schema::table('fuel_prices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('country_id');
            });
        }

        if (! $this->hasIndex('fuel_prices', 'fuel_prices_unique_lookup')) {
            Schema::table('fuel_prices', function (Blueprint $table) {
                $table->unique(
                    ['company_id', 'city_id', 'fuel_type_id', 'source'],
                    'fuel_prices_unique_lookup',
                );
            });
        }

        if (Schema::hasColumn('fuel_companies', 'country_id')) {
            Schema::table('fuel_companies', function (Blueprint $table) {
                $table->dropConstrainedForeignId('country_id');
            });
        }

        if ($this->hasIndex('cities', 'cities_country_id_slug_unique')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->dropUnique(['country_id', 'slug']);
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'sqlite' && ! $this->hasIndex('cities', 'cities_slug_unique')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->unique('slug');
            });
        } elseif ($driver === 'sqlite') {
            try {
                DB::statement('CREATE UNIQUE INDEX cities_slug_unique ON cities (slug)');
            } catch (\Throwable) {
                // ignore
            }
        }

        if (Schema::hasColumn('cities', 'country_id')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->dropConstrainedForeignId('country_id');
            });
        }

        Schema::dropIfExists('countries');
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            foreach (DB::select("PRAGMA index_list('{$table}')") as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$database, $table, $indexName],
        );

        return $row !== null;
    }

    private function indexIncludesColumn(string $table, string $indexName, string $column): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            foreach (DB::select("PRAGMA index_info('{$indexName}')") as $row) {
                if (($row->name ?? null) === $column) {
                    return true;
                }
            }

            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ? AND column_name = ?
             LIMIT 1',
            [$database, $table, $indexName, $column],
        );

        return $row !== null;
    }
};
