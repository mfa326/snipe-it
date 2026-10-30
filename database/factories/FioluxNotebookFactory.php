<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\FioluxNotebook;
use App\Models\Manufacturer;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FioluxNotebookFactory extends Factory
{
    protected $model = FioluxNotebook::class;

    public function definition(): array
    {
        $creator = User::factory()->withoutCompany()->state(['first_name' => 'TESTE FIOLUX', 'email' => null, 'end_date' => null]);

        return [
            'available_status_id' => Statuslabel::factory()->rtd()->state(['name' => 'TESTE FIOLUX Disponível '.$this->faker->unique()->word(), 'created_by' => $creator]),
            'pending_status_id' => Statuslabel::factory()->pending()->state(['name' => 'TESTE FIOLUX Pendente '.$this->faker->unique()->word(), 'created_by' => $creator]),
            'asset_id' => function (array $attributes) use ($creator): int {
                $model = AssetModel::factory()->create([
                    'name' => 'TESTE FIOLUX Notebook '.$this->faker->unique()->word(),
                    'category_id' => Category::factory()->create(['name' => 'TESTE FIOLUX Categoria '.$this->faker->unique()->word(), 'category_type' => 'asset', 'require_acceptance' => 0, 'checkin_email' => 0, 'use_default_eula' => 0])->id,
                    'manufacturer_id' => Manufacturer::factory()->create(['name' => 'TESTE FIOLUX Fabricante '.$this->faker->unique()->word()])->id,
                ]);

                return Asset::factory()->create([
                    'asset_tag' => 'TESTE FIOLUX NB-'.$this->faker->unique()->numerify('######'),
                    'name' => 'TESTE FIOLUX Notebook', 'serial' => 'TESTE FIOLUX SERIAL '.$this->faker->uuid(),
                    'model_id' => $model->id, 'status_id' => $attributes['available_status_id'],
                    'created_by' => $creator, 'rtd_location_id' => null, 'supplier_id' => null,
                    'next_audit_date' => null, 'warranty_months' => 0, 'requestable' => 0,
                ])->id;
            },
        ];
    }
}
